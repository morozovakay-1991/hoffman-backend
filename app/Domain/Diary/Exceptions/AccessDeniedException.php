<?php

namespace App\Domain\Diary\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessDeniedException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'ACCESS_DENIED',
                'message' => 'The practice diary is only available to confirmed graduates.',
                'fields' => (object) [],
            ],
        ], 403);
    }
}
