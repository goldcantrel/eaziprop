<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', 'App\Http\Controllers\DashboardController@index')->name('dashboard');
    
    // Superuser routes
    Route::middleware(['role:superuser'])->group(function () {
        Route::resource('admin/users', 'App\Http\Controllers\Admin\UserController');
        Route::resource('admin/properties', 'App\Http\Controllers\Admin\PropertyController');
    });
    
    // Landlord routes
    Route::middleware(['role:landlord'])->group(function () {
        Route::resource('landlord/properties', 'App\Http\Controllers\Landlord\PropertyController');
        Route::resource('landlord/tenants', 'App\Http\Controllers\Landlord\TenantController');
    });
    
    // Tenant routes
    Route::middleware(['role:tenant'])->group(function () {
        Route::get('tenant/rentals', 'App\Http\Controllers\Tenant\RentalController@index');
        Route::get('tenant/payments', 'App\Http\Controllers\Tenant\PaymentController@index');
    });
});

require __DIR__.'/auth.php';