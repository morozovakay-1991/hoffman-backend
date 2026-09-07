<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountBlockedException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'This account has been blocked.',
                'fields' => (object) [],
            ],
        ], 403);
    }
}
