<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_password_when_the_old_password_is_correct(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldSecret1!')]);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/password', [
            'old_password' => 'OldSecret1!',
            'password' => 'NewSecret1!',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('NewSecret1!', $user->refresh()->password));
    }

    public function test_it_rejects_an_incorrect_old_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldSecret1!')]);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/password', [
            'old_password' => 'WrongPassword1!',
            'password' => 'NewSecret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_OLD_PASSWORD');

        $this->assertTrue(Hash::check('OldSecret1!', $user->refresh()->password));
    }

    public function test_it_rejects_a_new_password_with_an_invalid_format(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldSecret1!')]);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/password', [
            'old_password' => 'OldSecret1!',
            'password' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->patchJson('/api/v1/profile/password', [
            'old_password' => 'OldSecret1!',
            'password' => 'NewSecret1!',
        ])->assertStatus(401);
    }
}
