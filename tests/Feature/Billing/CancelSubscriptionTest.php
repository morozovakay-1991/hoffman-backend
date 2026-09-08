<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Gateways\CloudPaymentsGateway;
use App\Domain\Billing\Gateways\StripeGateway;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_the_latest_active_subscription_via_stripe(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'payment_provider' => 'stripe',
            'country' => 'US',
            'currency' => 'USD',
            'external_subscription_id' => 'sub_test_123',
        ]);

        $this->mock(StripeGateway::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('cancelSubscription')
                ->once()
                ->with(\Mockery::on(fn (Subscription $arg) => $arg->id === $subscription->id));
        });

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/cancel');

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'cancelled');

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertFalse($subscription->auto_renew);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_it_cancels_the_latest_active_subscription_via_cloudpayments(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'payment_provider' => 'cloudpayments',
            'country' => 'RU',
            'currency' => 'RUB',
            'external_subscription_id' => 'cp_sub_123',
        ]);

        $this->mock(CloudPaymentsGateway::class, function (MockInterface $mock) use ($subscription) {
            $mock->shouldReceive('cancelSubscription')
                ->once()
                ->with(\Mockery::on(fn (Subscription $arg) => $arg->id === $subscription->id));
        });

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/cancel');

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'cancelled');

        $this->assertSame('cancelled', $subscription->refresh()->status);
    }

    public function test_it_returns_not_found_when_the_user_has_no_active_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->cancelled()->create(['user_id' => $user->id]);

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/billing/cancel');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_FOUND');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->postJson('/api/v1/billing/cancel')->assertStatus(401);
    }
}
