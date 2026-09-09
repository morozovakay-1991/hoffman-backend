<?php

namespace App\Notifications\Messages;

/**
 * The content of a push notification, independent of which device tokens it
 * is delivered to. Built by a notification's toFcm() method and handed to
 * SendPushNotificationJob for the actual Firebase Cloud Messaging call.
 */
class FcmMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {
    }
}
