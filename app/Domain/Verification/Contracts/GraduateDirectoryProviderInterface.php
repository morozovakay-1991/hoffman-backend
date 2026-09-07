<?php

namespace App\Domain\Verification\Contracts;

interface GraduateDirectoryProviderInterface
{
    /**
     * Determine whether the given identity matches an entry in the graduate directory.
     */
    public function matches(string $lastName, string $firstName, string $phone): bool;
}
