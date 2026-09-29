<?php

namespace App\Domain\Home\Services;

use App\Domain\Content\Services\AccessLevelService;
use App\Domain\Content\Services\ContentCatalogService;
use App\Models\Article;
use App\Models\Meditation;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the home screen: per content type, the featured (headline) item plus
 * a handful of other accessible items, and a summary of the user's practice
 * diary progress.
 *
 * The featured item follows the catalogue's locked-but-visible rule (see
 * ContentCatalogService::getFeatured()): if the user can't open it, it's still
 * returned, flagged is_locked. The regular items, by contrast, are filtered
 * through AccessLevelService, so guests and non-subscribed users only see
 * items they're actually allowed to open. The featured item is never repeated
 * among the regular items.
 */
class HomeService
{
    private const ITEMS_PER_SECTION = 3;

    private const DIARY_TOTAL_DAYS = 100;

    public function __construct(
        private readonly AccessLevelService $accessLevelService,
        private readonly ContentCatalogService $contentCatalogService,
    ) {
    }

    /**
     * @return array{
     *     meditations: array{featured: Model|null, items: Collection<int, Model>},
     *     tools: array{featured: Model|null, items: Collection<int, Model>},
     *     topics: array{featured: Model|null, items: Collection<int, Model>},
     *     articles: array{featured: Model|null, items: Collection<int, Model>},
     *     diary_progress: array{current_day: int|null, total_days: int, is_available: bool},
     * }
     */
    public function getSummary(?User $user): array
    {
        return [
            'meditations' => $this->section(Meditation::class, AccessLevelService::CONTENT_MEDITATION, $user),
            'tools' => $this->section(Tool::class, AccessLevelService::CONTENT_TOOL, $user),
            'topics' => $this->section(Topic::class, AccessLevelService::CONTENT_TOPIC, $user),
            'articles' => $this->section(Article::class, AccessLevelService::CONTENT_ARTICLE, $user),
            'diary_progress' => $this->diaryProgress($user),
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     * @return array{featured: Model|null, items: Collection<int, Model>}
     */
    private function section(string $modelClass, string $contentType, ?User $user): array
    {
        return [
            'featured' => $this->contentCatalogService->getFeatured($modelClass, $contentType, $user),
            'items' => $this->accessibleItems($this->regularItemsQuery($modelClass)->get(), $user, $contentType),
        ];
    }

    /**
     * Published, non-featured items in display order: newest first for
     * articles (which have no sort_order), sort_order for everything else.
     * Articles are always accessible, so they can be limited in SQL; other
     * types are limited after access filtering in accessibleItems().
     *
     * @param class-string<Model> $modelClass
     * @return Builder<Model>
     */
    private function regularItemsQuery(string $modelClass): Builder
    {
        $query = $modelClass::query()
            ->where('is_published', true)
            ->where('is_featured', false);

        if ($modelClass === Article::class) {
            return $query->orderByDesc('published_at')->orderByDesc('id')->take(self::ITEMS_PER_SECTION);
        }

        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param Collection<int, Model> $items
     * @return Collection<int, Model>
     */
    private function accessibleItems(Collection $items, ?User $user, string $contentType): Collection
    {
        $accessible = $items
            ->filter(fn (Model $item) => $this->accessLevelService->canAccess($user, $contentType, $item))
            ->take(self::ITEMS_PER_SECTION)
            ->values();

        $accessible->each(fn (Model $item) => $item->setAttribute('is_locked', false));

        return $accessible;
    }

    /**
     * @return array{current_day: int|null, total_days: int, is_available: bool}
     */
    private function diaryProgress(?User $user): array
    {
        $isAvailable = $user !== null
            && $this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_DIARY, $user);

        return [
            'current_day' => $isAvailable ? min($user->diaryEntries()->count() + 1, self::DIARY_TOTAL_DAYS) : null,
            'total_days' => self::DIARY_TOTAL_DAYS,
            'is_available' => $isAvailable,
        ];
    }
}
