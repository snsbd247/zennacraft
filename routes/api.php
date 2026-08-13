<?php

use App\Modules\Courier\Http\Controllers\CourierWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        require __DIR__.'/api/v1/auth.php';
        require __DIR__.'/api/v1/categories.php';
        require __DIR__.'/api/v1/products.php';
        require __DIR__.'/api/v1/orders.php';
        require __DIR__.'/api/v1/tracking.php';
    });

// Courier status-update webhooks — verified by a secret embedded in the URL
// itself (see docs/courier-payment-providers.md), not by anything the
// provider sends, so these are intentionally outside auth/CSRF.
Route::middleware('throttle:api-public')->group(function () {
    Route::post('/webhooks/steadfast', [CourierWebhookController::class, 'steadfast'])->name('webhooks.steadfast');
    Route::post('/webhooks/pathao', [CourierWebhookController::class, 'pathao'])->name('webhooks.pathao');
});
