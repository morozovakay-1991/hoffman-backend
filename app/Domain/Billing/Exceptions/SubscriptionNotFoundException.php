<?php

namespace App\Domain\Billing\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionNotFoundException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'SUBSCRIPTION_NOT_FOUND',
                'message' => 'No active subscription was found for this account.',
                'fields' => (object) [],
            ],
        ], 404);
    }
}
