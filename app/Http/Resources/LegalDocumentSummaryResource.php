<?php

namespace App\Http\Resources;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegalDocument
 */
class LegalDocumentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'updated_at' => $this->updated_at,
        ];
    }
}
