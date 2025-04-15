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
use App\Http\Controllers\API\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // User routes
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    
    // User management (superuser only)
    Route::middleware('role:superuser')->group(function () {
        Route::apiResource('users', UserController::class);
    });
    
    // Property routes
    Route::apiResource('properties', PropertyController::class);
    
    // Rental routes
    Route::apiResource('rentals', RentalController::class);
    
    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::put('/{payment}', [PaymentController::class, 'update']);
        Route::delete('/{payment}', [PaymentController::class, 'destroy']);
        Route::post('/webhook', [PaymentController::class, 'handleStripeWebhook']);
    });
    
    // Maintenance request routes
    Route::prefix('maintenance')->group(function () {
        Route::get('/', [MaintenanceRequestController::class, 'index']);
        Route::post('/', [MaintenanceRequestController::class, 'store']);
        Route::get('/{maintenanceRequest}', [MaintenanceRequestController::class, 'show']);
        Route::put('/{maintenanceRequest}', [MaintenanceRequestController::class, 'update']);
        Route::delete('/{maintenanceRequest}', [MaintenanceRequestController::class, 'destroy']);
    });
    
    // Document routes
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::get('/{document}', [DocumentController::class, 'show']);
        Route::put('/{document}', [DocumentController::class, 'update']);
        Route::delete('/{document}', [DocumentController::class, 'destroy']);
        Route::get('/{document}/download', [DocumentController::class, 'download']);
    });
    
    // Chat routes
    Route::prefix('chat')->group(function () {
        Route::get('/{property_id}/messages', [ChatController::class, 'getMessages']);
        Route::post('/{property_id}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/{property_id}/mark-read', [ChatController::class, 'markAsRead']);
        Route::delete('/messages/{message}', [ChatController::class, 'deleteMessage']);
    });
});

// Stripe webhook (unprotected)
Route::post('/webhook/stripe', [PaymentController::class, 'handleStripeWebhook']);