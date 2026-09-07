<?php

namespace App\Domain\Verification\Providers;

use App\Domain\Verification\Contracts\GraduateDirectoryProviderInterface;
use App\Models\GraduateDirectory;

/**
 * Matches identities against the graduate_directory table, populated via CSV import.
 */
class CsvGraduateDirectoryProvider implements GraduateDirectoryProviderInterface
{
    public function matches(string $lastName, string $firstName, string $phone): bool
    {
        $normalizedLastName = $this->normalizeName($lastName);
        $normalizedFirstName = $this->normalizeName($firstName);
        $normalizedPhone = $this->normalizePhone($phone);

        return GraduateDirectory::query()
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$normalizedLastName])
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$normalizedFirstName])
            ->get(['phone'])
            ->contains(fn (GraduateDirectory $graduate): bool => $this->normalizePhone($graduate->phone) === $normalizedPhone);
    }

    private function normalizeName(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Strip everything but digits and compare by the last 10 digits, so that
     * "+7 (900) 123-45-67", "89001234567" and "9001234567" are all equivalent.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return substr($digits, -10);
    }
}
