<?php

namespace App\Domain\Profile\Services;

use App\Enums\DeletionRequestStatus;
use App\Models\DeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    /**
     * Purge a user's related data, revoke their tokens, and mark the deletion
     * request as completed. The user account itself is soft-deleted rather
     * than removed outright, consistent with ProfileService::deleteNow().
     */
    public function process(DeletionRequest $deletionRequest): void
    {
        DB::transaction(function () use ($deletionRequest) {
            $user = $deletionRequest->user()->withTrashed()->first();

            if ($user !== null) {
                $this->purgePersonalData($user);

                if (! $user->trashed()) {
                    $user->delete();
                }
            }

            $deletionRequest->forceFill([
                'status' => DeletionRequestStatus::Completed,
                'completed_at' => now(),
            ])->save();
        });
    }

    /**
     * Remove a user's personal data ahead of the account itself being
     * deleted, whether that happens immediately or after the grace period.
     * Subscriptions and invoices are intentionally kept for accounting
     * purposes.
     */
    public function purgePersonalData(User $user): void
    {
        $user->tokens()->delete();
        $user->diaryEntries()->delete();
        $user->verificationRequests()->delete();
        $user->notificationSettings()->delete();
        $user->emailChangeRequests()->delete();
    }
}
