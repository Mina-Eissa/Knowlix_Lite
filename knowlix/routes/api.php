<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\ArticleVersionController;
use App\Http\Controllers\Api\V1\WebhookEventController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('accept-invite', [InvitationController::class, 'accept']); // public, no auth:sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::apiResource('users', UserController::class)->except(['update']);
        Route::post('users/{user}/resend-invite', [UserController::class, 'resendInvite']);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('articles', ArticleController::class);
        Route::post('articles/{article}/submit', [ArticleController::class, 'submit']);
        Route::post('articles/{article}/publish', [ArticleController::class, 'publish']);
        Route::get('articles/{article}/versions', [ArticleVersionController::class, 'index']);
        Route::get('webhook-events', [WebhookEventController::class, 'index']);
        Route::apiResource('tickets', TicketController::class)->except(['destroy']);
        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy']);
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign']);
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'transitionStatus']);
    });
});
