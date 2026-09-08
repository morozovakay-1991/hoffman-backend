<?php

namespace App\Domain\Content\Exceptions;

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
                'message' => 'This content requires an active subscription.',
                'fields' => (object) [],
            ],
        ], 403);
    }
}
