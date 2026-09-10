<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeditationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_meditations_with_locked_flag_on_paid_ones(): void
    {
        $free = Meditation::factory()->free()->create();
        $paid = Meditation::factory()->create();

        $response = $this->getJson('/api/v1/meditations');
        $response->assertOk();

        $items = collect($response->json('data'))->keyBy('id');

        $this->assertFalse($items[$free->id]['is_locked']);
        $this->assertTrue($items[$paid->id]['is_locked']);
        $this->assertNull($items[$paid->id]['audio_path']);
    }

    public function test_guest_can_view_a_free_meditation(): void
    {
        $meditation = Meditation::factory()->free()->create();

        $response = $this->getJson("/api/v1/meditations/{$meditation->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.audio_path', $meditation->audio_path);
    }

    public function test_guest_is_denied_a_paid_meditation(): void
    {
        $meditation = Meditation::factory()->create();

        $response = $this->getJson("/api/v1/meditations/{$meditation->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_user_without_subscription_is_denied_a_paid_meditation(): void
    {
        $user = User::factory()->create();
        $meditation = Meditation::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/meditations/{$meditation->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_subscribed_non_confirmed_graduate_can_view_a_paid_meditation(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $meditation = Meditation::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/meditations/{$meditation->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.audio_path', $meditation->audio_path);
    }

    public function test_confirmed_graduate_with_subscription_can_view_a_paid_meditation(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $meditation = Meditation::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/meditations/{$meditation->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_meditation_list_is_ordered_by_sort_order_not_creation_order(): void
    {
        $third = Meditation::factory()->create(['sort_order' => 20]);
        $first = Meditation::factory()->create(['sort_order' => 0]);
        $second = Meditation::factory()->create(['sort_order' => 10]);

        $response = $this->getJson('/api/v1/meditations');

        $response->assertOk();

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_unpublished_meditation_is_not_found(): void
    {
        $meditation = Meditation::factory()->free()->unpublished()->create();

        $response = $this->getJson("/api/v1/meditations/{$meditation->id}");

        $response->assertStatus(404);
    }
}
