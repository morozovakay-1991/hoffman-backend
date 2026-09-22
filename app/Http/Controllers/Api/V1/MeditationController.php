<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\AccessLevelService;
use App\Domain\Content\Services\ContentCatalogService;
use App\Domain\Content\Services\MeditationAudioService;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeditationResource;
use App\Models\Meditation;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Meditations', description: 'Guided meditation catalogue. Public — access to each item is subscription-gated per request, not by authentication, so both guests and logged-in users may call these.')]
class MeditationController extends Controller
{
    public function __construct(
        private readonly AccessLevelService $accessLevelService,
        private readonly MeditationAudioService $meditationAudioService,
        private readonly ContentCatalogService $contentCatalogService,
    ) {
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

        $meditations = $this->contentCatalogService->listPublishedWithLockFlag(
            Meditation::class,
            AccessLevelService::CONTENT_MEDITATION,
            $user,
            ['topics'],
        );

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
        if (! $meditation->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        $this->accessLevelService->assertCanAccess($user, AccessLevelService::CONTENT_MEDITATION, $meditation);

        $meditation->setAttribute('is_locked', false);
        $meditation->load('topics');

        return new MeditationResource($meditation);
    }

    /**
     * Issue a temporary, presigned URL to the meditation's audio file on the S3 disk.
     *
     * Access is checked the same way as `show`: a direct request for one item's audio
     * is explicitly denied (403 ACCESS_DENIED) rather than flagged. The URL is valid
     * for 1 hour, after which the client must request a fresh one.
     */
    public function audio(Request $request, Meditation $meditation): JsonResponse
    {
        if (! $meditation->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        $this->accessLevelService->assertCanAccess($user, AccessLevelService::CONTENT_MEDITATION, $meditation);

        $audio = $this->meditationAudioService->presignedUrl($meditation);

        return response()->json([
            'data' => [
                'url' => $audio['url'],
                'expires_at' => $audio['expires_at']->toIso8601String(),
            ],
        ]);
    }
}
