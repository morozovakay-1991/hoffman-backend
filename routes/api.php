<?php

use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\DiaryController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LegalDocumentController;
use App\Http\Controllers\Api\V1\MeditationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Http\Controllers\Api\V1\TopicController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/verification')->middleware('auth:sanctum')->group(function () {
    Route::post('submit', [VerificationController::class, 'submit']);
    Route::get('status', [VerificationController::class, 'status']);
});

Route::prefix('v1/profile')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::patch('/', [ProfileController::class, 'update']);
    Route::patch('email', [ProfileController::class, 'updateEmail']);
    Route::post('email/confirm', [ProfileController::class, 'confirmEmail']);
    Route::patch('password', [ProfileController::class, 'updatePassword']);
    Route::patch('notifications', [ProfileController::class, 'updateNotifications']);
    Route::post('deletion-request', [ProfileController::class, 'requestDeletion']);
    Route::delete('/', [ProfileController::class, 'destroy']);
});

Route::get('v1/subscription', [SubscriptionController::class, 'show'])->middleware('auth:sanctum');

Route::prefix('v1/billing')->group(function () {
    Route::get('plans', [BillingController::class, 'plans']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('checkout-session', [BillingController::class, 'checkoutSession']);
        Route::post('cancel', [BillingController::class, 'cancel']);
        Route::post('payment-method', [BillingController::class, 'paymentMethod']);
    });
});

// Payment provider callbacks: public (no Sanctum auth — providers can't hold a bearer
// token), authenticated instead via each provider's own signature scheme, and kept
// under a dedicated rate limit separate from the general API traffic.
Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function () {
    Route::post('stripe', [WebhookController::class, 'stripe']);
    Route::post('cloudpayments', [WebhookController::class, 'cloudpayments']);
});

Route::prefix('v1/diary')->middleware('auth:sanctum')->group(function () {
    Route::get('days', [DiaryController::class, 'index']);
    Route::get('days/{dayNumber}', [DiaryController::class, 'show'])->whereNumber('dayNumber');
    Route::post('days/{dayNumber}/answer', [DiaryController::class, 'saveAnswer'])->whereNumber('dayNumber');
});

Route::prefix('v1/legal-documents')->group(function () {
    Route::get('/', [LegalDocumentController::class, 'index']);
    Route::get('{legalDocument:slug}', [LegalDocumentController::class, 'show']);
});

// Content endpoints accept both authenticated and guest requests: access to each
// item is determined per-request by App\Domain\Content\Services\AccessLevelService,
// not by whether the caller is authenticated, so no auth middleware is applied here.
Route::prefix('v1/meditations')->group(function () {
    Route::get('/', [MeditationController::class, 'index']);
    Route::get('{meditation}', [MeditationController::class, 'show']);
    Route::get('{meditation}/audio', [MeditationController::class, 'audio']);
});

Route::prefix('v1/tools')->group(function () {
    Route::get('/', [ToolController::class, 'index']);
    Route::get('{tool}', [ToolController::class, 'show']);
});

Route::prefix('v1/topics')->group(function () {
    Route::get('/', [TopicController::class, 'index']);
    Route::get('{topic}', [TopicController::class, 'show']);
});

Route::prefix('v1/articles')->group(function () {
    Route::get('/', [ArticleController::class, 'index']);
    Route::get('{article}', [ArticleController::class, 'show']);
});

Route::get('v1/home', [HomeController::class, 'index']);

Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::prefix('password')->group(function () {
        Route::post('forgot', [PasswordResetController::class, 'forgot']);
        Route::post('verify-code', [PasswordResetController::class, 'verifyCode']);
        Route::post('reset', [PasswordResetController::class, 'reset']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
