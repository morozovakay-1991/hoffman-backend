<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verifies the centralized exception handler's safety net: exceptions that
 * fall through without a domain render() (route-model-binding 404s, rate
 * limiting, Gate::authorize() failures) still come back in the project's
 * unified {error: {code, message, fields}} format instead of Laravel's
 * or Symfony's default error body.
 */
class ErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_nonexistent_meditation_id_returns_the_unified_not_found_format(): void
    {
        $response = $this->getJson('/api/v1/meditations/999999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields']]);
    }

    public function test_hitting_the_login_rate_limit_returns_the_unified_too_many_requests_format(): void
    {
        RateLimiter::clear('login');

        $credentials = ['email' => 'jane@example.com', 'password' => 'WrongPass1!'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $credentials);
        }

        $response = $this->postJson('/api/v1/auth/login', $credentials);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields']]);
    }

    public function test_accessing_another_users_invoice_returns_the_unified_forbidden_format(): void
    {
        $owner = User::factory()->create();
        $foreignInvoice = Invoice::factory()->create(['user_id' => $owner->id]);

        $attacker = User::factory()->create();

        $response = $this->actingAsApiUser($attacker)->getJson("/api/v1/billing/invoices/{$foreignInvoice->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields']]);
    }
}
