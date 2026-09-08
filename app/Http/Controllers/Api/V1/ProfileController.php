<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Profile\Services\EmailChangeService;
use App\Domain\Profile\Services\ProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ConfirmEmailChangeRequest;
use App\Http\Requests\Profile\RequestDeletionRequest;
use App\Http\Requests\Profile\UpdateEmailRequest;
use App\Http\Requests\Profile\UpdateNotificationsRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\DeletionRequestResource;
use App\Http\Resources\NotificationSettingResource;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly EmailChangeService $emailChangeService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('notificationSettings');

        return response()->json([
            'profile' => new ProfileResource($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'profile' => new ProfileResource($user),
        ]);
    }

    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $this->emailChangeService->requestChange($request->user(), $request->validated('new_email'));

        return response()->json([
            'message' => 'A confirmation code has been sent to the new email address.',
        ]);
    }

    public function confirmEmail(ConfirmEmailChangeRequest $request): JsonResponse
    {
        $this->emailChangeService->confirm($request->user(), $request->validated('code'));

        return response()->json([
            'profile' => new ProfileResource($request->user()->fresh()),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profileService->updatePassword(
            $request->user(),
            $request->validated('old_password'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your password has been updated.',
        ]);
    }

    public function updateNotifications(UpdateNotificationsRequest $request): JsonResponse
    {
        $settings = $this->profileService->updateNotifications($request->user(), $request->validated());

        return response()->json([
            'notification_settings' => new NotificationSettingResource($settings),
        ]);
    }

    public function requestDeletion(RequestDeletionRequest $request): JsonResponse
    {
        $deletionRequest = $this->profileService->requestDeletion($request->user(), $request->validated('reason'));

        return response()->json([
            'deletion_request' => new DeletionRequestResource($deletionRequest),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->profileService->deleteNow($request->user());

        return response()->json(null, 204);
    }
}
