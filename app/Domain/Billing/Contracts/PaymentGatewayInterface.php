<?php

namespace App\Domain\Billing\Contracts;

use App\Models\Subscription;
use App\Models\User;

interface PaymentGatewayInterface
{
    /**
     * Start a checkout for the given plan and return the data the payment widget needs
     * (a hosted checkout URL and/or a token).
     *
     * @return array<string, mixed>
     */
    public function createCheckoutSession(User $user, string $plan, string $currency, string $country): array;

    /**
     * Cancel the subscription on the payment provider's side.
     */
    public function cancelSubscription(Subscription $subscription): void;

    /**
     * Start a payment-method update and return the data the payment widget needs
     * (a hosted form URL and/or a token).
     *
     * @return array<string, mixed>
     */
    public function updatePaymentMethod(Subscription $subscription): array;
}
