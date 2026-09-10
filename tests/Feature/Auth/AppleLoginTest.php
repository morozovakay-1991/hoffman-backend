<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;
use Tests\TestCase;

class AppleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mockAppleProvider(string $token, string $id, ?string $email, ?string $name = null): void
    {
        $socialiteUser = \Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);

        $provider = \Mockery::mock(AppleProvider::class);
        $provider->shouldReceive('userByIdentityToken')->once()->with($token)->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with('apple')->andReturn($provider);
    }

    public function test_it_creates_a_new_user_on_first_apple_login(): void
    {
        // Apple only includes the user's name in the very first authorization
        // response, never in the identity token itself, so it is typically absent.
        $this->mockAppleProvider('valid-token', 'apple-123', 'newuser@example.com', null);

        $response = $this->postJson('/api/v1/auth/apple', ['token' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.email', 'newuser@example.com')
            ->assertJsonPath('user.name', 'newuser')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'created_at'], 'token']);

        $user = User::where('email', 'newuser@example.com')->firstOrFail();

        $this->assertSame('apple-123', $user->apple_id);
        $this->assertNull($user->password);
    }

    public function test_it_logs_in_an_existing_user_linked_to_apple(): void
    {
        $user = User::factory()->withApple()->create([
            'email' => 'jane@example.com',
            'apple_id' => 'apple-999',
        ]);

        $this->mockAppleProvider('valid-token', 'apple-999', 'jane@example.com');

        $response = $this->postJson('/api/v1/auth/apple', ['token' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_apple_login_when_email_is_already_used_by_another_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->mockAppleProvider('valid-token', 'apple-321', 'taken@example.com');

        $response = $this->postJson('/api/v1/auth/apple', ['token' => 'valid-token']);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SOCIAL_EMAIL_CONFLICT');

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_an_invalid_or_expired_apple_token(): void
    {
        $provider = \Mockery::mock(AppleProvider::class);
        $provider->shouldReceive('userByIdentityToken')->once()->andThrow(new \RuntimeException('invalid token'));

        Socialite::shouldReceive('driver')->once()->with('apple')->andReturn($provider);

        $response = $this->postJson('/api/v1/auth/apple', ['token' => 'bad-token']);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_PROVIDER_TOKEN');

        $this->assertSame(0, User::count());
    }

    public function test_it_rejects_apple_login_with_missing_token(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
