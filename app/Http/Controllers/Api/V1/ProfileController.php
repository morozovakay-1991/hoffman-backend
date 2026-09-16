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
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Profile', description: 'The authenticated user\'s own profile: viewing/updating basic fields, changing email (code-confirmed) and password, notification preferences, and account deletion.')]
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

    /**
     * Request an email change: sends a confirmation code to the new address.
     * The change only takes effect once confirmed via `POST profile/email/confirm`.
     *
     * `422 EMAIL_TAKEN` is returned if the new address is already registered
     * to another account.
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $this->emailChangeService->requestChange($request->user(), $request->validated('new_email'));

        return response()->json([
            'message' => 'A confirmation code has been sent to the new email address.',
        ]);
    }

    /**
     * Confirm a pending email change with the code sent to the new address.
     *
     * `422 INVALID_CODE`, `422 TOO_MANY_ATTEMPTS` (too many wrong-code
     * attempts — request the change again) and `422 CODE_EXPIRED` may be
     * returned.
     */
    public function confirmEmail(ConfirmEmailChangeRequest $request): JsonResponse
    {
        $this->emailChangeService->confirm($request->user(), $request->validated('code'));

        return response()->json([
            'profile' => new ProfileResource($request->user()->fresh()),
        ]);
    }

    /**
     * Change the authenticated user's password.
     *
     * `422 INVALID_OLD_PASSWORD` is returned if `old_password` does not
     * match the account's current password.
     */
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

    /**
     * Request scheduled account deletion (grace-period deletion, as opposed
     * to `DELETE profile` which deletes immediately).
     *
     * `422 DELETION_ALREADY_REQUESTED` is returned if a deletion request is
     * already pending for this account.
     */
    public function requestDeletion(RequestDeletionRequest $request): JsonResponse
    {
        $deletionRequest = $this->profileService->requestDeletion($request->user(), $request->validated('reason'));

        return response()->json([
            'deletion_request' => new DeletionRequestResource($deletionRequest),
        ], 201);
    }

    /**
     * Delete the authenticated user's account immediately (no grace period),
     * as opposed to `POST profile/deletion-request` which schedules deletion.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->profileService->deleteNow($request->user());

        return response()->json(null, 204);
    }
}
