<?php

namespace Tests\Feature\Verification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_when_nothing_was_submitted_yet(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/verification/status');

        $response->assertOk()
            ->assertJsonPath('verification_request', null);
    }

    public function test_it_returns_the_latest_verification_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Unknown',
                'first_name' => 'Person',
                'phone' => '89991234567',
            ])->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/verification/status');

        $response->assertOk()
            ->assertJsonPath('verification_request.status', 'pending')
            ->assertJsonPath('verification_request.last_name', 'Unknown');
    }

    public function test_it_rejects_the_request_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/verification/status');

        $response->assertStatus(401);
    }
}
