<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_reset_code_for_a_known_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('password_reset_codes', 1);

        $resetCode = PasswordResetCode::where('email', 'jane@example.com')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $resetCode->code);
        $this->assertSame(0, $resetCode->attempts);
        $this->assertNull($resetCode->verified_at);
        $this->assertNull($resetCode->used_at);
        $this->assertTrue($resetCode->expires_at->between(now()->addMinutes(4), now()->addMinutes(5)));
    }

    public function test_it_rejects_forgot_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'EMAIL_NOT_FOUND');
    }

    public function test_it_rejects_forgot_with_missing_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.email.0', 'The email field is required.');
    }

    public function test_it_invalidates_the_previous_code_when_requested_again(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();
        $firstCode = PasswordResetCode::where('email', 'jane@example.com')->firstOrFail();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();

        $this->assertDatabaseCount('password_reset_codes', 1);
        $this->assertDatabaseMissing('password_reset_codes', ['id' => $firstCode->id]);
    }
}
