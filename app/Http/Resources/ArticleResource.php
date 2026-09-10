<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
            'cover_image_path' => $this->cover_image_path,
            'published_at' => $this->published_at,
            'is_new' => $this->is_new,
            // Articles are always accessible regardless of subscription state (see AccessLevelService),
            // so is_locked is always false. Kept for a consistent response shape across content resources.
            'is_locked' => false,
        ];
    }
}
