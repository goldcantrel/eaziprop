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

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile', [AuthController::class, 'updateProfile']);

    // Property routes
    Route::apiResource('properties', PropertyController::class);
    Route::get('properties/search', [PropertyController::class, 'search']);

    // Rental routes
    Route::apiResource('rentals', RentalController::class);
    Route::get('rental-history', [RentalController::class, 'getRentalHistory']);

    // Payment routes
    Route::apiResource('payments', PaymentController::class);
    Route::get('payment-history', [PaymentController::class, 'getPaymentHistory']);
    Route::get('payment-statistics', [PaymentController::class, 'getPaymentStatistics']);

    // Maintenance request routes
    Route::apiResource('maintenance-requests', MaintenanceRequestController::class);
    Route::post('maintenance-requests/{maintenance_request}/comments', 
        [MaintenanceRequestController::class, 'addComment']);
    Route::get('maintenance-statistics', 
        [MaintenanceRequestController::class, 'getStatistics']);

    // Document routes
    Route::apiResource('documents', DocumentController::class);
    Route::get('documents/{document}/download', 
        [DocumentController::class, 'download']);

    // Chat routes
    Route::get('chat/messages', [ChatController::class, 'index']);
    Route::post('chat/messages', [ChatController::class, 'store']);
    Route::post('chat/messages/read', [ChatController::class, 'markAsRead']);
    Route::get('chat/unread-count', [ChatController::class, 'getUnreadCount']);
    Route::get('chat/conversations', [ChatController::class, 'getConversations']);
});
