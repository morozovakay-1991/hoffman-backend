<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_endpoints_are_throttled_to_60_requests_per_minute_per_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postInvalidStripeWebhook();
            $response->assertStatus(400);
        }

        // The 61st request within the same minute, from the same IP, must be throttled.
        $this->postInvalidStripeWebhook()->assertStatus(429);
    }

    public function test_stripe_and_cloudpayments_endpoints_share_the_same_webhooks_rate_limit_bucket(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postInvalidStripeWebhook()->assertStatus(400);
        }

        for ($i = 0; $i < 30; $i++) {
            $this->postInvalidCloudPaymentsWebhook()->assertStatus(400);
        }

        // The combined 61st request (regardless of which webhook route it hits) is throttled,
        // because both routes resolve the same 'webhooks' limiter keyed by IP.
        $this->postInvalidStripeWebhook()->assertStatus(429);
    }

    public function test_the_webhooks_rate_limit_does_not_apply_to_regular_api_routes(): void
    {
        // Exhaust the dedicated webhooks bucket for this IP.
        for ($i = 0; $i < 60; $i++) {
            $this->postInvalidStripeWebhook()->assertStatus(400);
        }
        $this->postInvalidStripeWebhook()->assertStatus(429);

        // A regular, unrelated API route must be unaffected: no general limiter is shared
        // with (or leaks from) the webhooks throttle.
        for ($i = 0; $i < 65; $i++) {
            $this->getJson('/api/v1/legal-documents')->assertStatus(200);
        }
    }

    private function postInvalidStripeWebhook()
    {
        return $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid-signature',
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['id' => 'evt_rate_limit_probe'], JSON_THROW_ON_ERROR),
        );
    }

    private function postInvalidCloudPaymentsWebhook()
    {
        return $this->call(
            'POST',
            '/api/webhooks/cloudpayments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['TransactionId' => 'rate_limit_probe'], JSON_THROW_ON_ERROR),
        );
    }
}
