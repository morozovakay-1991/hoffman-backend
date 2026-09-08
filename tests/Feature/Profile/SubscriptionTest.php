<?php

namespace Tests\Feature\Profile;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_default_status_when_the_user_has_no_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/subscription');

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'none')
            ->assertJsonPath('subscription.is_active', false);
    }

    public function test_it_returns_the_users_active_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create(['status' => 'active']);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/subscription');

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.is_active', true);
    }

    public function test_it_returns_the_most_recent_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create(['status' => 'expired']);
        $latest = Subscription::factory()->for($user)->create(['status' => 'active']);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/subscription');

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.product_id', $latest->product_id);
    }

    public function test_an_expired_subscription_is_reported_as_inactive(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->expired()->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/subscription');

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'expired')
            ->assertJsonPath('subscription.is_active', false);
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->getJson('/api/v1/subscription')->assertStatus(401);
    }
}
