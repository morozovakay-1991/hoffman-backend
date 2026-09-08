<?php

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\Support\PlanCatalog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudPaymentsGateway implements PaymentGatewayInterface
{
    public function createCheckoutSession(User $user, string $plan, string $currency, string $country): array
    {
        $planData = PlanCatalog::find($plan);
        $amount = PlanCatalog::priceFor($plan, $currency);

        $response = $this->client()->post('/orders/create', [
            'Amount' => $amount !== null ? round($amount / 100, 2) : null,
            'Currency' => strtoupper($currency),
            'Description' => $planData['name'] ?? $plan,
            'Email' => $user->email,
            'AccountId' => (string) $user->id,
            'InvoiceId' => (string) Str::uuid(),
        ])->throw()->json();

        return [
            'checkout_url' => $response['Model']['Url'] ?? null,
            'invoice_id' => $response['Model']['Number'] ?? null,
        ];
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        if ($subscription->external_subscription_id !== null) {
            $this->client()->post('/subscriptions/cancel', [
                'Id' => $subscription->external_subscription_id,
            ])->throw();
        }
    }

    public function updatePaymentMethod(Subscription $subscription): array
    {
        // CloudPayments has no dedicated "update card" endpoint; a nominal
        // authorization order is the documented way to collect a new card
        // via the hosted widget and link it to the customer's account.
        $response = $this->client()->post('/orders/create', [
            'Amount' => 1,
            'Currency' => $subscription->currency ?? 'RUB',
            'Description' => 'Обновление способа оплаты',
            'AccountId' => (string) $subscription->user_id,
            'InvoiceId' => (string) Str::uuid(),
        ])->throw()->json();

        return [
            'checkout_url' => $response['Model']['Url'] ?? null,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.cloudpayments.base_url'))
            ->withBasicAuth(
                (string) config('services.cloudpayments.public_id'),
                (string) config('services.cloudpayments.api_secret'),
            )
            ->acceptJson();
    }
}
