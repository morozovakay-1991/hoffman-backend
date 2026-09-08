<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_authenticated_users_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'timezone' => 'Europe/Moscow',
        ]);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('profile.id', $user->id)
            ->assertJsonPath('profile.name', 'Jane Doe')
            ->assertJsonPath('profile.email', 'jane@example.com')
            ->assertJsonPath('profile.timezone', 'Europe/Moscow');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_it_updates_the_name_and_timezone(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'timezone' => 'UTC']);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile', [
            'name' => 'Jane Smith',
            'timezone' => 'America/New_York',
        ]);

        $response->assertOk()
            ->assertJsonPath('profile.name', 'Jane Smith')
            ->assertJsonPath('profile.timezone', 'America/New_York');

        $this->assertSame('Jane Smith', $user->refresh()->name);
        $this->assertSame('America/New_York', $user->refresh()->timezone);
    }

    public function test_it_allows_a_partial_update_of_only_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'timezone' => 'UTC']);

        $this->actingAsApiUser($user)->patchJson('/api/v1/profile', [
            'name' => 'Jane Smith',
        ])->assertOk();

        $this->assertSame('Jane Smith', $user->refresh()->name);
        $this->assertSame('UTC', $user->refresh()->timezone);
    }

    public function test_it_rejects_an_invalid_timezone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile', [
            'timezone' => 'Not/A_Timezone',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
