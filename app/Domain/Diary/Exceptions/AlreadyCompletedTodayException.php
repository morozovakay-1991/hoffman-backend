<?php

namespace App\Domain\Diary\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlreadyCompletedTodayException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'ALREADY_COMPLETED_TODAY',
                'message' => 'A diary entry has already been completed today.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
