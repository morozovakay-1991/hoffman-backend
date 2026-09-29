<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Article;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeaturedContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>, string}>
     */
    public static function catalogues(): array
    {
        return [
            'meditations' => [Meditation::class, '/api/v1/meditations'],
            'tools' => [Tool::class, '/api/v1/tools'],
            'topics' => [Topic::class, '/api/v1/topics'],
            'articles' => [Article::class, '/api/v1/articles'],
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     */
    #[DataProvider('catalogues')]
    public function test_featured_item_is_returned_separately_and_not_repeated_in_items(string $modelClass, string $url): void
    {
        $others = $modelClass::factory()->count(4)->create();
        $featured = $modelClass::factory()->featured()->create();

        $response = $this->actingAsApiUser($this->subscribedUser())->getJson($url);

        $response->assertOk()->assertJsonPath('data.featured.id', $featured->id);

        $itemIds = collect($response->json('data.items'))->pluck('id');
        $this->assertNotContains($featured->id, $itemIds);
        $this->assertEqualsCanonicalizing($others->pluck('id')->all(), $itemIds->all());
    }

    /**
     * @param class-string<Model> $modelClass
     */
    #[DataProvider('catalogues')]
    public function test_items_are_not_limited_in_the_full_list(string $modelClass, string $url): void
    {
        $modelClass::factory()->count(8)->create();
        $modelClass::factory()->featured()->create();

        $response = $this->actingAsApiUser($this->subscribedUser())->getJson($url);

        $response->assertOk();
        $this->assertCount(8, $response->json('data.items'));
    }

    /**
     * @param class-string<Model> $modelClass
     */
    #[DataProvider('catalogues')]
    public function test_featured_is_null_when_none_is_assigned(string $modelClass, string $url): void
    {
        $modelClass::factory()->count(2)->create();

        $response = $this->actingAsApiUser($this->subscribedUser())->getJson($url);

        $response->assertOk()->assertJsonPath('data.featured', null);
        $this->assertCount(2, $response->json('data.items'));
    }

    /**
     * @param class-string<Model> $modelClass
     */
    #[DataProvider('catalogues')]
    public function test_unpublished_featured_item_is_neither_featured_nor_listed(string $modelClass, string $url): void
    {
        $featured = $modelClass::factory()->featured()->unpublished()->create();
        $modelClass::factory()->create();

        $response = $this->actingAsApiUser($this->subscribedUser())->getJson($url);

        $response->assertOk()->assertJsonPath('data.featured', null);
        $this->assertNotContains($featured->id, collect($response->json('data.items'))->pluck('id'));
    }

    public function test_items_stay_ordered_by_sort_order_after_excluding_the_featured_one(): void
    {
        $third = Meditation::factory()->create(['sort_order' => 20]);
        Meditation::factory()->featured()->create(['sort_order' => 5]);
        $first = Meditation::factory()->create(['sort_order' => 0]);
        $second = Meditation::factory()->create(['sort_order' => 10]);

        $response = $this->actingAsApiUser($this->subscribedUser())->getJson('/api/v1/meditations');

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_inaccessible_featured_meditation_is_returned_locked_for_a_guest(): void
    {
        $featured = Meditation::factory()->featured()->create(); // paid
        Meditation::factory()->free()->create();

        $response = $this->getJson('/api/v1/meditations');

        $response->assertOk()
            ->assertJsonPath('data.featured.id', $featured->id)
            ->assertJsonPath('data.featured.is_locked', true)
            ->assertJsonPath('data.featured.audio_path', null)
            ->assertJsonPath('data.featured.full_description', null);
    }

    public function test_free_featured_meditation_is_unlocked_for_a_guest(): void
    {
        $featured = Meditation::factory()->free()->featured()->create();

        $this->getJson('/api/v1/meditations')
            ->assertOk()
            ->assertJsonPath('data.featured.is_locked', false)
            ->assertJsonPath('data.featured.audio_path', $featured->audio_path);
    }

    public function test_inaccessible_featured_tool_and_topic_are_returned_locked_for_a_user_without_subscription(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->featured()->create();
        $topic = Topic::factory()->featured()->create();

        $this->actingAsApiUser($user)->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonPath('data.featured.id', $tool->id)
            ->assertJsonPath('data.featured.is_locked', true)
            ->assertJsonPath('data.featured.full_description', null);

        $this->actingAsApiUser($user)->getJson('/api/v1/topics')
            ->assertOk()
            ->assertJsonPath('data.featured.id', $topic->id)
            ->assertJsonPath('data.featured.is_locked', true)
            ->assertJsonPath('data.featured.full_description', null);
    }

    public function test_featured_items_are_unlocked_for_a_subscriber(): void
    {
        $user = $this->subscribedUser();
        Meditation::factory()->featured()->create();
        Tool::factory()->featured()->create();
        Topic::factory()->featured()->create();

        foreach (['/api/v1/meditations', '/api/v1/tools', '/api/v1/topics'] as $url) {
            $this->actingAsApiUser($user)->getJson($url)
                ->assertOk()
                ->assertJsonPath('data.featured.is_locked', false);
        }
    }

    private function subscribedUser(): User
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);

        return $user;
    }
}
