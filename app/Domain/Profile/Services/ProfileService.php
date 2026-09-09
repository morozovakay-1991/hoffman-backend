<?php

namespace App\Domain\Profile\Services;

use App\Domain\Profile\Exceptions\DeletionAlreadyRequestedException;
use App\Domain\Profile\Exceptions\InvalidOldPasswordException;
use App\Models\DeletionRequest;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    private const DELETION_GRACE_PERIOD_DAYS = 30;

    /**
     * @param  array{name?: string, timezone?: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    /**
     * @throws InvalidOldPasswordException
     */
    public function updatePassword(User $user, string $oldPassword, string $newPassword): void
    {
        if (! Hash::check($oldPassword, $user->password)) {
            throw new InvalidOldPasswordException();
        }

        $user->update(['password' => $newPassword]);
    }

    /**
     * @param  array{push_enabled?: bool, email_enabled?: bool, marketing_enabled?: bool, daily_practices_enabled?: bool, new_articles_enabled?: bool, system_enabled?: bool}  $data
     */
    public function updateNotifications(User $user, array $data): NotificationSetting
    {
        $settings = NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'push_enabled' => true,
                'email_enabled' => true,
                'marketing_enabled' => false,
                'daily_practices_enabled' => true,
                'new_articles_enabled' => true,
                'system_enabled' => true,
            ],
        );
        $settings->fill($data);
        $settings->save();

        return $settings;
    }

    /**
     * @throws DeletionAlreadyRequestedException
     */
    public function requestDeletion(User $user, ?string $reason): DeletionRequest
    {
        $hasPendingRequest = $user->deletionRequests()->where('status', 'pending')->exists();

        if ($hasPendingRequest) {
            throw new DeletionAlreadyRequestedException();
        }

        return $user->deletionRequests()->create([
            'status' => 'pending',
            'reason' => $reason,
            'scheduled_for' => now()->addDays(self::DELETION_GRACE_PERIOD_DAYS),
        ]);
    }

    public function deleteNow(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
