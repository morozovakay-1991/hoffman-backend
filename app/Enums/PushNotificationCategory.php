<?php

namespace App\Enums;

/**
 * Categories a push notification can belong to. Each maps to a boolean column
 * on notification_settings that the user can toggle independently.
 */
enum PushNotificationCategory: string
{
    case DailyPractices = 'daily_practices';
    case NewArticles = 'new_articles';
    case System = 'system';

    /**
     * The notification_settings column that gates this category.
     */
    public function settingColumn(): string
    {
        return match ($this) {
            self::DailyPractices => 'daily_practices_enabled',
            self::NewArticles => 'new_articles_enabled',
            self::System => 'system_enabled',
        };
    }
}
