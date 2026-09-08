<?php

namespace Tests\Feature\Console;

use App\Enums\VerificationStatus;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProcessDeletionRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedRelatedData(User $user): void
    {
        $day = DiaryDay::factory()->create(['day_number' => 1]);

        DiaryEntry::create([
            'user_id' => $user->id,
            'diary_day_id' => $day->id,
            'answer_text' => 'My answer',
            'completed_date' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        VerificationRequest::create([
            'user_id' => $user->id,
            'last_name' => 'Ivanov',
            'first_name' => 'Petr',
            'phone' => '+79001234567',
            'status' => VerificationStatus::Pending,
        ]);

        NotificationSetting::create([
            'user_id' => $user->id,
            'push_enabled' => true,
            'email_enabled' => true,
            'marketing_enabled' => false,
        ]);
    }

    public function test_it_processes_a_deletion_request_with_a_past_scheduled_date(): void
    {
        $user = User::factory()->create();
        $this->seedRelatedData($user);
        $token = $user->createToken('api_token');

        $deletionRequest = $user->deletionRequests()->create([
            'status' => 'pending',
            'scheduled_for' => now()->subDay(),
        ]);

        Artisan::call('app:process-deletion-requests');

        $deletionRequest->refresh();
        $this->assertSame('completed', $deletionRequest->status);
        $this->assertNotNull($deletionRequest->completed_at);

        $this->assertDatabaseCount('diary_entries', 0);
        $this->assertDatabaseCount('verification_requests', 0);
        $this->assertDatabaseCount('notification_settings', 0);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->assertNotNull($user->fresh()->deleted_at);
    }

    public function test_it_does_not_process_a_deletion_request_scheduled_in_the_future(): void
    {
        $user = User::factory()->create();
        $this->seedRelatedData($user);

        $deletionRequest = $user->deletionRequests()->create([
            'status' => 'pending',
            'scheduled_for' => now()->addDay(),
        ]);

        Artisan::call('app:process-deletion-requests');

        $deletionRequest->refresh();
        $this->assertSame('pending', $deletionRequest->status);
        $this->assertNull($deletionRequest->completed_at);

        $this->assertDatabaseCount('diary_entries', 1);
        $this->assertDatabaseCount('verification_requests', 1);
        $this->assertDatabaseCount('notification_settings', 1);
        $this->assertNull($user->fresh()->deleted_at);
    }

    public function test_a_second_run_does_not_reprocess_an_already_completed_request(): void
    {
        $user = User::factory()->create();

        $deletionRequest = $user->deletionRequests()->create([
            'status' => 'pending',
            'scheduled_for' => now()->subDay(),
        ]);

        Artisan::call('app:process-deletion-requests');

        $deletionRequest->refresh();
        $completedAt = $deletionRequest->completed_at;
        $this->assertSame('completed', $deletionRequest->status);
        $this->assertNotNull($completedAt);

        Artisan::call('app:process-deletion-requests');

        $deletionRequest->refresh();
        $this->assertSame('completed', $deletionRequest->status);
        $this->assertTrue($completedAt->equalTo($deletionRequest->completed_at));
    }
}
