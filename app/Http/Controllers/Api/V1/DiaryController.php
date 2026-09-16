<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Diary\Services\DiaryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diary\SaveDiaryAnswerRequest;
use App\Http\Resources\DiaryDayResource;
use App\Http\Resources\DiaryEntryResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The 100-day practice diary: a strictly sequential, one-day-per-day program.
 * Every endpoint here is graduate-only — a caller whose `graduate_status` is
 * not `confirmed` gets `403 ACCESS_DENIED` from all three actions below.
 */
#[Group(name: 'Diary', description: 'The 100-day practice diary. Graduate-only: every endpoint rejects non-graduates with `403 ACCESS_DENIED`.')]
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
     * Show a single diary day's detail.
     *
     * `403 DAY_LOCKED` ("This diary day is not open yet.") is returned when
     * the requested day is ahead of the user's progress — a day only becomes
     * viewable once it is the user's active day or already completed.
     */
    public function show(Request $request, int $dayNumber): DiaryDayResource
    {
        $day = $this->diaryService->getDayDetail($request->user(), $dayNumber);

        return new DiaryDayResource($day);
    }

    /**
     * Save the user's answer for their current active day, advancing their
     * progress to the next day.
     *
     * `403 DAY_LOCKED` is returned if `dayNumber` is not the user's current
     * active day (out of order — either ahead of or already past it; only
     * the single active day can be answered).
     *
     * `422 ALREADY_COMPLETED_TODAY` is returned if the user has already
     * completed a day within their current local calendar date, as derived
     * from the `timezone` field on this request — at most one day may be
     * completed per local day, regardless of which day is targeted.
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
