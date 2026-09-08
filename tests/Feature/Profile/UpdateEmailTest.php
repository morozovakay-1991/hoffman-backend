<?php

namespace Tests\Feature\Profile;

use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_email_change_request_without_updating_the_email_yet(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/email', [
            'new_email' => 'jane.new@example.com',
        ]);

        $response->assertOk();

        $this->assertSame('jane@example.com', $user->refresh()->email);
        $this->assertDatabaseHas('email_change_requests', [
            'user_id' => $user->id,
            'old_email' => 'jane@example.com',
            'new_email' => 'jane.new@example.com',
            'confirmed_at' => null,
        ]);
    }

    public function test_it_rejects_a_new_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/email', [
            'new_email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'EMAIL_TAKEN');
    }

    public function test_it_confirms_the_email_change_and_updates_the_email(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->actingAsApiUser($user)->patchJson('/api/v1/profile/email', [
            'new_email' => 'jane.new@example.com',
        ])->assertOk();

        $code = EmailChangeRequest::where('user_id', $user->id)->firstOrFail()->code;

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/email/confirm', [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonPath('profile.email', 'jane.new@example.com');

        $this->assertSame('jane.new@example.com', $user->refresh()->email);
    }

    public function test_it_rejects_confirmation_with_an_invalid_code(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->actingAsApiUser($user)->patchJson('/api/v1/profile/email', [
            'new_email' => 'jane.new@example.com',
        ])->assertOk();

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/email/confirm', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');

        $this->assertSame('jane@example.com', $user->refresh()->email);
    }

    public function test_it_rejects_confirmation_when_no_change_was_requested(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/email/confirm', [
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CODE');
    }

    public function test_it_rejects_confirmation_with_an_expired_code(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        EmailChangeRequest::create([
            'user_id' => $user->id,
            'old_email' => 'jane@example.com',
            'new_email' => 'jane.new@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
            'confirmed_at' => null,
        ]);

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/email/confirm', [
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CODE_EXPIRED');
    }

    public function test_it_rejects_confirmation_after_reaching_the_maximum_number_of_attempts(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        EmailChangeRequest::create([
            'user_id' => $user->id,
            'old_email' => 'jane@example.com',
            'new_email' => 'jane.new@example.com',
            'code' => '123456',
            'attempts' => 5,
            'expires_at' => now()->addMinutes(15),
            'confirmed_at' => null,
        ]);

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/email/confirm', [
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->patchJson('/api/v1/profile/email', ['new_email' => 'new@example.com'])
            ->assertStatus(401);
    }
}
