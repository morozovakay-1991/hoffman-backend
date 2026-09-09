<?php

namespace App\Http\Resources;

use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationSetting
 */
class NotificationSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'push_enabled' => $this->push_enabled,
            'email_enabled' => $this->email_enabled,
            'marketing_enabled' => $this->marketing_enabled,
            'daily_practices_enabled' => $this->daily_practices_enabled,
            'new_articles_enabled' => $this->new_articles_enabled,
            'system_enabled' => $this->system_enabled,
        ];
    }
}
