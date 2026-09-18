<?php

namespace App\Domain\Diary\Services;

use App\Domain\Diary\Exceptions\AccessDeniedException;
use App\Domain\Diary\Exceptions\AlreadyCompletedTodayException;
use App\Domain\Diary\Exceptions\DayLockedException;
use App\Enums\GraduateStatus;
use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Drives the 100-day practice diary: a strictly sequential, one-day-per-day
 * program available only to confirmed graduates. Progress is derived from
 * the number of DiaryEntry rows a user has (there is no separate "current
 * day" counter to keep in sync), so the day right after the highest
 * completed day number is always the single "active" day, everything
 * before it is "completed", and everything after it is "locked".
 */
class DiaryService
{
    public const STATUS_LOCKED = 'locked';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    /**
     * How much faster than real elapsed time a user's reported UTC offset is
     * allowed to change between two requests. A genuine traveller's local
     * offset can only shift as fast as they physically move between
     * timezones, so a jump from e.g. UTC-11 to UTC+14 is plausible after a
     * long-haul flight but not between two requests that are seconds apart.
     * The grace hours absorb layovers, boarding time and the fact that
     * timezone boundaries don't align with travel time to the minute,
     * without opening the door back up to an instant, no-travel swap.
     */
    private const TIMEZONE_JUMP_GRACE_HOURS = 3;

    /**
     * Return all 100 diary days for the user, each flagged with its status.
     *
     * @return Collection<int, DiaryDay>
     */
    public function getDaysForUser(User $user): Collection
    {
        $this->assertGraduate($user);

        $completedCount = $this->completedDaysCount($user);

        $days = DiaryDay::query()->orderBy('day_number')->get();

        $days->each(function (DiaryDay $day) use ($completedCount) {
            $day->setAttribute('status', $this->statusFor($day->day_number, $completedCount));
        });

        return $days;
    }

    /**
     * Return a single day's detail, with its status and (if completed) the
     * user's own answer. Throws DayLockedException if the day is ahead of
     * the user's current progress.
     */
    public function getDayDetail(User $user, int $dayNumber): DiaryDay
    {
        $this->assertGraduate($user);

        $day = DiaryDay::query()->where('day_number', $dayNumber)->firstOrFail();

        $completedCount = $this->completedDaysCount($user);
        $status = $this->statusFor($day->day_number, $completedCount);

        if ($status === self::STATUS_LOCKED) {
            throw new DayLockedException();
        }

        $day->setAttribute('status', $status);
        $day->setAttribute(
            'entry',
            $user->diaryEntries()->where('diary_day_id', $day->id)->first(),
        );

        return $day;
    }

    /**
     * Record the user's answer for a day, advancing their progress.
     *
     * Only the user's current active day can be answered: attempting a day
     * out of order (ahead of or already past the active day) is rejected
     * with DayLockedException. A user may complete at most one day per
     * local calendar day, determined by the timezone the client sends with
     * the request (the user's device timezone, since they may be
     * travelling); a second attempt on the same local day is rejected with
     * AlreadyCompletedTodayException regardless of which day it targets.
     *
     * The `timezone` string is trusted client input (validated only for
     * being a real IANA identifier), so a client could otherwise send a
     * different timezone on each request purely to make it look like the
     * local calendar date has advanced, without any real time having
     * passed, and complete two days "the same day" for real-world purposes.
     * assertPlausibleTimezoneChange() closes that gap by rejecting a
     * reported UTC-offset change that outruns the real elapsed time since
     * the user's last entry.
     */
    public function saveAnswer(User $user, int $dayNumber, string $answer, string $timezone): DiaryEntry
    {
        $this->assertGraduate($user);

        $day = DiaryDay::query()->where('day_number', $dayNumber)->firstOrFail();

        $completedCount = $this->completedDaysCount($user);
        $status = $this->statusFor($day->day_number, $completedCount);

        if ($status !== self::STATUS_ACTIVE) {
            throw new DayLockedException();
        }

        $lastEntry = $user->diaryEntries()->latest('completed_at')->first();

        $this->assertPlausibleTimezoneChange($lastEntry, $timezone);

        $today = Carbon::now($timezone)->toDateString();

        $alreadyCompletedToday = $user->diaryEntries()
            ->whereDate('completed_date', $today)
            ->exists();

        if ($alreadyCompletedToday) {
            throw new AlreadyCompletedTodayException();
        }

        return DiaryEntry::create([
            'user_id' => $user->id,
            'diary_day_id' => $day->id,
            'answer_text' => $answer,
            'completed_date' => $today,
            'completed_at' => Carbon::now($timezone),
            'timezone' => $timezone,
        ]);
    }

    /**
     * Reject a timezone whose reported UTC offset has shifted more than the
     * real elapsed time since the user's last entry could plausibly
     * explain (plus TIMEZONE_JUMP_GRACE_HOURS of slack). This is what stops
     * "send timezone A, then immediately send timezone B where it's
     * already tomorrow" from unlocking a second entry for the same real
     * day, while still letting a traveller who genuinely changed timezone
     * over the course of a real trip through.
     */
    private function assertPlausibleTimezoneChange(?DiaryEntry $lastEntry, string $timezone): void
    {
        if ($lastEntry === null || $lastEntry->timezone === null) {
            return;
        }

        $elapsedHours = $lastEntry->completed_at->diffInMinutes(Carbon::now()) / 60;

        $offsetDeltaHours = abs(
            Carbon::now($timezone)->utcOffset() - Carbon::now($lastEntry->timezone)->utcOffset()
        ) / 60;

        if ($offsetDeltaHours > $elapsedHours + self::TIMEZONE_JUMP_GRACE_HOURS) {
            throw new AlreadyCompletedTodayException();
        }
    }

    private function assertGraduate(User $user): void
    {
        if ($user->graduate_status !== GraduateStatus::Confirmed) {
            throw new AccessDeniedException();
        }
    }

    private function completedDaysCount(User $user): int
    {
        return $user->diaryEntries()->count();
    }

    private function statusFor(int $dayNumber, int $completedCount): string
    {
        if ($dayNumber <= $completedCount) {
            return self::STATUS_COMPLETED;
        }

        if ($dayNumber === $completedCount + 1) {
            return self::STATUS_ACTIVE;
        }

        return self::STATUS_LOCKED;
    }
}
