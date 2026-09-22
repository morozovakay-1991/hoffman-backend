<?php

namespace Tests\Feature\Webhooks;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
            'external_customer_id' => 'cp_invoice_abc',
        ]);

        $data = $this->payNotificationData($user, 'Completed', 'cp_invoice_abc');

        $response = $this->sendCloudPaymentsWebhook($data);
        $response->assertOk()->assertJson(['code' => 0]);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
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
            'external_customer_id' => 'cp_invoice_abc',
        ]);

        $data = $this->payNotificationData($user, 'Declined', 'cp_invoice_abc');

        $response = $this->sendCloudPaymentsWebhook($data);
        $response->assertOk()->assertJson(['code' => 0]);

        $this->assertSame(SubscriptionStatus::Pending, $subscription->refresh()->status);
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
            'external_customer_id' => 'cp_invoice_abc',
        ]);

        $data = $this->payNotificationData($user, 'Completed', 'cp_invoice_abc');
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
        $this->assertSame(SubscriptionStatus::Pending, $subscription->refresh()->status);
    }

    public function test_a_notification_with_a_foreign_email_does_not_break_the_binding_to_the_correct_subscription(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $subscription = Subscription::factory()->create([
            'user_id' => $owner->id,
            'payment_provider' => 'cloudpayments',
            'product_id' => 'yearly',
            'status' => 'pending',
            'external_customer_id' => 'cp_invoice_owner',
        ]);

        // A second user with their own pending checkout, so we can prove the notification
        // never gets misrouted to it.
        $otherUser = User::factory()->create(['email' => 'someone-else@example.com']);
        $otherSubscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'payment_provider' => 'cloudpayments',
            'product_id' => 'monthly',
            'status' => 'pending',
            'external_customer_id' => 'cp_invoice_other',
        ]);

        $data = $this->payNotificationData($owner, 'Completed', 'cp_invoice_owner');
        // Simulates the account's email having changed since checkout (or CloudPayments
        // simply echoing back a different email on the card): the notification's Email
        // does not match the subscription owner's current account email at all.
        $data['Email'] = $otherUser->email;

        $response = $this->sendCloudPaymentsWebhook($data);
        $response->assertOk()->assertJson(['code' => 0]);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertSame(SubscriptionStatus::Pending, $otherSubscription->refresh()->status);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'provider' => 'cloudpayments',
            'status' => 'paid',
        ]);
    }

    public function test_a_notification_with_an_unknown_invoice_id_is_safely_ignored(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'payment_provider' => 'cloudpayments',
            'product_id' => 'yearly',
            'status' => 'pending',
            'external_customer_id' => 'cp_invoice_known',
        ]);

        $data = $this->payNotificationData($user, 'Completed', 'cp_invoice_unknown');

        $response = $this->sendCloudPaymentsWebhook($data);
        $response->assertOk()->assertJson(['code' => 0]);

        // The unmatched notification must not touch the user's real pending subscription,
        // nor fabricate a new subscription/invoice to satisfy it.
        $this->assertSame(SubscriptionStatus::Pending, $subscription->refresh()->status);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('invoices', 0);

        // It is still marked as processed so a retried delivery of the same unknown
        // InvoiceId doesn't spam the logs.
        $this->assertDatabaseCount('payment_webhook_events', 1);

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * @return array<string, string>
     */
    private function payNotificationData(User $user, string $status, string $invoiceId = 'cp_invoice_abc'): array
    {
        return [
            'TransactionId' => '100500',
            'Amount' => '6999.00',
            'Currency' => 'RUB',
            'AccountId' => (string) $user->id,
            'InvoiceId' => $invoiceId,
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
