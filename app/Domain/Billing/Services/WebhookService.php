<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Billing\Support\PlanCatalog;
use App\Models\Invoice;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;
use UnexpectedValueException;

class WebhookService
{
    /**
     * @throws InvalidWebhookSignatureException
     */
    public function handleStripe(Request $request): void
    {
        $secret = (string) config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            throw new InvalidWebhookSignatureException();
        }

        if ($this->alreadyProcessed('stripe', $event->id)) {
            return;
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };

        $this->markProcessed('stripe', $event->id, $event->type, $event->toArray());
    }

    /**
     * @throws InvalidWebhookSignatureException
     */
    public function handleCloudPayments(Request $request): void
    {
        $payload = $request->getContent();
        $secret = (string) config('services.cloudpayments.api_secret');
        $signature = (string) $request->header('Content-HMAC');

        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException();
        }

        parse_str($payload, $data);

        $transactionId = (string) ($data['TransactionId'] ?? '');

        if ($transactionId === '') {
            return;
        }

        if ($this->alreadyProcessed('cloudpayments', $transactionId)) {
            return;
        }

        $status = (string) ($data['Status'] ?? '');

        if ($status === 'Completed') {
            $this->handleCloudPaymentsSuccess($data);
        } else {
            $this->handleCloudPaymentsFailure($data);
        }

        $this->markProcessed('cloudpayments', $transactionId, $status, $data);
    }

    private function handleCheckoutSessionCompleted(StripeObject $object): void
    {
        $subscription = $this->findStripeSubscription($object['subscription'] ?? null, $object['customer'] ?? null);

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'external_subscription_id' => $object['subscription'] ?? $subscription->external_subscription_id,
            'external_customer_id' => $object['customer'] ?? $subscription->external_customer_id,
            'starts_at' => $subscription->starts_at ?? now(),
            'expires_at' => $this->expiresAtForPlan($subscription->product_id) ?? $subscription->expires_at,
        ]);

        $this->recordInvoice(
            $subscription,
            'stripe',
            $object['id'] ?? null,
            $object['amount_total'] ?? null,
            $object['currency'] ?? $subscription->currency,
        );
    }

    private function handleInvoicePaid(StripeObject $object): void
    {
        $subscription = $this->findStripeSubscription($object['subscription'] ?? null, $object['customer'] ?? null);

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'expires_at' => $this->expiresAtForPlan($subscription->product_id) ?? $subscription->expires_at,
        ]);

        $this->recordInvoice(
            $subscription,
            'stripe',
            $object['id'] ?? null,
            $object['amount_paid'] ?? null,
            $object['currency'] ?? $subscription->currency,
        );
    }

    private function handleSubscriptionDeleted(StripeObject $object): void
    {
        $subscription = $this->findStripeSubscription($object['id'] ?? null, $object['customer'] ?? null);

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => 'cancelled',
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleCloudPaymentsSuccess(array $data): void
    {
        $invoiceId = (string) ($data['InvoiceId'] ?? '');

        $subscription = $this->findCloudPaymentsSubscription($invoiceId);

        if ($subscription === null) {
            Log::warning('CloudPayments webhook: no subscription bound to InvoiceId, ignoring notification.', [
                'invoice_id' => $invoiceId,
                'transaction_id' => $data['TransactionId'] ?? null,
                'account_id' => $data['AccountId'] ?? null,
            ]);

            return;
        }

        $subscription->update([
            'status' => 'active',
            'external_subscription_id' => $data['SubscriptionId'] ?? $subscription->external_subscription_id,
            'starts_at' => $subscription->starts_at ?? now(),
            'expires_at' => $this->expiresAtForPlan($subscription->product_id) ?? $subscription->expires_at,
        ]);

        $amount = isset($data['Amount']) ? (int) round(((float) $data['Amount']) * 100) : null;

        $this->recordInvoice(
            $subscription,
            'cloudpayments',
            $data['TransactionId'] ?? null,
            $amount,
            $data['Currency'] ?? $subscription->currency,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleCloudPaymentsFailure(array $data): void
    {
        // No subscription state change: a declined payment leaves the pending
        // subscription as-is so the user can retry the checkout.
    }

    private function findStripeSubscription(?string $subscriptionId, ?string $customerId): ?Subscription
    {
        $query = Subscription::query()->where('payment_provider', 'stripe');

        if ($subscriptionId !== null) {
            $match = (clone $query)->where('external_subscription_id', $subscriptionId)->latest('id')->first();

            if ($match !== null) {
                return $match;
            }
        }

        if ($customerId !== null) {
            return $query->where('external_customer_id', $customerId)->latest('id')->first();
        }

        return null;
    }

    /**
     * Resolves the subscription bound to a CloudPayments checkout by the InvoiceId we
     * generated and handed to CloudPayments when the checkout session was created (stored
     * on the subscription as external_customer_id). The notification's AccountId/Email are
     * never used to pick the subscription: they are supplied by the webhook payload itself
     * and are not a reliable link back to a specific checkout attempt.
     */
    private function findCloudPaymentsSubscription(string $invoiceId): ?Subscription
    {
        if ($invoiceId === '') {
            return null;
        }

        return Subscription::query()
            ->where('payment_provider', 'cloudpayments')
            ->where('external_customer_id', $invoiceId)
            ->latest('id')
            ->first();
    }

    private function expiresAtForPlan(string $planId): ?Carbon
    {
        $interval = PlanCatalog::find($planId)['interval'] ?? null;

        return match ($interval) {
            'month' => now()->addMonth(),
            'year' => now()->addYear(),
            default => null,
        };
    }

    /**
     * alreadyProcessed() dedupes by the webhook's own event id, but two
     * distinct event ids (e.g. a Stripe retry or a resend via the dashboard)
     * can still reference the same underlying invoice. The exists() check
     * below catches that in the common case; the partial unique index on
     * invoices(provider, external_invoice_id) backs it up at the database
     * level, so a race between two such deliveries can't create a duplicate
     * invoice row either.
     */
    private function recordInvoice(
        Subscription $subscription,
        string $provider,
        ?string $externalInvoiceId,
        ?int $amount,
        ?string $currency,
    ): void {
        if ($externalInvoiceId !== null) {
            $exists = Invoice::query()
                ->where('provider', $provider)
                ->where('external_invoice_id', $externalInvoiceId)
                ->exists();

            if ($exists) {
                return;
            }
        }

        try {
            Invoice::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'provider' => $provider,
                'external_invoice_id' => $externalInvoiceId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            Log::info('WebhookService: duplicate invoice insert avoided by the database unique constraint.', [
                'provider' => $provider,
                'external_invoice_id' => $externalInvoiceId,
            ]);
        }
    }

    private function alreadyProcessed(string $provider, string $externalEventId): bool
    {
        return PaymentWebhookEvent::query()
            ->where('provider', $provider)
            ->where('external_event_id', $externalEventId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markProcessed(string $provider, string $externalEventId, ?string $eventType, array $payload): void
    {
        PaymentWebhookEvent::create([
            'provider' => $provider,
            'external_event_id' => $externalEventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'processed_at' => now(),
        ]);
    }
}
