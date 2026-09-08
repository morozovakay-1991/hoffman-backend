<?php

namespace App\Domain\Profile\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidEmailChangeCodeException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CODE',
                'message' => 'The provided confirmation code is invalid.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
