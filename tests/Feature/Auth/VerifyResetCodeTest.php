<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyResetCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_a_valid_code_without_burning_it(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        $resetCode = PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => '123456',
        ]);

        $response->assertOk();

        $resetCode->refresh();
        $this->assertNotNull($resetCode->verified_at);
        $this->assertNull($resetCode->used_at);
    }

    public function test_it_rejects_verify_code_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'unknown@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'EMAIL_NOT_FOUND');
    }

    public function test_it_rejects_verify_code_when_no_code_was_requested(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');
    }

    public function test_it_rejects_an_incorrect_code_and_increments_attempts(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        $resetCode = PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');

        $this->assertSame(1, $resetCode->refresh()->attempts);
        $this->assertNull($resetCode->verified_at);
    }

    public function test_it_rejects_an_expired_code(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CODE_EXPIRED');
    }

    public function test_it_rejects_after_reaching_the_maximum_number_of_attempts(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_five_wrong_attempts_lock_the_code(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/password/verify-code', [
                'email' => 'jane@example.com',
                'code' => '000000',
            ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_CODE');
        }

        $response = $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_it_rejects_verify_code_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/password/verify-code', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.email.0', 'The email field is required.')
            ->assertJsonPath('error.fields.code.0', 'The code field is required.');
    }
}
