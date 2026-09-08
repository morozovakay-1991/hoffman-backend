<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscriptions()->latest('id')->first();

        if (! $subscription) {
            return response()->json([
                'subscription' => [
                    'status' => 'none',
                    'provider' => null,
                    'product_id' => null,
                    'auto_renew' => false,
                    'is_active' => false,
                    'starts_at' => null,
                    'trial_ends_at' => null,
                    'expires_at' => null,
                    'cancelled_at' => null,
                ],
            ]);
        }

        return response()->json([
            'subscription' => new SubscriptionResource($subscription),
        ]);
    }
}
