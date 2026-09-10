<?php

namespace App\Domain\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InvalidProviderTokenException extends Exception
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The provided social login token is invalid or expired.', 0, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_PROVIDER_TOKEN',
                'message' => 'The provided social login token is invalid or expired.',
                'fields' => (object) [],
            ],
        ], 401);
    }
}
