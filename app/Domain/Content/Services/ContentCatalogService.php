<?php

namespace App\Domain\Content\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the "list published items" query shared by every content catalogue
 * endpoint (articles, meditations, tools, topics), so controllers stay thin:
 * call in, wrap the result in a Resource collection, return it.
 *
 * Each content type may have one featured (headline) item, returned separately
 * by getFeatured(). The list methods exclude it, so it never appears twice.
 */
class ContentCatalogService
{
    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * Published, non-featured items ordered by id, with no locking (used by
     * Article, which is always accessible and has no sort_order column).
     *
     * @param class-string<Model> $modelClass
     * @param list<string> $with
     * @return Collection<int, Model>
     */
    public function listPublished(string $modelClass, array $with = []): Collection
    {
        return $modelClass::query()
            ->where('is_published', true)
            ->where('is_featured', false)
            ->with($with)
            ->orderBy('id')
            ->get();
    }

    /**
     * Published, non-featured items ordered by sort_order, each flagged with
     * is_locked per AccessLevelService::canAccess() for $contentType (one of its
     * AccessLevelService::CONTENT_* constants). This is the "locked-but-visible"
     * list shape used by Meditation, Tool and Topic: the full published set is
     * returned rather than filtered, with inaccessible items merely flagged.
     *
     * @param class-string<Model> $modelClass
     * @param list<string> $with
     * @return Collection<int, Model>
     */
    public function listPublishedWithLockFlag(string $modelClass, string $contentType, ?User $user, array $with = []): Collection
    {
        $items = $modelClass::query()
            ->where('is_published', true)
            ->where('is_featured', false)
            ->with($with)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items->each(fn (Model $item) => $this->flagLocked($item, $contentType, $user));

        return $items;
    }

    /**
     * The published featured item of $modelClass, or null if none is assigned
     * (or the featured one is unpublished). It follows the same access rules as
     * list items: an inaccessible featured item is returned flagged is_locked,
     * never hidden and never unlocked.
     *
     * @param class-string<Model> $modelClass
     * @param list<string> $with
     */
    public function getFeatured(string $modelClass, string $contentType, ?User $user, array $with = []): ?Model
    {
        $featured = $modelClass::query()
            ->where('is_published', true)
            ->where('is_featured', true)
            ->with($with)
            ->first();

        if ($featured !== null) {
            $this->flagLocked($featured, $contentType, $user);
        }

        return $featured;
    }

    private function flagLocked(Model $item, string $contentType, ?User $user): void
    {
        $item->setAttribute(
            'is_locked',
            ! $this->accessLevelService->canAccess($user, $contentType, $item),
        );
    }
}
