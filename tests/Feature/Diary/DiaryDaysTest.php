<?php

namespace Tests\Feature\Diary;

use App\Enums\GraduateStatus;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryDaysTest extends TestCase
{
    use RefreshDatabase;

    private function seedDays(int $count = 100): void
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

    // --- GET /api/v1/diary/days -------------------------------------------------

    public function test_guest_is_rejected(): void
    {
        $this->seedDays();

        $response = $this->getJson('/api/v1/diary/days');

        $response->assertStatus(401);
    }

    public function test_non_confirmed_graduate_is_denied_access_to_the_days_list(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_pending_graduate_status_is_also_denied(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Pending)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_confirmed_graduate_with_no_progress_sees_only_day_one_active(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days');

        $response->assertOk();

        $days = collect($response->json('data'))->keyBy('day_number');

        $this->assertSame('active', $days[1]['status']);
        $this->assertSame('locked', $days[2]['status']);
        $this->assertSame('locked', $days[100]['status']);
        $this->assertCount(100, $days);
    }

    public function test_confirmed_graduate_progress_is_reflected_in_day_statuses(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $this->completeDay($user, 1);
        $this->completeDay($user, 2);
        $this->completeDay($user, 3);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days');

        $response->assertOk();

        $days = collect($response->json('data'))->keyBy('day_number');

        $this->assertSame('completed', $days[1]['status']);
        $this->assertSame('completed', $days[2]['status']);
        $this->assertSame('completed', $days[3]['status']);
        $this->assertSame('active', $days[4]['status']);
        $this->assertSame('locked', $days[5]['status']);
    }

    public function test_locked_days_do_not_expose_their_task_text_in_the_list(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days');

        $days = collect($response->json('data'))->keyBy('day_number');

        $this->assertNull($days[2]['task_text']);
        $this->assertNotNull($days[1]['task_text']);
    }

    // --- GET /api/v1/diary/days/{n} ----------------------------------------------

    public function test_guest_is_rejected_from_day_detail(): void
    {
        $this->seedDays();

        $response = $this->getJson('/api/v1/diary/days/1');

        $response->assertStatus(401);
    }

    public function test_non_confirmed_graduate_is_denied_day_detail(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days/1');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_confirmed_graduate_can_view_the_active_day(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days/1');

        $response->assertOk()
            ->assertJsonPath('data.day_number', 1)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.answer', null);
    }

    public function test_confirmed_graduate_can_view_a_completed_day_with_their_answer(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();
        $this->completeDay($user, 1);

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days/1');

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.answer.answer_text', 'Answer for day 1');
    }

    public function test_attempting_to_open_a_day_out_of_order_is_locked(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        // Progress is on day 1, so day 5 has not been reached yet.
        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days/5');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'DAY_LOCKED');
    }

    public function test_unknown_day_number_is_not_found(): void
    {
        $this->seedDays();
        $user = User::factory()->graduateStatus(GraduateStatus::Confirmed)->create();

        $response = $this->actingAsApiUser($user)->getJson('/api/v1/diary/days/999');

        $response->assertStatus(404);
    }
}
