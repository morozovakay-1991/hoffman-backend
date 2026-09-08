<?php

namespace App\Domain\Profile\Services;

use App\Domain\Profile\Exceptions\EmailChangeCodeExpiredException;
use App\Domain\Profile\Exceptions\InvalidEmailChangeCodeException;
use App\Domain\Profile\Exceptions\TooManyEmailChangeAttemptsException;
use App\Models\EmailChangeRequest;
use App\Models\User;

class EmailChangeService
{
    private const CODE_TTL_MINUTES = 15;

    /**
     * Generate a fresh confirmation code for the requested new email address,
     * invalidating any previous unconfirmed request for this user.
     */
    public function requestChange(User $user, string $newEmail): EmailChangeRequest
    {
        EmailChangeRequest::where('user_id', $user->id)->whereNull('confirmed_at')->delete();

        return EmailChangeRequest::create([
            'user_id' => $user->id,
            'old_email' => $user->email,
            'new_email' => $newEmail,
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'confirmed_at' => null,
        ]);
    }

    /**
     * @throws TooManyEmailChangeAttemptsException
     * @throws EmailChangeCodeExpiredException
     * @throws InvalidEmailChangeCodeException
     */
    public function confirm(User $user, string $code): void
    {
        $changeRequest = EmailChangeRequest::where('user_id', $user->id)
            ->whereNull('confirmed_at')
            ->latest('id')
            ->first();

        if (! $changeRequest) {
            throw new InvalidEmailChangeCodeException();
        }

        if ($changeRequest->hasTooManyAttempts()) {
            throw new TooManyEmailChangeAttemptsException();
        }

        if ($changeRequest->isExpired()) {
            throw new EmailChangeCodeExpiredException();
        }

        if ($changeRequest->code !== $code) {
            $changeRequest->increment('attempts');

            throw new InvalidEmailChangeCodeException();
        }

        $user->update(['email' => $changeRequest->new_email]);

        $changeRequest->update(['confirmed_at' => now()]);
    }
}
