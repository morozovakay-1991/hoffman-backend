<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidCredentialsException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials are incorrect.',
                'fields' => (object) [],
            ],
        ], 401);
    }
}
