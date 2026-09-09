<?php

namespace App\Notifications\Contracts;

use App\Enums\PushNotificationCategory;
use App\Notifications\Messages\FcmMessage;

/**
 * Implemented by any Notification that should be deliverable through
 * App\Notifications\FcmChannel.
 */
interface FcmNotification
{
    /**
     * The category this notification belongs to, used to check the
     * recipient's notification_settings before it is queued for delivery.
     */
    public function pushCategory(): PushNotificationCategory;

    /**
     * Build the push message content for the given notifiable.
     */
    public function toFcm(object $notifiable): FcmMessage;
}
