<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Support\PlanCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Http\Resources\SubscriptionResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Billing', description: 'Subscription plans, checkout, cancellation, payment method updates and invoices. `cancel` and `payment-method` act on the user\'s current active subscription (see `Subscription::isActive()`) and return `404 SUBSCRIPTION_NOT_FOUND` if there is none.')]
class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    /**
     * List the available subscription plans and their pricing.
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => PlanCatalog::all(),
        ]);
    }

    /**
     * Start a checkout session for a plan, creating a pending subscription
     * and returning provider-specific checkout details (e.g. a Stripe
     * client secret) the client uses to complete payment.
     */
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

    /**
     * Cancel the user's current subscription (remains active until the
     * period end; does not refund).
     *
     * `404 SUBSCRIPTION_NOT_FOUND` is returned if the user has no active
     * subscription (see `Subscription::isActive()`).
     */
    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->billingService->activeSubscriptionFor($request->user());

        $this->billingService->cancelSubscription($subscription);

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->fresh()),
        ]);
    }

    /**
     * Update the payment method on file for the user's current subscription.
     *
     * `404 SUBSCRIPTION_NOT_FOUND` is returned if the user has no active
     * subscription (see `Subscription::isActive()`).
     */
    public function paymentMethod(Request $request): JsonResponse
    {
        $subscription = $this->billingService->activeSubscriptionFor($request->user());

        return response()->json([
            'payment_method' => $this->billingService->updatePaymentMethod($subscription),
        ]);
    }
}
