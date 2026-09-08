<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\Gateways\CloudPaymentsGateway;
use App\Domain\Billing\Gateways\StripeGateway;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\App;

class BillingService
{
    /**
     * Resolve which payment gateway handles billing for the given country.
     * Russia is served by CloudPayments; every other country by Stripe.
     */
    public function resolveGateway(string $country): PaymentGatewayInterface
    {
        return strtoupper($country) === 'RU'
            ? App::make(CloudPaymentsGateway::class)
            : App::make(StripeGateway::class);
    }

    /**
     * @return array{subscription: Subscription, checkout: array<string, mixed>}
     */
    public function createCheckoutSession(User $user, string $plan, string $currency, string $country): array
    {
        $gateway = $this->resolveGateway($country);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'payment_provider' => $this->providerName($gateway),
            'product_id' => $plan,
            'status' => 'pending',
            'auto_renew' => true,
            'currency' => strtoupper($currency),
            'country' => strtoupper($country),
        ]);

        $checkout = $gateway->createCheckoutSession($user, $plan, $currency, $country);

        $subscription->fill([
            'external_customer_id' => $checkout['customer_id'] ?? $checkout['invoice_id'] ?? null,
        ])->save();

        return [
            'subscription' => $subscription,
            'checkout' => $checkout,
        ];
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        $gateway = $this->resolveGateway($subscription->country ?? 'US');
        $gateway->cancelSubscription($subscription);

        $subscription->update([
            'status' => 'cancelled',
            'auto_renew' => false,
            'cancelled_at' => now(),
            'cancel_at' => $subscription->expires_at ?? now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePaymentMethod(Subscription $subscription): array
    {
        $gateway = $this->resolveGateway($subscription->country ?? 'US');

        return $gateway->updatePaymentMethod($subscription);
    }

    private function providerName(PaymentGatewayInterface $gateway): string
    {
        return $gateway instanceof CloudPaymentsGateway ? 'cloudpayments' : 'stripe';
    }
}
