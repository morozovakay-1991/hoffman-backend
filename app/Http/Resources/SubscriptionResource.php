<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'provider' => $this->provider,
            'product_id' => $this->product_id,
            'auto_renew' => $this->auto_renew,
            'is_active' => $this->isActive(),
            'starts_at' => $this->starts_at,
            'trial_ends_at' => $this->trial_ends_at,
            'expires_at' => $this->expires_at,
            'cancelled_at' => $this->cancelled_at,
        ];
    }
}
