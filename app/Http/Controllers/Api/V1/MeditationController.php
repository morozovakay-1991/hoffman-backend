<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Exceptions\AccessDeniedException;
use App\Domain\Content\Services\AccessLevelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeditationResource;
use App\Models\Meditation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MeditationController extends Controller
{
    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * List published meditations.
     *
     * Access decision: locked-but-visible. Rather than hiding meditations the current
     * user can't access, the list returns every published meditation and flags the
     * inaccessible ones with `is_locked` (with `full_description`/`audio_path` stripped).
     * This lets clients render a paywalled catalogue (title, short description, lock
     * icon) instead of silently shrinking the list, which is better for discovery and
     * conversion than omitting locked items entirely.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');

        $meditations = Meditation::query()
            ->where('is_published', true)
            ->with('topics')
            ->orderBy('id')
            ->get();

        $meditations->each(function (Meditation $meditation) use ($user) {
            $meditation->setAttribute(
                'is_locked',
                !$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_MEDITATION, $meditation),
            );
        });

        return MeditationResource::collection($meditations);
    }

    /**
     * Show a single meditation.
     *
     * Unlike the list endpoint, a direct request for one item is explicitly denied
     * (403 ACCESS_DENIED) rather than flagged, since the caller already knows which
     * item they asked for.
     */
    public function show(Request $request, Meditation $meditation): MeditationResource
    {
        if (!$meditation->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        if (!$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_MEDITATION, $meditation)) {
            throw new AccessDeniedException();
        }

        $meditation->setAttribute('is_locked', false);
        $meditation->load('topics');

        return new MeditationResource($meditation);
    }
}
