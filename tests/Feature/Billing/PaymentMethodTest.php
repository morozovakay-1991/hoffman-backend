<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Gateways\CloudPaymentsGateway;
use App\Domain\Billing\Gateways\StripeGateway;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_stripe_billing_portal_url(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'payment_provider' => 'stripe',
            'country' => 'US',
            'currency' => 'USD',
            'external_customer_id' => 'cus_test_123',
        ]);

        $this->mock(StripeGateway::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('updatePaymentMethod')
                ->once()
                ->with(\Mockery::on(fn (Subscription $arg) => $arg->id === $subscription->id))
                ->andReturn(['checkout_url' => 'https://billing.stripe.com/session/xyz']);
        });

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/payment-method');

        $response->assertOk()
            ->assertJsonPath('payment_method.checkout_url', 'https://billing.stripe.com/session/xyz');
    }

    public function test_it_returns_a_cloudpayments_widget_url(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'payment_provider' => 'cloudpayments',
            'country' => 'RU',
            'currency' => 'RUB',
        ]);

        $this->mock(CloudPaymentsGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('updatePaymentMethod')
                ->once()
                ->andReturn(['checkout_url' => 'https://api.cloudpayments.ru/orders/pay/card-update']);
        });

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/payment-method');

        $response->assertOk()
            ->assertJsonPath('payment_method.checkout_url', 'https://api.cloudpayments.ru/orders/pay/card-update');
    }

    public function test_it_returns_not_found_when_the_user_has_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/payment-method');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_FOUND');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->postJson('/api/v1/billing/payment-method')->assertStatus(401);
    }
}
