<?php

use App\Modules\Api\V1\Controllers\ProductApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api-public')->group(function () {
    Route::get('products', [ProductApiController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductApiController::class, 'show'])->name('products.show');
});
