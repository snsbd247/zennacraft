<?php

use App\Modules\Api\V1\Controllers\Auth\CustomerAuthApiController;
use App\Modules\Api\V1\Controllers\Auth\StaffAuthApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::post('customer/request-otp', [CustomerAuthApiController::class, 'requestOtp'])
            ->middleware(['throttle:api-public', 'throttle:otp-request'])
            ->name('customer.request-otp');
        Route::post('customer/verify-otp', [CustomerAuthApiController::class, 'verifyOtp'])
            ->middleware(['throttle:api-public', 'throttle:otp-verify'])
            ->name('customer.verify-otp');
        Route::post('staff/login', [StaffAuthApiController::class, 'login'])
            ->middleware(['throttle:api-public', 'throttle:staff-login'])
            ->name('staff.login');

        Route::middleware(['auth:sanctum', 'abilities:customer', 'throttle:api-customer'])->group(function () {
            Route::post('customer/logout', [CustomerAuthApiController::class, 'logout'])->name('customer.logout');
            Route::get('customer/me', [CustomerAuthApiController::class, 'me'])->name('customer.me');
        });

        Route::middleware(['auth:sanctum', 'abilities:staff', 'throttle:api-staff'])->group(function () {
            Route::post('staff/logout', [StaffAuthApiController::class, 'logout'])->name('staff.logout');
            Route::get('staff/me', [StaffAuthApiController::class, 'me'])->name('staff.me');
        });
    });
