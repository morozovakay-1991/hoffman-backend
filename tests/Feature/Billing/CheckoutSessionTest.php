<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Gateways\CloudPaymentsGateway;
use App\Domain\Billing\Gateways\StripeGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_stripe_for_a_non_russian_country_and_creates_a_pending_subscription(): void
    {
        $user = User::factory()->create();

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->with(\Mockery::type(User::class), 'monthly', 'USD', 'US')
                ->andReturn([
                    'checkout_url' => 'https://checkout.stripe.com/session/abc123',
                    'session_id' => 'cs_test_abc123',
                    'customer_id' => 'cus_test_abc123',
                ]);
        });

        $this->mock(CloudPaymentsGateway::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCheckoutSession');
        });

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/checkout-session', [
            'plan' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subscription.status', 'pending')
            ->assertJsonPath('subscription.payment_provider', 'stripe')
            ->assertJsonPath('checkout.checkout_url', 'https://checkout.stripe.com/session/abc123');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'product_id' => 'monthly',
            'status' => 'pending',
            'payment_provider' => 'stripe',
            'currency' => 'USD',
            'country' => 'US',
            'external_customer_id' => 'cus_test_abc123',
        ]);
    }

    public function test_it_uses_cloudpayments_for_russia_and_creates_a_pending_subscription(): void
    {
        $user = User::factory()->create();

        $this->mock(CloudPaymentsGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->with(\Mockery::type(User::class), 'yearly', 'RUB', 'RU')
                ->andReturn([
                    'checkout_url' => 'https://api.cloudpayments.ru/orders/pay/xyz789',
                    'invoice_id' => 'INV-789',
                ]);
        });

        $this->mock(StripeGateway::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCheckoutSession');
        });

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/checkout-session', [
            'plan' => 'yearly',
            'currency' => 'RUB',
            'country' => 'ru',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subscription.status', 'pending')
            ->assertJsonPath('subscription.payment_provider', 'cloudpayments')
            ->assertJsonPath('checkout.checkout_url', 'https://api.cloudpayments.ru/orders/pay/xyz789');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'product_id' => 'yearly',
            'status' => 'pending',
            'payment_provider' => 'cloudpayments',
            'currency' => 'RUB',
            'country' => 'RU',
            'external_customer_id' => 'INV-789',
        ]);
    }

    public function test_it_rejects_an_unknown_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/checkout-session', [
            'plan' => 'does-not-exist',
            'currency' => 'USD',
            'country' => 'US',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_it_rejects_an_unsupported_currency(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/checkout-session', [
            'plan' => 'monthly',
            'currency' => 'GBP',
            'country' => 'US',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->postJson('/api/v1/billing/checkout-session', [
            'plan' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
        ])->assertStatus(401);
    }
}
