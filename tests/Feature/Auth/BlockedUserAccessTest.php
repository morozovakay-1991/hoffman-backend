<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockedUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_blocked_user_with_a_valid_token_is_rejected_on_a_protected_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $user->forceFill(['blocked_at' => now()])->save();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_BLOCKED');
    }

    public function test_an_active_user_with_a_valid_token_is_not_affected_by_the_block_check(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
    }
}
