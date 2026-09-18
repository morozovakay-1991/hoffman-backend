<?php

namespace Tests\Feature\Diary;

use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryEntryUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The unique index on diary_entries(user_id, diary_day_id) is the safety
     * net a duplicate insert ultimately hits, regardless of whether
     * DiaryService's application-level checks were bypassed (e.g. by a race
     * between two concurrent requests). forceCreate() skips the service
     * layer entirely and writes straight through Eloquent, so a rejection
     * here can only come from the database constraint itself.
     */
    public function test_a_second_diary_entry_for_the_same_user_and_day_is_rejected_at_the_database_level(): void
    {
        $user = User::factory()->create();
        $day = DiaryDay::factory()->create();

        DiaryEntry::forceCreate(DiaryEntry::factory()->raw([
            'user_id' => $user->id,
            'diary_day_id' => $day->id,
        ]));

        $this->expectException(UniqueConstraintViolationException::class);

        DiaryEntry::forceCreate(DiaryEntry::factory()->raw([
            'user_id' => $user->id,
            'diary_day_id' => $day->id,
        ]));
    }

    /**
     * The constraint is scoped to (user_id, diary_day_id), not to either
     * column alone — different users must remain free to each have their
     * own entry for the same day, and one user must be free to complete
     * multiple different days.
     */
    public function test_different_users_can_have_entries_for_the_same_day(): void
    {
        $day = DiaryDay::factory()->create();

        DiaryEntry::forceCreate(DiaryEntry::factory()->raw([
            'user_id' => User::factory()->create()->id,
            'diary_day_id' => $day->id,
        ]));
        DiaryEntry::forceCreate(DiaryEntry::factory()->raw([
            'user_id' => User::factory()->create()->id,
            'diary_day_id' => $day->id,
        ]));

        $this->assertDatabaseCount('diary_entries', 2);
    }
}
