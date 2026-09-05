<?php

use App\Http\Controllers\Api\MobileApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [MobileApiController::class, 'login']);

    Route::get('/properties', [MobileApiController::class, 'properties']);
    Route::get('/properties/{property:slug}', [MobileApiController::class, 'property']);
    Route::post('/properties/{property:slug}/availability', [MobileApiController::class, 'availability']);

    Route::middleware('api-token')->group(function (): void {
        Route::post('/auth/logout', [MobileApiController::class, 'logout']);
        Route::get('/me', [MobileApiController::class, 'me']);
        Route::put('/me', [MobileApiController::class, 'updateMe']);

        Route::middleware('api-role:customer')->group(function (): void {
            Route::get('/customer/reservations', [MobileApiController::class, 'customerReservations']);
            Route::get('/customer/reservations/{reservation}', [MobileApiController::class, 'customerReservation']);
            Route::post('/customer/properties/{property}/favorite', [MobileApiController::class, 'toggleFavorite']);
        });

        Route::middleware('api-role:staff')->group(function (): void {
            Route::get('/staff/reservations', [MobileApiController::class, 'staffReservations']);
            Route::get('/staff/reservations/{reservation}', [MobileApiController::class, 'staffReservation']);
            Route::patch('/staff/reservations/{reservation}/status', [MobileApiController::class, 'updateReservationStatus']);
            Route::post('/staff/reservations/{reservation}/payment-link', [MobileApiController::class, 'sendPaymentLink']);
        });

        Route::middleware('api-role:admin')->group(function (): void {
            Route::get('/admin/properties', [MobileApiController::class, 'adminProperties']);
        });
    });
});