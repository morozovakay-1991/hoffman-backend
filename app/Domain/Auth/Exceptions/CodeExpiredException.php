<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeExpiredException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CODE_EXPIRED',
                'message' => 'The provided reset code has expired.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
