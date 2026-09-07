<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->forgot($request->validated('email'));

        return response()->json([
            'message' => 'A password reset code has been sent to the provided email address.',
        ]);
    }

    public function verifyCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $this->passwordResetService->verifyCode(
            $request->validated('email'),
            $request->validated('code'),
        );

        return response()->json([
            'message' => 'The reset code is valid.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset(
            $request->validated('email'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your password has been reset.',
        ]);
    }
}
