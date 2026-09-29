<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\MeditationResource\Pages\EditMeditation;
use App\Filament\Resources\ToolResource\Pages\EditTool;
use App\Filament\Resources\TopicResource\Pages\EditTopic;
use App\Models\Article;
use App\Models\Meditation;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeaturedContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The meditation edit form validates that its audio file exists on s3.
        Storage::fake('s3');
    }

    /**
     * @return array<string, array{class-string<Model>, class-string}>
     */
    public static function contentTypes(): array
    {
        return [
            'meditation' => [Meditation::class, EditMeditation::class],
            'tool' => [Tool::class, EditTool::class],
            'topic' => [Topic::class, EditTopic::class],
            'article' => [Article::class, EditArticle::class],
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     * @param class-string $editPage
     */
    #[DataProvider('contentTypes')]
    public function test_featuring_a_record_in_the_panel_unfeatures_the_previous_one(string $modelClass, string $editPage): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$first, $second, $third] = $this->createRecords($modelClass, 3);

        $this->featureInPanel($editPage, $first);
        $this->assertFeaturedIs($modelClass, $first);

        $this->featureInPanel($editPage, $second);
        $this->assertFeaturedIs($modelClass, $second);

        $this->featureInPanel($editPage, $third);
        $this->assertFeaturedIs($modelClass, $third);

        // Re-featuring an earlier record, and re-saving the current one, still leaves exactly one.
        $this->featureInPanel($editPage, $first);
        $this->featureInPanel($editPage, $first);
        $this->assertFeaturedIs($modelClass, $first);
    }

    /**
     * @param class-string<Model> $modelClass
     * @param class-string $editPage
     */
    #[DataProvider('contentTypes')]
    public function test_unfeaturing_in_the_panel_leaves_no_featured_record(string $modelClass, string $editPage): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$record] = $this->createRecords($modelClass, 1, ['is_featured' => true]);

        Livewire::test($editPage, ['record' => $record->getRouteKey()])
            ->fillForm(['is_featured' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $modelClass::query()->where('is_featured', true)->count());
    }

    /**
     * @param class-string<Model> $modelClass
     */
    #[DataProvider('contentTypes')]
    public function test_exclusivity_is_enforced_on_any_save_not_only_through_the_panel(string $modelClass): void
    {
        [$first, $second] = $this->createRecords($modelClass, 2);

        $first->update(['is_featured' => true]);
        $this->assertFeaturedIs($modelClass, $first);

        [$created] = $this->createRecords($modelClass, 1, ['is_featured' => true]);
        $this->assertFeaturedIs($modelClass, $created);

        $second->is_featured = true;
        $second->save();
        $this->assertFeaturedIs($modelClass, $second);
    }

    public function test_featuring_one_content_type_does_not_affect_other_types(): void
    {
        $meditation = Meditation::factory()->featured()->create();
        $tool = Tool::factory()->featured()->create();
        $topic = Topic::factory()->featured()->create();
        $article = Article::factory()->featured()->create();

        Tool::factory()->featured()->create();

        $this->assertTrue($meditation->refresh()->is_featured);
        $this->assertFalse($tool->refresh()->is_featured);
        $this->assertTrue($topic->refresh()->is_featured);
        $this->assertTrue($article->refresh()->is_featured);
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $attributes
     * @return list<Model>
     */
    private function createRecords(string $modelClass, int $count, array $attributes = []): array
    {
        $records = [];

        for ($i = 0; $i < $count; $i++) {
            $record = $modelClass::factory()->create($attributes);

            if ($record instanceof Meditation) {
                Storage::disk('s3')->put($record->audio_path, 'fake-audio-content');
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param class-string $editPage
     */
    private function featureInPanel(string $editPage, Model $record): void
    {
        Livewire::test($editPage, ['record' => $record->getRouteKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function assertFeaturedIs(string $modelClass, Model $expected): void
    {
        $this->assertSame(
            [$expected->getKey()],
            $modelClass::query()->where('is_featured', true)->pluck('id')->all(),
        );
    }
}
