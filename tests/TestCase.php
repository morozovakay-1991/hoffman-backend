<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Return a test instance that authenticates API requests as the given user via
     * a Sanctum bearer token, matching how the mobile clients authenticate.
     */
    protected function actingAsApiUser(User $user): static
    {
        $token = $user->createToken('api_token')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
