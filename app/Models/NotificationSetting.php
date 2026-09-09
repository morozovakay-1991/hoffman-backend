<?php

namespace App\Models;

use App\Enums\PushNotificationCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationSettingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'push_enabled',
        'email_enabled',
        'marketing_enabled',
        'daily_practices_enabled',
        'new_articles_enabled',
        'system_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'marketing_enabled' => 'boolean',
            'daily_practices_enabled' => 'boolean',
            'new_articles_enabled' => 'boolean',
            'system_enabled' => 'boolean',
        ];
    }

    /**
     * Whether push notifications of the given category should be sent to this
     * user: both the global push toggle and the category's own toggle must be on.
     */
    public function allowsPushCategory(PushNotificationCategory $category): bool
    {
        if (! $this->push_enabled) {
            return false;
        }

        return (bool) $this->getAttribute($category->settingColumn());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
