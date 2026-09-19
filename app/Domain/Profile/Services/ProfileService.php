<?php

namespace App\Domain\Profile\Services;

use App\Domain\Profile\Exceptions\DeletionAlreadyRequestedException;
use App\Domain\Profile\Exceptions\InvalidOldPasswordException;
use App\Models\DeletionRequest;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    private const DELETION_GRACE_PERIOD_DAYS = 30;

    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {
    }

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

        // A partial unique index on (user_id) WHERE status = 'pending' backs this
        // up at the database level: if two requests race past the exists() check
        // above, the second insert fails and is translated into the same domain
        // exception instead of creating a duplicate pending request.
        try {
            return $user->deletionRequests()->create([
                'status' => 'pending',
                'reason' => $reason,
                'scheduled_for' => now()->addDays(self::DELETION_GRACE_PERIOD_DAYS),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new DeletionAlreadyRequestedException();
        }
    }

    /**
     * Delete the account immediately. Any deletion request(s) still pending
     * for this user (from the grace-period flow) are cancelled so they are
     * not later picked up by app:process-deletion-requests for an account
     * that no longer exists. Personal data is purged the same way as the
     * grace-period flow (see AccountDeletionService::purgePersonalData()),
     * keeping subscriptions/invoices intact for accounting purposes.
     */
    public function deleteNow(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->deletionRequests()->where('status', 'pending')->update(['status' => 'cancelled']);
            $this->accountDeletionService->purgePersonalData($user);
            $user->delete();
        });
    }
}
