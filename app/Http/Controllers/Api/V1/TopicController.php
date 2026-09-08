<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Exceptions\AccessDeniedException;
use App\Domain\Content\Services\AccessLevelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TopicResource;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function show(Request $request, Topic $topic): TopicResource
    {
        if (!$topic->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        if (!$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_TOPIC, $topic)) {
            throw new AccessDeniedException();
        }

        $topic->setAttribute('is_locked', false);
        $topic->load(['meditations', 'tools']);

        return new TopicResource($topic);
    }
}
