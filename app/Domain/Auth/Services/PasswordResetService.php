<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\CodeExpiredException;
use App\Domain\Auth\Exceptions\EmailNotFoundException;
use App\Domain\Auth\Exceptions\InvalidCodeException;
use App\Domain\Auth\Exceptions\TooManyAttemptsException;
use App\Models\PasswordResetCode;
use App\Models\User;

class PasswordResetService
{
    private const CODE_TTL_MINUTES = 5;

    /**
     * Generate a fresh reset code for the given email and invalidate any previous one.
     *
     * @throws EmailNotFoundException
     */
    public function forgot(string $email): void
    {
        $this->findUserOrFail($email);

        PasswordResetCode::where('email', $email)->delete();

        PasswordResetCode::create([
            'email' => $email,
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'verified_at' => null,
            'used_at' => null,
        ]);
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
