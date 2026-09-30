<?php

namespace Tests\Feature\Home;

use App\Enums\GraduateStatus;
use App\Models\Article;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_has_the_expected_structure(): void
    {
        Meditation::factory()->free()->create();
        Tool::factory()->create();
        Topic::factory()->create();
        Article::factory()->create();

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'meditations' => [
                    'featured',
                    'items' => [
                        '*' => ['id', 'title', 'short_description', 'cover_image_url', 'is_locked'],
                    ],
                ],
                'tools' => [
                    'featured',
                    'items' => [
                        '*' => ['id', 'title', 'short_description', 'cover_image_url', 'is_locked'],
                    ],
                ],
                'topics' => [
                    'featured',
                    'items' => [
                        '*' => ['id', 'title', 'subtitle', 'cover_image_url', 'is_locked'],
                    ],
                ],
                'articles' => [
                    'featured',
                    'items' => [
                        '*' => ['id', 'title', 'short_description', 'cover_image_url', 'is_locked'],
                    ],
                ],
                'diary_progress' => ['current_day', 'total_days', 'is_available'],
            ],
        ]);
    }

    public function test_guest_only_sees_accessible_content_and_no_diary_progress(): void
    {
        $freeMeditation = Meditation::factory()->free()->create();
        Meditation::factory()->create(); // paid, inaccessible to a guest
        Article::factory()->create();
        $tool = Tool::factory()->create(); // tools and topics are always accessible
        $topic = Topic::factory()->create();

        $response = $this->getJson('/api/v1/home');
        $response->assertOk();

        $meditationIds = collect($response->json('data.meditations.items'))->pluck('id');

        $this->assertTrue($meditationIds->contains($freeMeditation->id));
        $this->assertCount(1, $meditationIds);
        $this->assertCount(1, $response->json('data.articles.items'));
        $this->assertSame([$tool->id], collect($response->json('data.tools.items'))->pluck('id')->all());
        $this->assertSame([$topic->id], collect($response->json('data.topics.items'))->pluck('id')->all());
        $response->assertJsonPath('data.tools.items.0.is_locked', false)
            ->assertJsonPath('data.topics.items.0.is_locked', false);

        $response->assertJsonPath('data.diary_progress.is_available', false)
            ->assertJsonPath('data.diary_progress.current_day', null)
            ->assertJsonPath('data.diary_progress.total_days', 100);
    }

    public function test_subscribed_non_confirmed_graduate_sees_paid_content_but_no_diary_progress(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $paidMeditation = Meditation::factory()->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');
        $response->assertOk();

        $meditationIds = collect($response->json('data.meditations.items'))->pluck('id');
        $this->assertTrue($meditationIds->contains($paidMeditation->id));

        $response->assertJsonPath('data.diary_progress.is_available', false);
    }

    public function test_confirmed_graduate_with_subscription_sees_diary_progress(): void
    {
        for ($dayNumber = 1; $dayNumber <= 100; $dayNumber++) {
            DiaryDay::factory()->create(['day_number' => $dayNumber]);
        }

        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);

        $firstDay = DiaryDay::query()->where('day_number', 1)->firstOrFail();
        DiaryEntry::create([
            'user_id' => $user->id,
            'diary_day_id' => $firstDay->id,
            'answer_text' => 'Done',
            'completed_date' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.diary_progress.is_available', true)
            ->assertJsonPath('data.diary_progress.current_day', 2)
            ->assertJsonPath('data.diary_progress.total_days', 100);
    }

    public function test_each_section_is_limited_to_three_items(): void
    {
        Meditation::factory()->free()->count(7)->create();
        Article::factory()->count(7)->create();

        $response = $this->getJson('/api/v1/home');

        $response->assertOk();
        $this->assertCount(3, $response->json('data.meditations.items'));
        $this->assertCount(3, $response->json('data.articles.items'));
    }

    public function test_featured_item_is_returned_separately_and_not_repeated_in_items(): void
    {
        $user = $this->subscribedUser();
        $featured = [
            'meditations' => Meditation::factory()->featured()->create(['sort_order' => 0]),
            'tools' => Tool::factory()->featured()->create(['sort_order' => 0]),
            'topics' => Topic::factory()->featured()->create(['sort_order' => 0]),
            'articles' => Article::factory()->featured()->create(['published_at' => now()->addDay()]),
        ];
        Meditation::factory()->count(4)->create(['sort_order' => 10]);
        Tool::factory()->count(4)->create(['sort_order' => 10]);
        Topic::factory()->count(4)->create(['sort_order' => 10]);
        Article::factory()->count(4)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');
        $response->assertOk();

        foreach ($featured as $section => $item) {
            $response->assertJsonPath("data.{$section}.featured.id", $item->id);

            $itemIds = collect($response->json("data.{$section}.items"))->pluck('id');
            $this->assertCount(3, $itemIds, "{$section} should still have 3 regular items");
            $this->assertNotContains($item->id, $itemIds, "{$section} featured item is repeated in items");
        }
    }

    public function test_featured_is_null_when_none_is_assigned(): void
    {
        Meditation::factory()->free()->create();
        Article::factory()->create();

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.meditations.featured', null)
            ->assertJsonPath('data.tools.featured', null)
            ->assertJsonPath('data.topics.featured', null)
            ->assertJsonPath('data.articles.featured', null);
    }

    public function test_items_are_ordered_by_sort_order(): void
    {
        $user = $this->subscribedUser();

        foreach ([Meditation::class, Tool::class, Topic::class] as $modelClass) {
            // Created out of order so that ordering by id (asc or desc) would fail.
            $second = $modelClass::factory()->create(['sort_order' => 10]);
            $fourth = $modelClass::factory()->create(['sort_order' => 30]);
            $first = $modelClass::factory()->create(['sort_order' => 0]);
            $third = $modelClass::factory()->create(['sort_order' => 20]);
            $modelClass::factory()->featured()->create(['sort_order' => -1]);

            $expected[$modelClass] = [$first->id, $second->id, $third->id];
        }

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');
        $response->assertOk();

        $this->assertSame($expected[Meditation::class], collect($response->json('data.meditations.items'))->pluck('id')->all());
        $this->assertSame($expected[Tool::class], collect($response->json('data.tools.items'))->pluck('id')->all());
        $this->assertSame($expected[Topic::class], collect($response->json('data.topics.items'))->pluck('id')->all());
    }

    public function test_article_items_are_ordered_by_newest_published_at(): void
    {
        $older = Article::factory()->create(['published_at' => now()->subDays(3)]);
        $newest = Article::factory()->create(['published_at' => now()->subDay()]);
        Article::factory()->create(['published_at' => now()->subDays(10)]);
        $middle = Article::factory()->create(['published_at' => now()->subDays(2)]);
        Article::factory()->featured()->create(['published_at' => now()]);

        $response = $this->getJson('/api/v1/home');

        $this->assertSame(
            [$newest->id, $middle->id, $older->id],
            collect($response->json('data.articles.items'))->pluck('id')->all(),
        );
    }

    public function test_inaccessible_featured_meditation_is_returned_locked_for_a_guest_while_tool_and_topic_are_unlocked(): void
    {
        $meditation = Meditation::factory()->featured()->create(); // paid
        $tool = Tool::factory()->featured()->create();
        $topic = Topic::factory()->featured()->create();
        $freeMeditation = Meditation::factory()->free()->create();

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.meditations.featured.id', $meditation->id)
            ->assertJsonPath('data.meditations.featured.is_locked', true)
            ->assertJsonPath('data.meditations.featured.audio_path', null)
            ->assertJsonPath('data.meditations.featured.full_description', null)
            ->assertJsonPath('data.tools.featured.id', $tool->id)
            ->assertJsonPath('data.tools.featured.is_locked', false)
            ->assertJsonPath('data.tools.featured.full_description', $tool->full_description)
            ->assertJsonPath('data.topics.featured.id', $topic->id)
            ->assertJsonPath('data.topics.featured.is_locked', false)
            ->assertJsonPath('data.topics.featured.full_description', $topic->full_description);

        // Regular items remain accessible-only.
        $this->assertSame([$freeMeditation->id], collect($response->json('data.meditations.items'))->pluck('id')->all());
    }

    public function test_accessible_featured_item_is_unlocked(): void
    {
        $meditation = Meditation::factory()->featured()->create();

        $this->actingAsApiUser($this->subscribedUser())->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.meditations.featured.is_locked', false)
            ->assertJsonPath('data.meditations.featured.audio_path', $meditation->audio_path);
    }

    private function subscribedUser(): User
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);

        return $user;
    }
}
