<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Home\Services\HomeService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\MeditationResource;
use App\Http\Resources\ToolResource;
use App\Http\Resources\TopicResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Home', description: 'Aggregated home-screen feed: a handful of accessible items per content type plus diary progress. Public — access to each item is subscription-gated per request, not by authentication.')]
class HomeController extends Controller
{
    public function __construct(private readonly HomeService $homeService)
    {
    }

    /**
     * Aggregate, per content type, the featured item (`featured`, or null if
     * none is assigned) and up to 3 other accessible items (`items`, never
     * repeating the featured one), plus the user's practice diary progress,
     * for the app's home screen.
     *
     * An inaccessible featured item is still returned, flagged `is_locked`
     * (as in the catalogue lists), while `items` only contains accessible ones.
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
                'meditations' => [
                    'featured' => $summary['meditations']['featured'] ? new MeditationResource($summary['meditations']['featured']) : null,
                    'items' => MeditationResource::collection($summary['meditations']['items']),
                ],
                'tools' => [
                    'featured' => $summary['tools']['featured'] ? new ToolResource($summary['tools']['featured']) : null,
                    'items' => ToolResource::collection($summary['tools']['items']),
                ],
                'topics' => [
                    'featured' => $summary['topics']['featured'] ? new TopicResource($summary['topics']['featured']) : null,
                    'items' => TopicResource::collection($summary['topics']['items']),
                ],
                'articles' => [
                    'featured' => $summary['articles']['featured'] ? new ArticleResource($summary['articles']['featured']) : null,
                    'items' => ArticleResource::collection($summary['articles']['items']),
                ],
                'diary_progress' => $summary['diary_progress'],
            ],
        ]);
    }
}
