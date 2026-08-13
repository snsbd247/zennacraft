<?php

use App\Modules\Api\V1\Controllers\TrackingApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api-public', 'throttle:tracking-lookup'])->group(function () {
    Route::post('tracking/lookup', [TrackingApiController::class, 'lookup'])->name('tracking.lookup');
});
