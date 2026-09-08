<?php

use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LegalDocumentController;
use App\Http\Controllers\Api\V1\MeditationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Http\Controllers\Api\V1\TopicController;
use App\Http\Controllers\Api\V1\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/verification')->middleware('auth:sanctum')->group(function () {
    Route::post('submit', [VerificationController::class, 'submit']);
    Route::get('status', [VerificationController::class, 'status']);
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
