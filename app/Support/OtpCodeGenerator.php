<?php

namespace App\Support;

class OtpCodeGenerator
{
    /**
     * Generate a random 6-digit one-time code, zero-padded on the left.
     */
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
