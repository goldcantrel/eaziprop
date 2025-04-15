<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->name('password.email');

    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->name('password.reset');

    // OAuth Routes
    Route::get('/auth/{provider}/redirect', [AuthenticatedSessionController::class, 'redirectToProvider'])
        ->name('oauth.redirect');
    
    Route::get('/auth/{provider}/callback', [AuthenticatedSessionController::class, 'handleProviderCallback'])
        ->name('oauth.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail'])
        ->name('verification.send');

    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});