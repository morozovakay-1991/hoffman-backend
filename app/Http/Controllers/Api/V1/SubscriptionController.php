<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\BillingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Subscription', description: 'Read-only view of the authenticated user\'s current subscription. Managing a subscription (checking out, cancelling, invoices) lives under the Billing group.')]
class SubscriptionController extends Controller
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    /**
     * Show the authenticated user's most recent subscription, or a
     * `status: "none"` placeholder object if they have never subscribed.
     */
    public function show(Request $request): JsonResponse
    {
        $subscription = $this->billingService->latestSubscriptionFor($request->user());

        return response()->json([
            'subscription' => new SubscriptionResource($subscription),
        ]);
    }
}
