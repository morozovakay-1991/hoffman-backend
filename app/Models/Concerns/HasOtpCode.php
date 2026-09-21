<?php

namespace App\Models\Concerns;

trait HasOtpCode
{
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasTooManyAttempts(): bool
    {
        return $this->attempts >= static::MAX_ATTEMPTS;
    }
}
