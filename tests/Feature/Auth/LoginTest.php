<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_in_a_user_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Secret1!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'Secret1!',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'created_at'], 'token']);
    }

    public function test_it_rejects_login_with_an_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'Secret1!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_it_rejects_login_with_an_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Secret1!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'WrongPass1!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_it_rejects_login_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.email.0', 'The email field is required.')
            ->assertJsonPath('error.fields.password.0', 'The password field is required.');
    }
}
