<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->tokenResponse($result, 200);
    }

    public function apple(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWithApple($request->validated()['token']);

        return $this->tokenResponse($result, 200);
    }

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
