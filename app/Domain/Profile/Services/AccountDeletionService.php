<?php

namespace App\Domain\Profile\Services;

use App\Models\DeletionRequest;
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
                $user->tokens()->delete();
                $user->diaryEntries()->delete();
                $user->verificationRequests()->delete();
                $user->notificationSettings()->delete();
                $user->emailChangeRequests()->delete();

                if (! $user->trashed()) {
                    $user->delete();
                }
            }

            $deletionRequest->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
        });
    }
}
