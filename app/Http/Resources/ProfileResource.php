<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'graduate_status' => $this->graduate_status,
            'email_verified_at' => $this->email_verified_at,
            'notification_settings' => $this->whenLoaded(
                'notificationSettings',
                fn () => $this->notificationSettings ? new NotificationSettingResource($this->notificationSettings) : null,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
