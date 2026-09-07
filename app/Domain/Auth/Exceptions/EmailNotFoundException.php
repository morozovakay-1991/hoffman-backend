<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailNotFoundException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'EMAIL_NOT_FOUND',
                'message' => 'No account was found for the provided email address.',
                'fields' => (object) [],
            ],
        ], 404);
    }
}
