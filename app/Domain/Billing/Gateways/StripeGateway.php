<?php

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\Support\PlanCatalog;
use App\Models\Subscription;
use App\Models\User;
use Stripe\StripeClient;

class StripeGateway implements PaymentGatewayInterface
{
    private readonly StripeClient $client;

    public function __construct(?StripeClient $client = null)
    {
        $this->client = $client ?? new StripeClient((string) config('services.stripe.secret'));
    }

    public function createCheckoutSession(User $user, string $plan, string $currency, string $country): array
    {
        $planData = PlanCatalog::find($plan);

        $session = $this->client->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $user->email,
            'client_reference_id' => (string) $user->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => PlanCatalog::priceFor($plan, $currency),
                    'recurring' => ['interval' => $planData['interval'] ?? 'month'],
                    'product_data' => ['name' => $planData['name'] ?? $plan],
                ],
                'quantity' => 1,
            ]],
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
        ]);

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'customer_id' => $session->customer,
        ];
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        if ($subscription->external_subscription_id !== null) {
            $this->client->subscriptions->cancel($subscription->external_subscription_id);
        }
    }

    public function updatePaymentMethod(Subscription $subscription): array
    {
        $session = $this->client->billingPortal->sessions->create([
            'customer' => $subscription->external_customer_id,
            'return_url' => config('services.stripe.return_url'),
        ]);

        return [
            'checkout_url' => $session->url,
        ];
    }
}
