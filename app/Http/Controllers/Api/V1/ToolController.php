<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\AccessLevelService;
use App\Domain\Content\Services\ContentCatalogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Tools', description: 'Practice tools catalogue. Public and always accessible — no authentication or subscription required; `is_locked` is always false.')]
class ToolController extends Controller
{
    public function __construct(
        private readonly AccessLevelService $accessLevelService,
        private readonly ContentCatalogService $contentCatalogService,
    ) {
    }

    /**
     * List published tools.
     *
     * See App\Http\Controllers\Api\V1\MeditationController::index() for the
     * locked-but-visible access decision applied here (is_locked flag instead of
     * hiding inaccessible records), and for the `featured` / `items` split.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $featured = $this->contentCatalogService->getFeatured(
            Tool::class,
            AccessLevelService::CONTENT_TOOL,
            $user,
            ['topics'],
        );

        $tools = $this->contentCatalogService->listPublishedWithLockFlag(
            Tool::class,
            AccessLevelService::CONTENT_TOOL,
            $user,
            ['topics'],
        );

        return response()->json([
            'data' => [
                'featured' => $featured ? new ToolResource($featured) : null,
                'items' => ToolResource::collection($tools),
            ],
        ]);
    }

    /**
     * Show a single tool.
     *
     * Tools are always accessible (see AccessLevelService::canAccess()),
     * so `403 ACCESS_DENIED` is never returned in practice; the check is kept
     * for consistency with other content types.
     */
    public function show(Request $request, Tool $tool): ToolResource
    {
        if (!$tool->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        $this->accessLevelService->assertCanAccess($user, AccessLevelService::CONTENT_TOOL, $tool);

        $tool->setAttribute('is_locked', false);
        $tool->load('topics');

        return new ToolResource($tool);
    }
}
