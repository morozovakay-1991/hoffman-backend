<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_new_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secret1!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonPath('user.name', 'Jane Doe')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'created_at'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Secret1!', $user->password));
    }

    public function test_it_rejects_registration_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.name.0', 'The name field is required.')
            ->assertJsonPath('error.fields.email.0', 'The email field is required.')
            ->assertJsonPath('error.fields.password.0', 'The password field is required.');
    }

    public function test_it_rejects_registration_with_a_password_missing_a_digit(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secretary!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.password.0', 'The password field format is invalid.');
    }

    public function test_it_rejects_registration_with_a_password_missing_a_special_character(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.password.0', 'The password field format is invalid.');
    }

    public function test_it_rejects_registration_with_a_password_shorter_than_eight_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Sec1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_it_rejects_registration_with_an_already_taken_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secret1!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'EMAIL_TAKEN')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields']]);
    }
}
