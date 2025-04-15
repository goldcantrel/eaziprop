<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Property routes
    Route::apiResource('properties', 'App\Http\Controllers\API\PropertyController');
    
    // Rental routes
    Route::apiResource('rentals', 'App\Http\Controllers\API\RentalController');
    
    // Payment routes
    Route::apiResource('payments', 'App\Http\Controllers\API\PaymentController');
    
    // Maintenance request routes
    Route::apiResource('maintenance-requests', 'App\Http\Controllers\API\MaintenanceRequestController');
    
    // Document routes
    Route::apiResource('documents', 'App\Http\Controllers\API\DocumentController');
    
    // Chat routes
    Route::get('/chats/{property_id}', 'App\Http\Controllers\API\ChatController@getMessages');
    Route::post('/chats/{property_id}', 'App\Http\Controllers\API\ChatController@sendMessage');
});

// Auth routes
Route::post('/login', 'App\Http\Controllers\API\AuthController@login');
Route::post('/register', 'App\Http\Controllers\API\AuthController@register');
Route::get('/auth/{provider}/redirect', 'App\Http\Controllers\API\AuthController@redirectToProvider');
Route::get('/auth/{provider}/callback', 'App\Http\Controllers\API\AuthController@handleProviderCallback');