<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Exceptions\AccessDeniedException;
use App\Domain\Content\Services\AccessLevelService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function __construct(private readonly AccessLevelService $accessLevelService)
    {
    }

    /**
     * List published articles.
     *
     * Articles are always accessible (see AccessLevelService::canAccess()), so no
     * per-item check or is_locked flag is needed here; show() below still routes
     * through the service so that rule stays defined in a single place.
     */
    public function index(): AnonymousResourceCollection
    {
        $articles = Article::query()
            ->where('is_published', true)
            ->orderBy('id')
            ->get();

        return ArticleResource::collection($articles);
    }

    public function show(Request $request, Article $article): ArticleResource
    {
        if (!$article->is_published) {
            abort(404);
        }

        $user = $request->user('sanctum');

        if (!$this->accessLevelService->canAccess($user, AccessLevelService::CONTENT_ARTICLE, $article)) {
            throw new AccessDeniedException();
        }

        return new ArticleResource($article);
    }
}
