<?php

namespace Tests\Feature\Verification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationSubmitRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_429_after_exceeding_the_verification_submit_rate_limit(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $payload = ['last_name' => 'Petrov', 'first_name' => 'Ivan', 'phone' => '89001234567'];

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/verification/submit', $payload);
        }

        // The 6th attempt within the same minute, for the same user, must be throttled.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', $payload)
            ->assertStatus(429);
    }

    public function test_it_does_not_throttle_the_unrelated_status_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $payload = ['last_name' => 'Petrov', 'first_name' => 'Ivan', 'phone' => '89001234567'];

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/verification/submit', $payload);
        }
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', $payload)
            ->assertStatus(429);

        // The status endpoint has no rate limit of its own and is unaffected.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/verification/status')
            ->assertOk();
    }
}
