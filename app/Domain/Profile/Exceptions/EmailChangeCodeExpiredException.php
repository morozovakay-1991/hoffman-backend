<?php

namespace App\Domain\Profile\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailChangeCodeExpiredException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CODE_EXPIRED',
                'message' => 'The provided confirmation code has expired.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
