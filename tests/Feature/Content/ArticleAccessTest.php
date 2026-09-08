<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Article;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_an_article(): void
    {
        $article = Article::factory()->create();

        $response = $this->getJson("/api/v1/articles/{$article->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.full_description', $article->full_description);
    }

    public function test_user_without_subscription_can_view_an_article(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/articles/{$article->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_subscribed_non_confirmed_graduate_can_view_an_article(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $article = Article::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/articles/{$article->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_confirmed_graduate_with_subscription_can_view_an_article(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $article = Article::factory()->create();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/articles/{$article->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    }

    public function test_article_list_is_never_locked(): void
    {
        Article::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/articles');

        $response->assertOk();
        collect($response->json('data'))->each(
            fn (array $article) => $this->assertFalse($article['is_locked']),
        );
    }

    public function test_unpublished_article_is_not_found(): void
    {
        $article = Article::factory()->unpublished()->create();

        $response = $this->getJson("/api/v1/articles/{$article->id}");

        $response->assertStatus(404);
    }
}
