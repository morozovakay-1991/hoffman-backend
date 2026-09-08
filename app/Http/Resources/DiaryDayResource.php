<?php

namespace App\Http\Resources;

use App\Domain\Diary\Services\DiaryService;
use App\Models\DiaryDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiaryDay
 */
class DiaryDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // status and entry are runtime-only attributes set by DiaryService, not
        // model attributes, so they're read via getAttribute() rather than the mixin.
        $status = $this->resource->getAttribute('status');
        $entry = $this->resource->getAttribute('entry');
        $isLocked = $status === DiaryService::STATUS_LOCKED;

        return [
            'day_number' => $this->day_number,
            'title' => $this->title,
            'task_text' => $isLocked ? null : $this->task_text,
            'status' => $status,
            'answer' => $this->when($entry !== null, fn () => new DiaryEntryResource($entry)),
        ];
    }
}
