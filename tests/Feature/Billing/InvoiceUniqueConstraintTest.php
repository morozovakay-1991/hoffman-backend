<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Stripe\WebhookSignature;
use Tests\TestCase;

class InvoiceUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    private const STRIPE_SECRET = 'whsec_test_secret';

    /**
     * The partial unique index on invoices(provider, external_invoice_id)
     * WHERE external_invoice_id IS NOT NULL is the safety net a duplicate
     * insert ultimately hits, regardless of which code path produced it.
     */
    public function test_a_second_invoice_with_the_same_provider_and_external_id_is_rejected_at_the_database_level(): void
    {
        $invoice = Invoice::factory()->create([
            'provider' => 'stripe',
            'external_invoice_id' => 'in_duplicate_test',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('invoices')->insert([
            'user_id' => $invoice->user_id,
            'subscription_id' => $invoice->subscription_id,
            'provider' => 'stripe',
            'external_invoice_id' => 'in_duplicate_test',
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => 'paid',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The index is a partial one precisely so it does not restrict rows
     * where external_invoice_id is null (e.g. invoices recorded without a
     * provider reference) — multiple such rows for the same provider must
     * remain possible.
     */
    public function test_multiple_invoices_with_a_null_external_invoice_id_are_allowed_for_the_same_provider(): void
    {
        Invoice::factory()->create(['provider' => 'stripe', 'external_invoice_id' => null]);
        Invoice::factory()->create(['provider' => 'stripe', 'external_invoice_id' => null]);

        $this->assertDatabaseCount('invoices', 2);
    }

    /**
     * alreadyProcessed() in WebhookService dedupes on the webhook's own
     * event id, not on the invoice id it carries. Two distinct Stripe event
     * ids can reference the same underlying invoice (e.g. a dashboard
     * resend, or Stripe redelivering with a fresh event id), so this drives
     * WebhookService::recordInvoice() through two separate deliveries that
     * both resolve to the same external_invoice_id and asserts only one
     * invoice row is ever persisted.
     */
    public function test_two_stripe_events_referencing_the_same_invoice_do_not_create_a_duplicate_invoice(): void
    {
        Config::set('services.stripe.webhook_secret', self::STRIPE_SECRET);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'stripe',
            'product_id' => 'monthly',
            'status' => 'active',
            'currency' => 'USD',
            'country' => 'US',
            'external_subscription_id' => 'sub_test_123',
            'external_customer_id' => 'cus_test_123',
        ]);

        $first = $this->sendStripeWebhook($this->invoicePaidPayload('evt_test_1', 'sub_test_123', 'in_shared_id'));
        $first->assertOk();

        $second = $this->sendStripeWebhook($this->invoicePaidPayload('evt_test_2', 'sub_test_123', 'in_shared_id'));
        $second->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 2);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'provider' => 'stripe',
            'external_invoice_id' => 'in_shared_id',
        ]);
    }

    private function invoicePaidPayload(string $eventId, string $subscriptionId, string $invoiceId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'object' => 'invoice',
                    'subscription' => $subscriptionId,
                    'amount_paid' => 999,
                    'currency' => 'usd',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function sendStripeWebhook(string $payload)
    {
        $signature = WebhookSignature::generateSignatureHeader($payload, self::STRIPE_SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );
    }
}
