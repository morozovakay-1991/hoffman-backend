<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\AccessLevelService;
use App\Domain\Content\Services\ContentCatalogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TopicResource;
use App\Models\Topic;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Topics', description: 'Content topics (categories grouping meditations and tools). Public and always accessible — no authentication or subscription required; `is_locked` is always false.')]
class TopicController extends Controller
{
    public function __construct(
        private readonly AccessLevelService $accessLevelService,
        private readonly ContentCatalogService $contentCatalogService,
    ) {
    }

    /**
     * List published topics.
     *
     * See App\Http\Controllers\Api\V1\MeditationController::index() for the
     * locked-but-visible access decision applied here (is_locked flag instead of
     * hiding inaccessible records), and for the `featured` / `items` split.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $featured = $this->contentCatalogService->getFeatured(
            Topic::class,
            AccessLevelService::CONTENT_TOPIC,
            $user,
            ['meditations', 'tools'],
        );

        $topics = $this->contentCatalogService->listPublishedWithLockFlag(
            Topic::class,
            AccessLevelService::CONTENT_TOPIC,
            $user,
            ['meditations', 'tools'],
        );

        return response()->json([
            'data' => [
                'featured' => $featured ? new TopicResource($featured) : null,
                'items' => TopicResource::collection($topics),
            ],
        ]);
    }

    /**
     * Show a single topic.
     *
     * Topics are always accessible (see AccessLevelService::canAccess()),
     * so `403 ACCESS_DENIED` is never returned in practice; the check is kept
     * for consistency with other content types.
     */
    public function show(Request $request, Topic $topic): TopicResource
    {
        if (!$topic->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        $this->accessLevelService->assertCanAccess($user, AccessLevelService::CONTENT_TOPIC, $topic);

        $topic->setAttribute('is_locked', false);
        $topic->load(['meditations', 'tools']);

        return new TopicResource($topic);
    }
}
