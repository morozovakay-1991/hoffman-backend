<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_soft_deletes_the_account_immediately(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->deleteJson('/api/v1/profile');

        $response->assertStatus(204);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_it_revokes_all_access_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('other_device');

        $this->actingAsApiUser($user)->deleteJson('/api/v1/profile')->assertStatus(204);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_revoked_token_can_no_longer_authenticate(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/profile')
            ->assertStatus(204);

        // Sanctum's guard caches the resolved user on the guard instance itself, and the
        // service container isn't rebooted between calls within a single test, so the
        // cache must be cleared manually to simulate the token being checked afresh.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->deleteJson('/api/v1/profile')->assertStatus(401);
    }
}
