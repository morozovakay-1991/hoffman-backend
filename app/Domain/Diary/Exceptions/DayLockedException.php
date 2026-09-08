<?php

namespace App\Domain\Diary\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DayLockedException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'DAY_LOCKED',
                'message' => 'This diary day is not open yet.',
                'fields' => (object) [],
            ],
        ], 403);
    }
}
