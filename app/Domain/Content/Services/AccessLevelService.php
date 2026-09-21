<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Exceptions\AccessDeniedException;
use App\Enums\GraduateStatus;
use App\Models\Meditation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Determines whether a (possibly guest) user may access a piece of content,
 * based on their subscription state and graduate confirmation status.
 *
 * Access levels, from lowest to highest:
 *  - none:      no authenticated user, or no currently active subscription.
 *               Only free meditations (is_free=true) and articles are available.
 *  - subscribed: an active subscription, but graduate_status is not "confirmed".
 *               Meditations, tools and topics are fully available; diary content is not.
 *  - confirmed:  an active subscription and graduate_status is "confirmed".
 *               Everything is available.
 *
 * Articles are always accessible, regardless of authentication or subscription state.
 */
class AccessLevelService
{
    public const CONTENT_MEDITATION = 'meditation';

    public const CONTENT_TOOL = 'tool';

    public const CONTENT_TOPIC = 'topic';

    public const CONTENT_ARTICLE = 'article';

    public const CONTENT_DIARY = 'diary';

    /**
     * The $user parameter is nullable to represent unauthenticated visitors,
     * who are treated the same as authenticated users without an active subscription.
     */
    public function canAccess(?User $user, string $contentType, Model $content): bool
    {
        if ($contentType === self::CONTENT_ARTICLE) {
            return true;
        }

        if (!$this->hasActiveSubscription($user)) {
            return $contentType === self::CONTENT_MEDITATION
                && $content instanceof Meditation
                && $content->is_free;
        }

        if ($contentType === self::CONTENT_DIARY) {
            return $user->graduate_status === GraduateStatus::Confirmed;
        }

        return true;
    }

    /**
     * Same rule as canAccess(), but throws instead of returning false, so
     * callers that need to deny access (rather than merely flag it) don't
     * have to repeat the `if (!canAccess(...)) { throw ... }` check themselves.
     */
    public function assertCanAccess(?User $user, string $contentType, Model $content): void
    {
        if (!$this->canAccess($user, $contentType, $content)) {
            throw new AccessDeniedException();
        }
    }

    private function hasActiveSubscription(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->subscriptions()->active()->exists();
    }
}
