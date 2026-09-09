<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Home\Services\HomeService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\MeditationResource;
use App\Http\Resources\ToolResource;
use App\Http\Resources\TopicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $homeService)
    {
    }

    /**
     * Aggregate a handful of accessible items per content type, plus the
     * user's practice diary progress, for the app's home screen.
     *
     * Like the content catalogue endpoints, this accepts both guest and
     * authenticated requests: access to each item is decided per-request by
     * AccessLevelService rather than by whether the caller is authenticated.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $summary = $this->homeService->getSummary($user);

        return response()->json([
            'data' => [
                'meditations' => MeditationResource::collection($summary['meditations']),
                'tools' => ToolResource::collection($summary['tools']),
                'topics' => TopicResource::collection($summary['topics']),
                'articles' => ArticleResource::collection($summary['articles']),
                'diary_progress' => $summary['diary_progress'],
            ],
        ]);
    }
}
