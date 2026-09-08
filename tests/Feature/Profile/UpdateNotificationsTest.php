<?php

namespace Tests\Feature\Profile;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_notification_settings_on_first_update(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/notifications', [
            'push_enabled' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('notification_settings.push_enabled', false)
            ->assertJsonPath('notification_settings.email_enabled', true)
            ->assertJsonPath('notification_settings.marketing_enabled', false);

        $this->assertDatabaseHas('notification_settings', [
            'user_id' => $user->id,
            'push_enabled' => false,
        ]);
    }

    public function test_it_partially_updates_existing_notification_settings(): void
    {
        $user = User::factory()->create();
        NotificationSetting::create([
            'user_id' => $user->id,
            'push_enabled' => true,
            'email_enabled' => true,
            'marketing_enabled' => false,
        ]);

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/notifications', [
            'marketing_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('notification_settings.push_enabled', true)
            ->assertJsonPath('notification_settings.marketing_enabled', true);
    }

    public function test_it_rejects_a_non_boolean_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->patchJson('/api/v1/profile/notifications', [
            'push_enabled' => 'not-a-boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->patchJson('/api/v1/profile/notifications', ['push_enabled' => false])
            ->assertStatus(401);
    }
}
