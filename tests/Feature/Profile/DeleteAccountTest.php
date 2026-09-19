<?php

namespace Tests\Feature\Profile;

use App\Enums\VerificationStatus;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\EmailChangeRequest;
use App\Models\Invoice;
use App\Models\NotificationSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_soft_deletes_the_account_immediately(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->deleteJson('/api/v1/profile');

        $response->assertStatus(204);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_it_revokes_all_access_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('other_device');

        $this->actingAsApiUser($user)->deleteJson('/api/v1/profile')->assertStatus(204);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_revoked_token_can_no_longer_authenticate(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/profile')
            ->assertStatus(204);

        // Sanctum's guard caches the resolved user on the guard instance itself, and the
        // service container isn't rebooted between calls within a single test, so the
        // cache must be cleared manually to simulate the token being checked afresh.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->deleteJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_it_cancels_a_pending_deletion_request_when_deleting_immediately(): void
    {
        $user = User::factory()->create();

        $this->actingAsApiUser($user)->postJson('/api/v1/profile/deletion-request')->assertStatus(201);

        $this->actingAsApiUser($user)->deleteJson('/api/v1/profile')->assertStatus(204);

        $this->assertDatabaseHas('deletion_requests', [
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('deletion_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_leaves_no_pending_deletion_request_for_the_scheduled_command_to_pick_up(): void
    {
        $user = User::factory()->create();

        $this->actingAsApiUser($user)
            ->postJson('/api/v1/profile/deletion-request')
            ->assertStatus(201);

        $this->actingAsApiUser($user)->deleteJson('/api/v1/profile')->assertStatus(204);

        $this->artisan('app:process-deletion-requests')->assertExitCode(0);

        $this->assertDatabaseHas('deletion_requests', [
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_it_purges_personal_data_but_keeps_invoices_and_subscriptions(): void
    {
        $user = User::factory()->create();

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

        EmailChangeRequest::create([
            'user_id' => $user->id,
            'old_email' => $user->email,
            'new_email' => 'new@example.com',
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        $this->actingAsApiUser($user)->deleteJson('/api/v1/profile')->assertStatus(204);

        $this->assertDatabaseCount('diary_entries', 0);
        $this->assertDatabaseCount('verification_requests', 0);
        $this->assertDatabaseCount('notification_settings', 0);
        $this->assertDatabaseCount('email_change_requests', 0);

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
