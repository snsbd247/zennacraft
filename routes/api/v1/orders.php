<?php

use App\Modules\Api\V1\Controllers\OrderApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:customer', 'throttle:api-customer'])->group(function () {
    Route::get('customer/orders', [OrderApiController::class, 'index'])->name('customer.orders.index');
    Route::get('customer/orders/{order}', [OrderApiController::class, 'show'])->name('customer.orders.show');
});
