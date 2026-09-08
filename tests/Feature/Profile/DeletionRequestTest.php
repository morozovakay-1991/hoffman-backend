<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_deletion_request_scheduled_thirty_days_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/deletion-request', [
            'reason' => 'No longer needed',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('deletion_request.status', 'pending')
            ->assertJsonPath('deletion_request.reason', 'No longer needed');

        $this->assertDatabaseHas('deletion_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $deletionRequest = $user->deletionRequests()->firstOrFail();
        $this->assertTrue($deletionRequest->scheduled_for->isSameDay(now()->addDays(30)));
    }

    public function test_it_does_not_delete_the_account_immediately(): void
    {
        $user = User::factory()->create();

        $this->actingAsApiUser($user)->postJson('/api/v1/profile/deletion-request')->assertStatus(201);

        $this->assertNull($user->refresh()->deleted_at);
    }

    public function test_it_rejects_a_repeated_deletion_request_while_one_is_pending(): void
    {
        $user = User::factory()->create();

        $this->actingAsApiUser($user)->postJson('/api/v1/profile/deletion-request')->assertStatus(201);

        $response = $this->actingAsApiUser($user)->postJson('/api/v1/profile/deletion-request');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DELETION_ALREADY_REQUESTED');

        $this->assertSame(1, $user->deletionRequests()->count());
    }

    public function test_it_rejects_the_request_without_a_token(): void
    {
        $this->postJson('/api/v1/profile/deletion-request')->assertStatus(401);
    }
}
