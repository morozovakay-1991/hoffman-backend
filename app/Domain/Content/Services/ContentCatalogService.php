<?php

namespace App\Domain\Content\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the "list published items" query shared by every content catalogue
 * endpoint (articles, meditations, tools, topics), so controllers stay thin:
 * call in, wrap the result in a Resource collection, return it.
 */
class ContentCatalogService
{
    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * Published items ordered by id, with no locking (used by Article, which
     * is always accessible and has no sort_order column).
     *
     * @param class-string<Model> $modelClass
     * @param list<string> $with
     * @return Collection<int, Model>
     */
    public function listPublished(string $modelClass, array $with = []): Collection
    {
        return $modelClass::query()
            ->where('is_published', true)
            ->with($with)
            ->orderBy('id')
            ->get();
    }

    /**
     * Published items ordered by sort_order, each flagged with is_locked per
     * AccessLevelService::canAccess() for $contentType (one of its
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
            ->with($with)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items->each(function (Model $item) use ($user, $contentType) {
            $item->setAttribute(
                'is_locked',
                ! $this->accessLevelService->canAccess($user, $contentType, $item),
            );
        });

        return $items;
    }
}
