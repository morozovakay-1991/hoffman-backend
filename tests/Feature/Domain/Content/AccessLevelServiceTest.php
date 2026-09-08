<?php

namespace Tests\Feature\Domain\Content;

use App\Domain\Content\Services\AccessLevelService;
use App\Enums\GraduateStatus;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLevelServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccessLevelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AccessLevelService();
    }

    public function test_guest_can_access_only_free_meditations_and_articles(): void
    {
        $free = Meditation::factory()->free()->make();
        $paid = Meditation::factory()->make();

        $this->assertTrue($this->service->canAccess(null, AccessLevelService::CONTENT_MEDITATION, $free));
        $this->assertFalse($this->service->canAccess(null, AccessLevelService::CONTENT_MEDITATION, $paid));
        $this->assertTrue($this->service->canAccess(null, AccessLevelService::CONTENT_ARTICLE, $paid));
        $this->assertFalse($this->service->canAccess(null, AccessLevelService::CONTENT_TOOL, $paid));
        $this->assertFalse($this->service->canAccess(null, AccessLevelService::CONTENT_TOPIC, $paid));
        $this->assertFalse($this->service->canAccess(null, AccessLevelService::CONTENT_DIARY, $paid));
    }

    public function test_user_without_an_active_subscription_is_treated_like_a_guest(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->expired()->create();
        $paid = Meditation::factory()->make();

        $this->assertFalse($this->service->canAccess($user, AccessLevelService::CONTENT_MEDITATION, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_ARTICLE, $paid));
    }

    public function test_subscribed_non_confirmed_graduate_gets_full_content_but_not_diary(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $paid = Meditation::factory()->make();

        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_MEDITATION, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_TOOL, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_TOPIC, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_ARTICLE, $paid));
        $this->assertFalse($this->service->canAccess($user, AccessLevelService::CONTENT_DIARY, $paid));
    }

    public function test_confirmed_graduate_with_subscription_gets_everything_including_diary(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $paid = Meditation::factory()->make();

        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_MEDITATION, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_TOOL, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_TOPIC, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_ARTICLE, $paid));
        $this->assertTrue($this->service->canAccess($user, AccessLevelService::CONTENT_DIARY, $paid));
    }
}
