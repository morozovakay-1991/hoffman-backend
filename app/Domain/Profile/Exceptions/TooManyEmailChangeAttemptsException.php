<?php

namespace App\Domain\Profile\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TooManyEmailChangeAttemptsException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'TOO_MANY_ATTEMPTS',
                'message' => 'Too many failed attempts. Please request a new code.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
