<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Subscription;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_topics_locked_in_the_list(): void
    {
        $topic = Topic::factory()->create();

        $response = $this->getJson('/api/v1/topics');

        $response->assertOk();

        $items = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($items[$topic->id]['is_locked']);
        $this->assertNull($items[$topic->id]['full_description']);
    }

    public function test_guest_is_denied_a_topic(): void
    {
        $topic = Topic::factory()->create();

        $response = $this->getJson("/api/v1/topics/{$topic->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_user_without_subscription_is_denied_a_topic(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/topics/{$topic->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_subscribed_non_confirmed_graduate_can_view_a_topic(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $topic = Topic::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/topics/{$topic->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.full_description', $topic->full_description);
    }

    public function test_confirmed_graduate_with_subscription_can_view_a_topic(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $topic = Topic::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/topics/{$topic->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_unpublished_topic_is_not_found(): void
    {
        $topic = Topic::factory()->unpublished()->create();

        $response = $this->getJson("/api/v1/topics/{$topic->id}");

        $response->assertStatus(404);
    }
}
