<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleProvider(string $token, string $id, ?string $email, ?string $name): void
    {
        $socialiteUser = \Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);

        $provider = \Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('userFromToken')->once()->with($token)->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    public function test_it_creates_a_new_user_on_first_google_login(): void
    {
        $this->mockGoogleProvider('valid-token', 'google-123', 'newuser@example.com', 'New User');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.email', 'newuser@example.com')
            ->assertJsonPath('user.name', 'New User')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'created_at'], 'token']);

        $user = User::where('email', 'newuser@example.com')->firstOrFail();

        $this->assertSame('google-123', $user->google_id);
        $this->assertNull($user->password);
    }

    public function test_it_logs_in_an_existing_user_linked_to_google(): void
    {
        $user = User::factory()->withGoogle()->create([
            'email' => 'jane@example.com',
            'google_id' => 'google-999',
        ]);

        $this->mockGoogleProvider('valid-token', 'google-999', 'jane@example.com', 'Jane Doe');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_google_login_when_email_is_already_used_by_another_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->mockGoogleProvider('valid-token', 'google-321', 'taken@example.com', 'Someone Else');

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'valid-token']);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SOCIAL_EMAIL_CONFLICT');

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_an_invalid_or_expired_google_token(): void
    {
        $provider = \Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('userFromToken')->once()->andThrow(new \RuntimeException('invalid token'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->postJson('/api/v1/auth/google', ['token' => 'bad-token']);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_PROVIDER_TOKEN');

        $this->assertSame(0, User::count());
    }

    public function test_it_rejects_google_login_with_missing_token(): void
    {
        $response = $this->postJson('/api/v1/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
