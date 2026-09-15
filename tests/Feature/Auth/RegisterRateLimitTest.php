<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_429_after_exceeding_the_register_rate_limit(): void
    {
        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secret1!',
        ];

        for ($i = 0; $i < 5; $i++) {
            // The 2nd..5th attempts fail validation (email already taken), which is fine:
            // the limiter counts requests regardless of the resulting status.
            $this->postJson('/api/v1/auth/register', $payload);
        }

        // The 6th attempt within the same minute, for the same IP+email, must be throttled.
        $this->postJson('/api/v1/auth/register', $payload)->assertStatus(429);
    }
}
