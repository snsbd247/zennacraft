<?php

use App\Modules\Api\V1\Controllers\CategoryApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api-public')->group(function () {
    Route::get('categories', [CategoryApiController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryApiController::class, 'show'])->name('categories.show');
});
