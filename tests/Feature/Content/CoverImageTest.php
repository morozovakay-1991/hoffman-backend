<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Article;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // In a real environment the bucket's public base URL comes from AWS_URL.
        Storage::fake('s3', ['url' => 'https://minio.local/hoffman']);
    }

    public function test_home_returns_cover_urls_for_every_content_type(): void
    {
        $user = $this->subscribedUser();
        Meditation::factory()->create(['cover_image_path' => 'meditations/covers/morning.jpg']);
        Tool::factory()->create(['cover_image_path' => 'tools/anger.jpg']);
        Topic::factory()->create(['cover_image_path' => 'topics/boundaries.jpg']);
        Article::factory()->create(['cover_image_path' => 'articles/hoffman.jpg']);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.meditations.items.0.cover_image_url', 'https://minio.local/hoffman/meditations/covers/morning.jpg')
            ->assertJsonPath('data.tools.items.0.cover_image_url', 'https://minio.local/hoffman/tools/anger.jpg')
            ->assertJsonPath('data.topics.items.0.cover_image_url', 'https://minio.local/hoffman/topics/boundaries.jpg')
            ->assertJsonPath('data.articles.items.0.cover_image_url', 'https://minio.local/hoffman/articles/hoffman.jpg')
            ->assertJsonPath('data.articles.items.0.cover_image_path', 'articles/hoffman.jpg');
    }

    public function test_cover_url_is_null_when_content_has_no_cover(): void
    {
        $user = $this->subscribedUser();
        Meditation::factory()->create();
        Tool::factory()->create();
        Topic::factory()->create();
        Article::factory()->create(['cover_image_path' => null]);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.meditations.items.0.cover_image_url', null)
            ->assertJsonPath('data.tools.items.0.cover_image_url', null)
            ->assertJsonPath('data.topics.items.0.cover_image_url', null)
            ->assertJsonPath('data.articles.items.0.cover_image_url', null);
    }

    public function test_detail_endpoints_return_the_cover_url(): void
    {
        $user = $this->subscribedUser();
        $meditation = Meditation::factory()->create(['cover_image_path' => 'meditations/covers/morning.jpg']);
        $tool = Tool::factory()->create(['cover_image_path' => 'tools/anger.jpg']);
        $topic = Topic::factory()->create(['cover_image_path' => 'topics/boundaries.jpg']);
        $article = Article::factory()->create(['cover_image_path' => 'articles/hoffman.jpg']);

        $this->actingAsApiUser($user)->getJson("/api/v1/meditations/{$meditation->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_image_url', 'https://minio.local/hoffman/meditations/covers/morning.jpg');

        $this->actingAsApiUser($user)->getJson("/api/v1/tools/{$tool->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_image_url', 'https://minio.local/hoffman/tools/anger.jpg');

        $this->actingAsApiUser($user)->getJson("/api/v1/topics/{$topic->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_image_url', 'https://minio.local/hoffman/topics/boundaries.jpg');

        $this->getJson("/api/v1/articles/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_image_url', 'https://minio.local/hoffman/articles/hoffman.jpg');
    }

    public function test_cover_url_is_permanent_and_not_signed(): void
    {
        $meditation = Meditation::factory()->free()->create(['cover_image_path' => 'meditations/covers/morning.jpg']);

        $first = $this->getJson("/api/v1/meditations/{$meditation->id}")->json('data.cover_image_url');
        $this->travel(2)->hours();
        $second = $this->getJson("/api/v1/meditations/{$meditation->id}")->json('data.cover_image_url');

        $this->assertSame('https://minio.local/hoffman/meditations/covers/morning.jpg', $first);
        $this->assertSame($first, $second);
    }

    public function test_catalogue_lists_expose_the_cover_url_for_locked_and_unlocked_items(): void
    {
        Meditation::factory()->create(['cover_image_path' => 'meditations/covers/paid.jpg']);
        Tool::factory()->create(['cover_image_path' => 'tools/anger.jpg']);
        Topic::factory()->create(['cover_image_path' => 'topics/boundaries.jpg']);

        $this->getJson('/api/v1/meditations')
            ->assertOk()
            ->assertJsonPath('data.items.0.is_locked', true)
            ->assertJsonPath('data.items.0.cover_image_url', 'https://minio.local/hoffman/meditations/covers/paid.jpg');

        $this->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonPath('data.items.0.is_locked', false)
            ->assertJsonPath('data.items.0.cover_image_url', 'https://minio.local/hoffman/tools/anger.jpg');

        $this->getJson('/api/v1/topics')
            ->assertOk()
            ->assertJsonPath('data.items.0.is_locked', false)
            ->assertJsonPath('data.items.0.cover_image_url', 'https://minio.local/hoffman/topics/boundaries.jpg');
    }

    private function subscribedUser(): User
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);

        return $user;
    }
}
