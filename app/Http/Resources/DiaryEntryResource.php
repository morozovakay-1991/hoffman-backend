<?php

namespace App\Http\Resources;

use App\Models\DiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiaryEntry
 */
class DiaryEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'answer_text' => $this->answer_text,
            'completed_date' => $this->completed_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
