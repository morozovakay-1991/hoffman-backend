<?php

namespace App\Jobs;

use App\Notifications\Messages\FcmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

/**
 * Delivers a single push notification to one or more device registration
 * tokens via Firebase Cloud Messaging. Dispatched by App\Notifications\FcmChannel
 * only after that channel has confirmed the recipient's notification_settings
 * allow the notification's category.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  list<string>  $tokens
     */
    public function __construct(
        public readonly array $tokens,
        public readonly FcmMessage $message,
    ) {
        $this->onConnection('redis');
        $this->onQueue('push');
    }

    public function handle(Messaging $messaging): void
    {
        if ($this->tokens === []) {
            return;
        }

        $cloudMessage = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($this->message->title, $this->message->body))
            ->withData($this->message->data);

        $messaging->sendMulticast($cloudMessage, $this->tokens);
    }
}
