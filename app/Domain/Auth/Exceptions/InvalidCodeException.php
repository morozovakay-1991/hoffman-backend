<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidCodeException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CODE',
                'message' => 'The provided reset code is invalid.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
