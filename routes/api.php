<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LegalDocumentController;
use App\Http\Controllers\Api\V1\PasswordResetController;
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
