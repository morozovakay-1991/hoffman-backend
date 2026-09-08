<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Subscription;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_tools_locked_in_the_list(): void
    {
        $tool = Tool::factory()->create();

        $response = $this->getJson('/api/v1/tools');

        $response->assertOk();

        $items = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($items[$tool->id]['is_locked']);
        $this->assertNull($items[$tool->id]['full_description']);
    }

    public function test_guest_is_denied_a_tool(): void
    {
        $tool = Tool::factory()->create();

        $response = $this->getJson("/api/v1/tools/{$tool->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_user_without_subscription_is_denied_a_tool(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/tools/{$tool->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_subscribed_non_confirmed_graduate_can_view_a_tool(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $tool = Tool::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/tools/{$tool->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.full_description', $tool->full_description);
    }

    public function test_confirmed_graduate_with_subscription_can_view_a_tool(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $tool = Tool::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/tools/{$tool->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_unpublished_tool_is_not_found(): void
    {
        $tool = Tool::factory()->unpublished()->create();

        $response = $this->getJson("/api/v1/tools/{$tool->id}");

        $response->assertStatus(404);
    }
}
