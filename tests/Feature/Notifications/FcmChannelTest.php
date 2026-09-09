<?php

namespace Tests\Feature\Notifications;

use App\Enums\PushNotificationCategory;
use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\Contracts\FcmNotification;
use App\Notifications\FcmChannel;
use App\Notifications\Messages\FcmMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FcmChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_is_queued_when_its_category_is_enabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['token' => 'daily-token']);
        NotificationSetting::factory()->for($user)->create(['daily_practices_enabled' => true]);

        $user->notify(new DailyPracticeTestNotification());

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job) => $job->tokens === ['daily-token']
                && $job->message->title === 'Daily practice',
        );
    }

    public function test_push_is_not_queued_when_its_category_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();
        NotificationSetting::factory()->for($user)->create(['daily_practices_enabled' => false]);

        $user->notify(new DailyPracticeTestNotification());

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_push_is_not_queued_when_a_different_category_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();
        NotificationSetting::factory()->for($user)->create([
            'daily_practices_enabled' => true,
            'new_articles_enabled' => false,
        ]);

        $user->notify(new NewArticleTestNotification());

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_push_is_not_queued_when_push_is_globally_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();
        NotificationSetting::factory()->for($user)->create([
            'push_enabled' => false,
            'daily_practices_enabled' => true,
        ]);

        $user->notify(new DailyPracticeTestNotification());

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_push_defaults_to_enabled_when_the_user_has_no_settings_row_yet(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();

        $user->notify(new DailyPracticeTestNotification());

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    public function test_push_is_not_queued_when_the_user_has_no_registered_devices(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        NotificationSetting::factory()->for($user)->create();

        $user->notify(new DailyPracticeTestNotification());

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_system_category_is_checked_independently_of_other_categories(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();
        NotificationSetting::factory()->for($user)->create([
            'daily_practices_enabled' => false,
            'new_articles_enabled' => false,
            'system_enabled' => true,
        ]);

        $user->notify(new SystemTestNotification());

        Queue::assertPushed(SendPushNotificationJob::class);
    }
}

class DailyPracticeTestNotification extends Notification implements FcmNotification
{
    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function pushCategory(): PushNotificationCategory
    {
        return PushNotificationCategory::DailyPractices;
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return new FcmMessage('Daily practice', 'Time for today\'s practice.');
    }
}

class NewArticleTestNotification extends Notification implements FcmNotification
{
    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function pushCategory(): PushNotificationCategory
    {
        return PushNotificationCategory::NewArticles;
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return new FcmMessage('New article', 'A new article was published.');
    }
}

class SystemTestNotification extends Notification implements FcmNotification
{
    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function pushCategory(): PushNotificationCategory
    {
        return PushNotificationCategory::System;
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return new FcmMessage('System notice', 'Something you should know.');
    }
}
