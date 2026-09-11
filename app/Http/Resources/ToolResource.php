<?php

namespace App\Http\Resources;

use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tool
 */
class ToolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // is_locked is a runtime-only flag set by the controller (not a model attribute
        // or cast), so it's read via getAttribute() rather than the ->is_locked mixin.
        $isLocked = (bool) $this->resource->getAttribute('is_locked');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'full_description' => $isLocked ? null : $this->full_description,
            'is_locked' => $isLocked,
            'stage_tag' => $this->stage_tag,
            'topic_ids' => $this->whenLoaded('topics', fn () => $this->topics->pluck('id')),
        ];
    }
}
