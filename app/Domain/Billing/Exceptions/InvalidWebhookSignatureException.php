<?php

namespace App\Domain\Billing\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidWebhookSignatureException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_WEBHOOK_SIGNATURE',
                'message' => 'The webhook signature could not be verified.',
                'fields' => (object) [],
            ],
        ], 400);
    }
}
