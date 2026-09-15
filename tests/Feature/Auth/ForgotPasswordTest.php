<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS_MESSAGE = 'A password reset code has been sent to the provided email address.';

    public function test_it_generates_a_reset_code_for_a_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk()->assertExactJson(['message' => self::SUCCESS_MESSAGE]);

        $this->assertDatabaseCount('password_reset_codes', 1);

        $resetCode = PasswordResetCode::where('email', 'jane@example.com')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $resetCode->code);
        $this->assertSame(0, $resetCode->attempts);
        $this->assertNull($resetCode->verified_at);
        $this->assertNull($resetCode->used_at);
        $this->assertTrue($resetCode->expires_at->between(now()->addMinutes(4), now()->addMinutes(5)));

        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            fn (PasswordResetCodeNotification $notification): bool => $notification->code === $resetCode->code
                && $notification->ttlMinutes === 5
        );
    }

    public function test_it_sends_the_reset_code_by_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();

        Notification::assertSentTo($user, PasswordResetCodeNotification::class, function (PasswordResetCodeNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->render(), $notification->code);
        });
    }

    public function test_it_returns_the_same_success_response_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertOk()->assertExactJson(['message' => self::SUCCESS_MESSAGE]);

        $this->assertDatabaseCount('password_reset_codes', 0);

        Notification::assertNothingSent();
    }

    public function test_it_returns_an_identical_response_regardless_of_whether_the_email_exists(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $knownResponse = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com']);
        $unknownResponse = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'unknown@example.com']);

        $this->assertSame($knownResponse->getStatusCode(), $unknownResponse->getStatusCode());
        $this->assertSame($knownResponse->json(), $unknownResponse->json());
    }

    public function test_it_only_creates_a_reset_code_for_a_known_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'unknown@example.com'])->assertOk();

        $this->assertDatabaseCount('password_reset_codes', 1);
        $this->assertDatabaseHas('password_reset_codes', ['email' => 'jane@example.com']);
        $this->assertDatabaseMissing('password_reset_codes', ['email' => 'unknown@example.com']);
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
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();
        $firstCode = PasswordResetCode::where('email', 'jane@example.com')->firstOrFail();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'jane@example.com'])->assertOk();

        $this->assertDatabaseCount('password_reset_codes', 1);
        $this->assertDatabaseMissing('password_reset_codes', ['id' => $firstCode->id]);
    }
}
