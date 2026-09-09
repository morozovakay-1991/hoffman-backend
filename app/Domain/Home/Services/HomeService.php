<?php

namespace App\Domain\Home\Services;

use App\Domain\Content\Services\AccessLevelService;
use App\Models\Article;
use App\Models\Meditation;
use App\Models\Tool;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the home screen: a handful of accessible items from each content
 * type plus a summary of the user's practice diary progress. Access to each
 * item is decided by AccessLevelService, so guests and non-subscribed users
 * only ever see content they're actually allowed to open — unlike the
 * catalogue endpoints (MeditationController et al.), which show locked items
 * flagged rather than omitted.
 */
class HomeService
{
    private const ITEMS_PER_SECTION = 5;

    private const DIARY_TOTAL_DAYS = 100;

    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * @return array{meditations: Collection<int, Model>, tools: Collection<int, Model>, topics: Collection<int, Model>, articles: Collection<int, Article>, diary_progress: array{current_day: int|null, total_days: int, is_available: bool}}
     */
    public function getSummary(?User $user): array
    {
        return [
            'meditations' => $this->accessibleItems(
                Meditation::query()->where('is_published', true)->orderByDesc('id')->get(),
                $user,
                AccessLevelService::CONTENT_MEDITATION,
            ),
            'tools' => $this->accessibleItems(
                Tool::query()->where('is_published', true)->orderByDesc('id')->get(),
                $user,
                AccessLevelService::CONTENT_TOOL,
            ),
            'topics' => $this->accessibleItems(
                Topic::query()->where('is_published', true)->orderByDesc('id')->get(),
                $user,
                AccessLevelService::CONTENT_TOPIC,
            ),
            'articles' => Article::query()
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->take(self::ITEMS_PER_SECTION)
                ->get()
                ->each(fn (Article $article) => $article->setAttribute('is_locked', false)),
            'diary_progress' => $this->diaryProgress($user),
        ];
    }

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
