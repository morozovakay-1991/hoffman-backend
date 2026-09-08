<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\WebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks)
    {
    }

    public function stripe(Request $request): JsonResponse
    {
        $this->webhooks->handleStripe($request);

        return response()->json(['status' => 'ok']);
    }

    public function cloudpayments(Request $request): JsonResponse
    {
        $this->webhooks->handleCloudPayments($request);

        return response()->json(['code' => 0]);
    }
}
