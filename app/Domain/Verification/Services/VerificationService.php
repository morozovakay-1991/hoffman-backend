<?php

namespace App\Domain\Verification\Services;

use App\Domain\Verification\Contracts\GraduateDirectoryProviderInterface;
use App\Enums\GraduateStatus;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\VerificationRequest;

class VerificationService
{
    public function __construct(private readonly GraduateDirectoryProviderInterface $graduateDirectoryProvider)
    {
    }

    /**
     * Create or update the user's verification request and auto-confirm it on a directory match.
     */
    public function submit(User $user, string $lastName, string $firstName, string $phone): VerificationRequest
    {
        $isMatch = $this->graduateDirectoryProvider->matches($lastName, $firstName, $phone);

        $verificationRequest = VerificationRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'phone' => $phone,
                'status' => $isMatch ? VerificationStatus::Confirmed : VerificationStatus::Pending,
            ],
        );

        if ($isMatch) {
            $user->update(['graduate_status' => GraduateStatus::Confirmed]);
        }

        return $verificationRequest;
    }

    public function status(User $user): ?VerificationRequest
    {
        return $user->verificationRequests()->latest('id')->first();
    }

    /**
     * Confirm a verification request and mark the applicant as a confirmed graduate.
     */
    public function confirm(VerificationRequest $verificationRequest, User $reviewer): VerificationRequest
    {
        $verificationRequest->update([
            'status' => VerificationStatus::Confirmed,
            'reviewer_id' => $reviewer->id,
            'rejection_reason' => null,
            'reviewed_at' => now(),
        ]);

        $verificationRequest->user->update(['graduate_status' => GraduateStatus::Confirmed]);

        return $verificationRequest;
    }

    /**
     * Reject a verification request and mark the applicant's graduate status as rejected.
     */
    public function reject(VerificationRequest $verificationRequest, User $reviewer, string $reason): VerificationRequest
    {
        $verificationRequest->update([
            'status' => VerificationStatus::Rejected,
            'reviewer_id' => $reviewer->id,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);

        $verificationRequest->user->update(['graduate_status' => GraduateStatus::Rejected]);

        return $verificationRequest;
    }
}
