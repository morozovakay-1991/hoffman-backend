<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_429_after_exceeding_the_profile_rate_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAsApiUser($user)->getJson('/api/v1/profile')->assertOk();
        }

        // The 31st request within the same minute, for the same user, must be throttled.
        $this->actingAsApiUser($user)->getJson('/api/v1/profile')->assertStatus(429);
    }

    public function test_the_profile_rate_limit_is_keyed_per_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAsApiUser($user)->getJson('/api/v1/profile')->assertOk();
        }
        $this->actingAsApiUser($user)->getJson('/api/v1/profile')->assertStatus(429);

        // The sanctum guard caches the resolved user on first use within a test; forget
        // it so the next request actually re-authenticates as the other user.
        $this->app['auth']->forgetGuards();

        // A different, authenticated user has their own bucket and is unaffected.
        $this->actingAsApiUser($otherUser)->getJson('/api/v1/profile')->assertOk();
    }
}
