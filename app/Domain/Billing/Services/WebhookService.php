<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Billing\Support\PlanCatalog;
use App\Models\Invoice;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $userId = $data['AccountId'] ?? null;

        $subscription = Subscription::query()
            ->where('payment_provider', 'cloudpayments')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->whereIn('status', ['pending', 'active'])
            ->latest('id')
            ->first();

        if ($subscription === null) {
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

    private function expiresAtForPlan(string $planId): ?Carbon
    {
        $interval = PlanCatalog::find($planId)['interval'] ?? null;

        return match ($interval) {
            'month' => now()->addMonth(),
            'year' => now()->addYear(),
            default => null,
        };
    }

    private function recordInvoice(
        Subscription $subscription,
        string $provider,
        ?string $externalInvoiceId,
        ?int $amount,
        ?string $currency,
    ): void {
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
