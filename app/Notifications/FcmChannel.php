<?php

namespace App\Notifications;

use App\Enums\PushNotificationCategory;
use App\Jobs\SendPushNotificationJob;
use App\Notifications\Contracts\FcmNotification;
use Illuminate\Notifications\Notification;

/**
 * Delivers notifications over Firebase Cloud Messaging.
 *
 * Only notifications implementing FcmNotification are handled; anything else
 * is silently ignored, matching how Laravel's built-in channels (mail,
 * database, ...) skip notifications that don't define their toX() method.
 *
 * Before a push is queued, the recipient's NotificationSetting is checked for
 * the notification's category (daily_practices / new_articles / system), so
 * that a user who disabled a category — or push notifications altogether —
 * never has a job queued on their behalf.
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof FcmNotification) {
            return;
        }

        $tokens = method_exists($notifiable, 'routeNotificationForFcm')
            ? $notifiable->routeNotificationForFcm($notification)
            : [];

        if ($tokens === []) {
            return;
        }

        if (! $this->isAllowed($notifiable, $notification->pushCategory())) {
            return;
        }

        SendPushNotificationJob::dispatch($tokens, $notification->toFcm($notifiable));
    }

    private function isAllowed(object $notifiable, PushNotificationCategory $category): bool
    {
        if (! method_exists($notifiable, 'notificationSettings')) {
            return true;
        }

        $settings = $notifiable->notificationSettings;

        // No settings row yet means the user never touched their defaults,
        // which are all enabled (see notification_settings migration).
        if ($settings === null) {
            return true;
        }

        return $settings->allowsPushCategory($category);
    }
}
