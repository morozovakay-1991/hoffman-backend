<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Verification\Services\VerificationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Verification\SubmitVerificationRequest;
use App\Http\Resources\VerificationRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(private readonly VerificationService $verificationService)
    {
    }

    public function submit(SubmitVerificationRequest $request): JsonResponse
    {
        $verificationRequest = $this->verificationService->submit(
            $request->user(),
            $request->validated('last_name'),
            $request->validated('first_name'),
            $request->validated('phone'),
        );

        return response()->json([
            'verification_request' => new VerificationRequestResource($verificationRequest),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $verificationRequest = $this->verificationService->status($request->user());

        return response()->json([
            'verification_request' => $verificationRequest ? new VerificationRequestResource($verificationRequest) : null,
        ]);
    }
}
