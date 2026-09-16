<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Auth', description: 'Registration, email/social login, logout and the current-user endpoint. Issues a Sanctum bearer token, which every other endpoint (except this group\'s login/register and the public content/legal endpoints) requires via `Authorization: Bearer <token>`.')]
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->tokenResponse($result, 201);
    }

    /**
     * Log in with email and password.
     *
     * `401 INVALID_CREDENTIALS` is returned for a wrong email/password pair.
     * `403 ACCOUNT_BLOCKED` is returned if the account has been blocked by
     * an administrator.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->tokenResponse($result, 200);
    }

    /**
     * Log in (or silently register) with a Sign in with Apple identity token.
     *
     * `401 INVALID_PROVIDER_TOKEN` is returned if the token is invalid, expired,
     * or fails to verify with Apple. `409 SOCIAL_EMAIL_CONFLICT` is returned if
     * the token's email already belongs to a password-based account — the
     * client should ask the user to log in with their password instead.
     * `403 ACCOUNT_BLOCKED` is returned if the resolved account is blocked.
     */
    public function apple(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWithApple($request->validated()['token']);

        return $this->tokenResponse($result, 200);
    }

    /**
     * Log in (or silently register) with a Google ID token.
     *
     * Same error contract as `POST /auth/apple`: `401 INVALID_PROVIDER_TOKEN`,
     * `409 SOCIAL_EMAIL_CONFLICT`, `403 ACCOUNT_BLOCKED`.
     */
    public function google(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWithGoogle($request->validated()['token']);

        return $this->tokenResponse($result, 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * @param  array{user: User, token: string}  $result
     */
    private function tokenResponse(array $result, int $status): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], $status);
    }
}
