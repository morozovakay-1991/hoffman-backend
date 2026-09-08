<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Diary\Services\DiaryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diary\SaveDiaryAnswerRequest;
use App\Http\Resources\DiaryDayResource;
use App\Http\Resources\DiaryEntryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiaryController extends Controller
{
    public function __construct(private readonly DiaryService $diaryService)
    {
    }

    /**
     * List all 100 diary days for the authenticated user, each flagged with
     * its locked/active/completed status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $days = $this->diaryService->getDaysForUser($request->user());

        return DiaryDayResource::collection($days);
    }

    /**
     * Show a single diary day's detail. Locked (not-yet-reached) days are
     * rejected with 403 DAY_LOCKED.
     */
    public function show(Request $request, int $dayNumber): DiaryDayResource
    {
        $day = $this->diaryService->getDayDetail($request->user(), $dayNumber);

        return new DiaryDayResource($day);
    }

    /**
     * Save the user's answer for their current active day, advancing their
     * progress to the next day.
     */
    public function saveAnswer(SaveDiaryAnswerRequest $request, int $dayNumber): DiaryEntryResource
    {
        $entry = $this->diaryService->saveAnswer(
            $request->user(),
            $dayNumber,
            $request->validated('answer'),
            $request->validated('timezone'),
        );

        return new DiaryEntryResource($entry);
    }
}
