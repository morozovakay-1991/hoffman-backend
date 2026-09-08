<?php

namespace App\Http\Resources;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Topic
 */
class TopicResource extends JsonResource
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
            'subtitle' => $this->subtitle,
            'full_description' => $isLocked ? null : $this->full_description,
            'is_locked' => $isLocked,
            'meditation_ids' => $this->whenLoaded('meditations', fn () => $this->meditations->pluck('id')),
            'tool_ids' => $this->whenLoaded('tools', fn () => $this->tools->pluck('id')),
        ];
    }
}
