<?php

namespace Tests\Feature\Webhooks;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Stripe\WebhookSignature;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.stripe.webhook_secret', self::SECRET);
    }

    public function test_a_valid_checkout_completed_event_activates_the_subscription_once_even_if_sent_twice(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'stripe',
            'product_id' => 'monthly',
            'status' => 'pending',
            'currency' => 'USD',
            'country' => 'US',
            'external_customer_id' => 'cus_test_123',
            'external_subscription_id' => null,
        ]);

        $payload = $this->checkoutCompletedPayload('evt_test_1', 'cus_test_123', 'sub_test_123');

        $response = $this->sendStripeWebhook($payload);
        $response->assertOk()->assertJson(['status' => 'ok']);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('sub_test_123', $subscription->external_subscription_id);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'provider' => 'stripe',
            'status' => 'paid',
            'amount' => 999,
        ]);
        $this->assertDatabaseCount('payment_webhook_events', 1);

        // Stripe retries deliveries, so the same event id may arrive again.
        $repeat = $this->sendStripeWebhook($payload);
        $repeat->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_it_rejects_an_event_with_an_invalid_signature(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'stripe',
            'product_id' => 'monthly',
            'status' => 'pending',
            'external_customer_id' => 'cus_test_123',
        ]);

        $payload = $this->checkoutCompletedPayload('evt_test_2', 'cus_test_123', 'sub_test_123');

        $response = $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid-signature',
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_SIGNATURE');

        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertSame('pending', $subscription->refresh()->status);
    }

    private function checkoutCompletedPayload(string $eventId, string $customerId, string $subscriptionId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'customer' => $customerId,
                    'subscription' => $subscriptionId,
                    'client_reference_id' => '1',
                    'amount_total' => 999,
                    'currency' => 'usd',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function sendStripeWebhook(string $payload)
    {
        $signature = WebhookSignature::generateSignatureHeader($payload, self::SECRET);

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
