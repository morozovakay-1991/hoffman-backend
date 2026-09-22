<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\AccessLevelService;
use App\Domain\Content\Services\ContentCatalogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'Tools', description: 'Practice tools catalogue. Public — access to each item is subscription-gated per request, not by authentication, so both guests and logged-in users may call these.')]
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
     * hiding inaccessible records).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');

        $tools = $this->contentCatalogService->listPublishedWithLockFlag(
            Tool::class,
            AccessLevelService::CONTENT_TOOL,
            $user,
            ['topics'],
        );

        return ToolResource::collection($tools);
    }

    /**
     * Show a single tool.
     *
     * Unlike the list endpoint, a direct request for one item is explicitly
     * denied (`403 ACCESS_DENIED`) rather than flagged, since the caller
     * already knows which item they asked for.
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
