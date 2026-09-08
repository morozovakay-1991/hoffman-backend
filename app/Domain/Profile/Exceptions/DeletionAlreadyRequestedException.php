<?php

namespace App\Domain\Profile\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeletionAlreadyRequestedException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'DELETION_ALREADY_REQUESTED',
                'message' => 'A data deletion request is already pending for this account.',
                'fields' => (object) [],
            ],
        ], 422);
    }
}
