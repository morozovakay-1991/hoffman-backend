<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Exceptions\AccessDeniedException;
use App\Domain\Content\Services\AccessLevelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ToolController extends Controller
{
    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * List published tools.
     *
     * See App\Http\Controllers\Api\V1\MeditationController::index() for the
     * locked-but-visible access decision applied here (is_locked flag instead of
     * hiding inaccessible records).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');

        $tools = Tool::query()
            ->where('is_published', true)
            ->with('topics')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $tools->each(function (Tool $tool) use ($user) {
            $tool->setAttribute(
                'is_locked',
                !$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_TOOL, $tool),
            );
        });

        return ToolResource::collection($tools);
    }

    public function show(Request $request, Tool $tool): ToolResource
    {
        if (!$tool->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        if (!$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_TOOL, $tool)) {
            throw new AccessDeniedException();
        }

        $tool->setAttribute('is_locked', false);
        $tool->load('topics');

        return new ToolResource($tool);
    }
}
