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
}
