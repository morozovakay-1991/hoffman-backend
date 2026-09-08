<?php

namespace App\Http\Resources;

use App\Models\DeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeletionRequest
 */
class DeletionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'reason' => $this->reason,
            'scheduled_for' => $this->scheduled_for,
            'completed_at' => $this->completed_at,
        ];
    }
}
