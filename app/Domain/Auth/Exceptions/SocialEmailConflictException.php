<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialEmailConflictException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SOCIAL_EMAIL_CONFLICT',
                'message' => 'An account with this email already exists. Log in with your password instead.',
                'fields' => (object) [],
            ],
        ], 409);
    }
}
