<?php

namespace App\Domain\Profile\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOldPasswordException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_OLD_PASSWORD',
                'message' => 'The provided current password is incorrect.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
