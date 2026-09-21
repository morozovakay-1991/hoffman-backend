<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\AccessLevelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TopicResource;
use App\Models\Topic;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Topics', description: 'Content topics (categories grouping meditations and tools). Public — access to each item is subscription-gated per request, not by authentication, so both guests and logged-in users may call these.')]
class TopicController extends Controller
{
    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * List published topics.
     *
     * See App\Http\Controllers\Api\V1\MeditationController::index() for the
     * locked-but-visible access decision applied here (is_locked flag instead of
     * hiding inaccessible records).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');

        $topics = Topic::query()
            ->where('is_published', true)
            ->with(['meditations', 'tools'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $topics->each(function (Topic $topic) use ($user) {
            $topic->setAttribute(
                'is_locked',
                !$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_TOPIC, $topic),
            );
        });

        return TopicResource::collection($topics);
    }

    /**
     * Show a single topic.
     *
     * Unlike the list endpoint, a direct request for one item is explicitly
     * denied (`403 ACCESS_DENIED`) rather than flagged, since the caller
     * already knows which item they asked for.
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
