<?php

namespace App\Domain\Verification\Contracts;

use App\Models\GraduateDirectory;

interface GraduateDirectoryProviderInterface
{
    /**
     * Find the graduate directory entry matching the given identity, if any.
     */
    public function findMatch(string $lastName, string $firstName, string $phone): ?GraduateDirectory;
}
