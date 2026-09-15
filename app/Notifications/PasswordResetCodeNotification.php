<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PasswordResetCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Код для сброса пароля')
            ->greeting('Здравствуйте!')
            ->line('Вы запросили сброс пароля. Используйте код ниже, чтобы продолжить.')
            ->line(new HtmlString('<strong>'.$this->code.'</strong>'))
            ->line("Код действителен {$this->ttlMinutes} мин.")
            ->line('Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.');
    }
}
