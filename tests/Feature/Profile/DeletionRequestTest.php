<?php

namespace Tests\Feature\Profile;

use App\Domain\Profile\Exceptions\DeletionAlreadyRequestedException;
use App\Domain\Profile\Services\ProfileService;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * The application-level exists() check in ProfileService::requestDeletion()
     * has a TOCTOU race window: two concurrent requests can both pass the
     * check before either has inserted its row. The partial unique index on
     * deletion_requests(user_id) WHERE status = 'pending' is the real
     * safety net, rejecting the second insert at the database level so a
     * user can never end up with two pending requests.
     */
    public function test_a_second_pending_deletion_request_is_rejected_at_the_database_level(): void
    {
        $user = User::factory()->create();

        app(ProfileService::class)->requestDeletion($user, 'first request');

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('deletion_requests')->insert([
            'user_id' => $user->id,
            'status' => 'pending',
            'reason' => 'second request racing the first',
            'scheduled_for' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * ProfileService::requestDeletion() itself catches a unique constraint
     * violation and translates it into DeletionAlreadyRequestedException
     * (a 422, not a 500) even when its own exists() check did not see the
     * race. A DB query listener stands in for the second, concurrent
     * request here: it inserts the competing pending row at the exact
     * moment the first request's exists() check runs, landing squarely in
     * the TOCTOU gap between that check and this request's own insert.
     */
    public function test_request_deletion_survives_a_true_race_by_raising_the_domain_exception(): void
    {
        $user = User::factory()->create();
        $service = app(ProfileService::class);

        $raced = false;
        DB::listen(function ($query) use (&$raced, $user) {
            if (! $raced && str_contains($query->sql, 'exists') && str_contains($query->sql, 'deletion_requests')) {
                $raced = true;

                DB::table('deletion_requests')->insert([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'reason' => 'concurrent request',
                    'scheduled_for' => now()->addDays(30),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        try {
            $this->expectException(DeletionAlreadyRequestedException::class);

            $service->requestDeletion($user, 'this request');
        } finally {
            $this->assertTrue($raced, 'The query listener never fired; the test did not exercise the race.');
            $this->assertSame(
                1,
                DB::table('deletion_requests')->where('user_id', $user->id)->where('status', 'pending')->count(),
                'A duplicate pending deletion request must never be persisted.',
            );
        }
    }
}
