<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RentalController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\MaintenanceRequestController;
use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\ChatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Property routes
    Route::apiResource('properties', PropertyController::class);
    Route::get('properties/statistics', [PropertyController::class, 'statistics']);

    // Rental routes
    Route::apiResource('rentals', RentalController::class);
    Route::post('rentals/{id}/terminate', [RentalController::class, 'terminate']);

    // Payment routes
    Route::apiResource('payments', PaymentController::class, ['except' => ['destroy']]);
    Route::post('payments/{id}/status', [PaymentController::class, 'updateStatus']);
    Route::get('payments/statistics', [PaymentController::class, 'statistics']);

    // Maintenance request routes
    Route::apiResource('maintenance-requests', MaintenanceRequestController::class);
    Route::get('maintenance-requests/statistics', [MaintenanceRequestController::class, 'statistics']);

    // Document routes
    Route::apiResource('documents', DocumentController::class);
    Route::get('documents/statistics', [DocumentController::class, 'statistics']);

    // Chat routes
    Route::get('chat/conversations', [ChatController::class, 'conversations']);
    Route::get('chat/messages', [ChatController::class, 'messages']);
    Route::post('chat/send', [ChatController::class, 'send']);
    Route::get('chat/statistics', [ChatController::class, 'statistics']);
});
