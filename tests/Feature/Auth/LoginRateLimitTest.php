<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_429_after_exceeding_the_login_rate_limit(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Secret1!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'WrongPass1!',
            ]);

            $response->assertStatus(401);
        }

        // The 6th attempt within the same minute, for the same IP+email, must be throttled.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(429);
    }

    public function test_it_does_not_throttle_login_requests_within_the_normal_rate(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Secret1!'),
        ]);

        // Well below the 5-per-minute limit: every request should succeed normally.
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'Secret1!',
            ]);

            $response->assertOk()->assertJsonPath('user.id', $user->id);
        }
    }

    public function test_the_login_rate_limit_is_keyed_per_email_so_other_accounts_are_unaffected(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Secret1!'),
        ]);
        $otherUser = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Secret1!'),
        ]);

        // Exhaust the bucket for jane@example.com from this IP.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'WrongPass1!',
            ])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(429);

        // A different email from the same IP is a different bucket and is unaffected.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'Secret1!',
        ])->assertOk()->assertJsonPath('user.id', $otherUser->id);
    }
}
