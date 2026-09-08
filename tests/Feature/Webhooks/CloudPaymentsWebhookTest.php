<?php

namespace Tests\Feature\Webhooks;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CloudPaymentsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'cp_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.cloudpayments.api_secret', self::SECRET);
    }

    public function test_a_valid_pay_notification_activates_the_subscription_once_even_if_sent_twice(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'cloudpayments',
            'product_id' => 'yearly',
            'status' => 'pending',
            'currency' => 'RUB',
            'country' => 'RU',
        ]);

        $data = $this->payNotificationData($user, 'Completed');

        $response = $this->sendCloudPaymentsWebhook($data);
        $response->assertOk()->assertJson(['code' => 0]);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('cp_sub_123', $subscription->external_subscription_id);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'provider' => 'cloudpayments',
            'status' => 'paid',
            'amount' => 699900,
        ]);
        $this->assertDatabaseCount('payment_webhook_events', 1);

        // CloudPayments retries deliveries, so the same TransactionId may arrive again.
        $repeat = $this->sendCloudPaymentsWebhook($data);
        $repeat->assertOk()->assertJson(['code' => 0]);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_a_fail_notification_does_not_activate_the_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'cloudpayments',
            'product_id' => 'yearly',
            'status' => 'pending',
        ]);

        $data = $this->payNotificationData($user, 'Declined');

        $response = $this->sendCloudPaymentsWebhook($data);
        $response->assertOk()->assertJson(['code' => 0]);

        $this->assertSame('pending', $subscription->refresh()->status);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_it_rejects_a_notification_with_an_invalid_signature(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'cloudpayments',
            'status' => 'pending',
        ]);

        $data = $this->payNotificationData($user, 'Completed');
        $body = http_build_query($data);

        $response = $this->call(
            'POST',
            '/api/webhooks/cloudpayments',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_HMAC' => base64_encode('not-the-right-signature'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            $body,
        );

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_SIGNATURE');

        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertSame('pending', $subscription->refresh()->status);
    }

    /**
     * @return array<string, string>
     */
    private function payNotificationData(User $user, string $status): array
    {
        return [
            'TransactionId' => '100500',
            'Amount' => '6999.00',
            'Currency' => 'RUB',
            'AccountId' => (string) $user->id,
            'SubscriptionId' => 'cp_sub_123',
            'Status' => $status,
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function sendCloudPaymentsWebhook(array $data)
    {
        $body = http_build_query($data);
        $signature = base64_encode(hash_hmac('sha256', $body, self::SECRET, true));

        return $this->call(
            'POST',
            '/api/webhooks/cloudpayments',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_HMAC' => $signature,
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            $body,
        );
    }
}
