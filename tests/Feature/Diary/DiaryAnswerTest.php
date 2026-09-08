<?php

namespace Tests\Feature\Diary;

use App\Enums\GraduateStatus;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiaryAnswerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function seedDays(int $count = 5): void
    {
        for ($dayNumber = 1; $dayNumber <= $count; $dayNumber++) {
            DiaryDay::factory()->create(['day_number' => $dayNumber]);
        }
    }

    private function completeDay(User $user, int $dayNumber): void
    {
        $day = DiaryDay::query()->where('day_number', $dayNumber)->firstOrFail();

        DiaryEntry::create([
            'user_id' => $user->id,
            'diary_day_id' => $day->id,
            'answer_text' => "Answer for day {$dayNumber}",
            'completed_date' => now()->toDateString(),
            'completed_at' => now(),
        ]);
    }

    private function postAnswer(User $user, int $dayNumber, string $timezone = 'UTC', string $answer = 'My answer'): TestResponse
    {
        return $this->actingAsApiUser($user)
            ->withHeader('X-Timezone', $timezone)
            ->postJson("/api/v1/diary/days/{$dayNumber}/answer", ['answer' => $answer]);
    }

    // --- Guarding module access ----------------------------------------------

    public function test_guest_is_rejected(): void
    {
        $this->seedDays();

        $response = $this->postJson('/api/v1/diary/days/1/answer', ['answer' => 'x']);

        $response->assertStatus(401);
    }

    public function test_non_confirmed_graduate_is_denied(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();

        $response = $this->postAnswer($user, 1);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    // --- Validation ------------------------------------------------------------

    public function test_missing_timezone_header_is_rejected(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->actingAsApiUser($user)
            ->postJson('/api/v1/diary/days/1/answer', ['answer' => 'My answer']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.timezone.0', 'The timezone field is required.');
    }

    public function test_invalid_timezone_header_is_rejected(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->postAnswer($user, 1, 'Not/A_Timezone');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJson(fn ($json) => $json->has('error.fields.timezone'));
    }

    public function test_missing_answer_is_rejected(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->actingAsApiUser($user)
            ->withHeader('X-Timezone', 'UTC')
            ->postJson('/api/v1/diary/days/1/answer', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.fields.answer.0', 'The answer field is required.');
    }

    // --- Sequential order --------------------------------------------------------

    public function test_attempting_a_day_out_of_order_is_locked(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        // Progress is on day 1; day 3 has not been reached yet.
        $response = $this->postAnswer($user, 3);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'DAY_LOCKED');

        $this->assertDatabaseCount('diary_entries', 0);
    }

    public function test_resaving_an_already_completed_day_is_rejected(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        $this->completeDay($user, 1);

        // Progress has moved to day 2; day 1 is already completed.
        $response = $this->postAnswer($user, 1, 'UTC', 'Trying to redo day 1');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'DAY_LOCKED');

        $this->assertDatabaseCount('diary_entries', 1);
        $this->assertDatabaseHas('diary_entries', ['answer_text' => 'Answer for day 1']);
    }

    public function test_the_active_day_can_be_answered_and_progress_advances(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->postAnswer($user, 1, 'UTC', 'My first day answer');

        $response->assertCreated()
            ->assertJsonPath('data.answer_text', 'My first day answer');

        $day1 = DiaryDay::query()->where('day_number', 1)->first();
        $this->assertDatabaseHas('diary_entries', [
            'user_id' => $user->id,
            'diary_day_id' => $day1->id,
            'answer_text' => 'My first day answer',
        ]);

        // Day 2 is now active.
        $listResponse = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days');
        $days = collect($listResponse->json('data'))->keyBy('day_number');
        $this->assertSame('completed', $days[1]['status']);
        $this->assertSame('active', $days[2]['status']);
    }

    // --- One completion per local calendar day ------------------------------------

    public function test_a_second_day_cannot_be_completed_on_the_same_local_day(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->postAnswer($user, 1, 'UTC')->assertCreated();

        // Day 2 is now active, but it's still the same local day for this timezone.
        $response = $this->postAnswer($user, 2, 'UTC');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_COMPLETED_TODAY');

        $this->assertDatabaseCount('diary_entries', 1);
    }

    public function test_completing_a_day_is_allowed_again_once_the_local_date_changes(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'UTC'));
        $this->postAnswer($user, 1, 'UTC')->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-06-16 10:00:00', 'UTC'));
        $response = $this->postAnswer($user, 2, 'UTC');

        $response->assertCreated()
            ->assertJsonPath('data.answer_text', 'My answer');

        $this->assertDatabaseCount('diary_entries', 2);
    }

    /**
     * The client sends the device's timezone on every request because the user may
     * be travelling. This freezes a single instant and shows that a user who has
     * crossed into a new local calendar day (as reported by a new device timezone)
     * may complete another day, even though the underlying UTC instant is unchanged.
     */
    public function test_a_traveling_user_crossing_into_a_new_local_day_can_complete_another_day(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        // A single frozen instant, late in the day UTC.
        Carbon::setTestNow(Carbon::parse('2026-06-15 23:30:00', 'UTC'));

        // Pacific/Niue is UTC-11: local date is still 2026-06-15.
        $this->postAnswer($user, 1, 'Pacific/Niue')->assertCreated();

        // Pacific/Kiritimati is UTC+14: local date is already 2026-06-16, a new day
        // for this device even though no time has passed on the server clock.
        $response = $this->postAnswer($user, 2, 'Pacific/Kiritimati');

        $response->assertCreated();
        $this->assertDatabaseCount('diary_entries', 2);
    }

    /**
     * Same frozen instant, but the second request reports the *same* device
     * timezone as the first, so it is still the same local day and is rejected.
     */
    public function test_repeating_the_same_timezone_at_the_same_instant_is_still_blocked(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        Carbon::setTestNow(Carbon::parse('2026-06-15 23:30:00', 'UTC'));

        $this->postAnswer($user, 1, 'Pacific/Niue')->assertCreated();

        $response = $this->postAnswer($user, 2, 'Pacific/Niue');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_COMPLETED_TODAY');
    }
}
