<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resets_the_password_with_a_previously_verified_code(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $resetCode = PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('NewSecret1!', $user->refresh()->password));
        $this->assertNotNull($resetCode->refresh()->used_at);
    }

    public function test_it_allows_logging_in_with_the_new_password_after_reset(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ])->assertOk();
    }

    public function test_full_password_reset_flow_end_to_end(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();
        $code = PasswordResetCode::where('email', 'jane@example.com')->firstOrFail()->code;

        $this->postJson('/api/v1/auth/password/verify-code', [
            'email' => 'jane@example.com',
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ])->assertOk();
    }

    public function test_it_rejects_reset_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'unknown@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'EMAIL_NOT_FOUND');
    }

    public function test_it_rejects_reset_when_no_code_was_requested(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');
    }

    public function test_it_rejects_reset_when_the_code_was_not_verified_yet(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');
    }

    public function test_it_rejects_reset_when_the_verified_code_has_expired(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
            'verified_at' => now()->subMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CODE_EXPIRED');
    }

    public function test_it_rejects_reset_after_reaching_the_maximum_number_of_attempts(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 5,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_it_rejects_reuse_of_an_already_used_code(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'used_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_it_rejects_reset_with_an_invalid_password_format(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        PasswordResetCode::create([
            'email' => 'jane@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'jane@example.com',
            'password' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
