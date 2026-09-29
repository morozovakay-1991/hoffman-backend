<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\AccessLevelService;
use App\Domain\Content\Services\ContentCatalogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Articles', description: 'Article catalogue. Public — articles are always accessible regardless of subscription, so both guests and logged-in users may call these.')]
class ArticleController extends Controller
{
    public function __construct(
        private readonly AccessLevelService $accessLevelService,
        private readonly ContentCatalogService $contentCatalogService,
    ) {
    }

    /**
     * List published articles.
     *
     * Articles are always accessible (see AccessLevelService::canAccess()), so no
     * per-item check or is_locked flag is needed here; show() below still routes
     * through the service so that rule stays defined in a single place.
     *
     * The response is split into `featured` (the headline article, or null if
     * none is assigned) and `items` (every other published article); the
     * featured article never appears in `items`.
     */
    public function index(Request $request): JsonResponse
    {
        $featured = $this->contentCatalogService->getFeatured(
            Article::class,
            AccessLevelService::CONTENT_ARTICLE,
            $request->user('sanctum'),
        );

        $articles = $this->contentCatalogService->listPublished(Article::class);

        return response()->json([
            'data' => [
                'featured' => $featured ? new ArticleResource($featured) : null,
                'items' => ArticleResource::collection($articles),
            ],
        ]);
    }

    /**
     * Show a single article.
     *
     * Routed through AccessLevelService for consistency with other content
     * types, but articles are always accessible, so `403 ACCESS_DENIED` is
     * not expected in practice for this endpoint.
     */
    public function show(Request $request, Article $article): ArticleResource
    {
        if (!$article->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        $this->accessLevelService->assertCanAccess($user, AccessLevelService::CONTENT_ARTICLE, $article);

        return new ArticleResource($article);
    }
}
