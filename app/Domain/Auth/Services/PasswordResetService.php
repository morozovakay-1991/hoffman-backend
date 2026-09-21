<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\CodeExpiredException;
use App\Domain\Auth\Exceptions\EmailNotFoundException;
use App\Domain\Auth\Exceptions\InvalidCodeException;
use App\Domain\Auth\Exceptions\TooManyAttemptsException;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Support\OtpCodeGenerator;

class PasswordResetService
{
    private const CODE_TTL_MINUTES = 5;

    /**
     * Generate a fresh reset code for the given email and invalidate any previous one.
     *
     * Silently does nothing when no account matches the email, so the response
     * given to the caller cannot be used to enumerate registered accounts.
     */
    public function forgot(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        PasswordResetCode::where('email', $email)->delete();

        $code = OtpCodeGenerator::generate();

        PasswordResetCode::create([
            'email' => $email,
            'code' => $code,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'verified_at' => null,
            'used_at' => null,
        ]);

        $user->notify(new PasswordResetCodeNotification($code, self::CODE_TTL_MINUTES));
    }

    /**
     * Verify a reset code without consuming it, so it can be reused by the reset step.
     *
     * @throws EmailNotFoundException
     * @throws TooManyAttemptsException
     * @throws CodeExpiredException
     * @throws InvalidCodeException
     */
    public function verifyCode(string $email, string $code): void
    {
        $this->findUserOrFail($email);

        $resetCode = $this->findActiveCodeOrFail($email);

        if ($resetCode->code !== $code) {
            $resetCode->increment('attempts');

            throw new InvalidCodeException();
        }

        $resetCode->update(['verified_at' => now()]);
    }

    /**
     * Reset the user's password using a previously verified code.
     *
     * @throws EmailNotFoundException
     * @throws TooManyAttemptsException
     * @throws CodeExpiredException
     * @throws InvalidCodeException
     */
    public function reset(string $email, string $password): void
    {
        $user = $this->findUserOrFail($email);

        $resetCode = $this->findActiveCodeOrFail($email);

        if ($resetCode->verified_at === null) {
            throw new InvalidCodeException();
        }

        $user->update(['password' => $password]);

        $resetCode->update(['used_at' => now()]);
    }

    /**
     * @throws EmailNotFoundException
     */
    private function findUserOrFail(string $email): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new EmailNotFoundException();
        }

        return $user;
    }

    /**
     * @throws TooManyAttemptsException
     * @throws CodeExpiredException
     * @throws InvalidCodeException
     */
    private function findActiveCodeOrFail(string $email): PasswordResetCode
    {
        $resetCode = PasswordResetCode::where('email', $email)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $resetCode) {
            throw new InvalidCodeException();
        }

        if ($resetCode->hasTooManyAttempts()) {
            throw new TooManyAttemptsException();
        }

        if ($resetCode->isExpired()) {
            throw new CodeExpiredException();
        }

        return $resetCode;
    }
}
