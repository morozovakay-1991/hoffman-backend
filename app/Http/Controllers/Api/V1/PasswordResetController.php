<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Auth', description: 'Registration, email/social login, logout and the current-user endpoint. Issues a Sanctum bearer token, which every other endpoint (except this group\'s login/register and the public content/legal endpoints) requires via `Authorization: Bearer <token>`.')]
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    /**
     * Request a 6-digit password reset code by email.
     *
     * Always responds with 200 regardless of whether the email is
     * registered, so the response cannot be used to enumerate accounts (see
     * `App\Domain\Auth\Services\PasswordResetService::forgot()`). The code
     * expires after 5 minutes.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->forgot($request->validated('email'));

        return response()->json([
            'message' => 'A password reset code has been sent to the provided email address.',
        ]);
    }

    /**
     * Verify a reset code without consuming it, so the client can confirm it
     * before showing the new-password screen.
     *
     * `404 EMAIL_NOT_FOUND`, `422 INVALID_CODE`, `422 TOO_MANY_ATTEMPTS`
     * (too many wrong-code attempts — request a new code) and
     * `422 CODE_EXPIRED` (past the 5-minute TTL) may be returned.
     */
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

    /**
     * Set a new password using a code previously verified via `verify-code`.
     *
     * Same error codes as `verify-code`, plus `422 INVALID_CODE` is also
     * returned if the code has not been verified yet.
     */
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
