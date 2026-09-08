<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Exceptions\SubscriptionNotFoundException;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Support\PlanCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => PlanCatalog::all(),
        ]);
    }

    public function checkoutSession(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $result = $this->billingService->createCheckoutSession(
            $request->user(),
            $request->validated('plan'),
            $request->validated('currency'),
            $request->validated('country'),
        );

        return response()->json([
            'subscription' => new SubscriptionResource($result['subscription']),
            'checkout' => $result['checkout'],
        ], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->currentSubscription($request);

        $this->billingService->cancelSubscription($subscription);

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->fresh()),
        ]);
    }

    public function paymentMethod(Request $request): JsonResponse
    {
        $subscription = $this->currentSubscription($request);

        return response()->json([
            'payment_method' => $this->billingService->updatePaymentMethod($subscription),
        ]);
    }

    /**
     * @throws SubscriptionNotFoundException
     */
    private function currentSubscription(Request $request): Subscription
    {
        $subscription = $request->user()->subscriptions()
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->latest('id')
            ->first();

        if (! $subscription) {
            throw new SubscriptionNotFoundException();
        }

        return $subscription;
    }
}
