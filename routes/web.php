<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\RedirectIfStaffAuthenticated;
use App\Modules\AdminAuth\Http\Controllers\AccountController;
use App\Modules\AdminAuth\Http\Controllers\AdminLoginController;
use App\Modules\Analytics\Http\Controllers\BehaviorEventController;
use App\Modules\Checkout\Http\Controllers\CartController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Courier\Http\Controllers\CourierController;
use App\Modules\Customer\Http\Controllers\CustomerAuthController;
use App\Modules\Customer\Http\Controllers\CustomerDashboardController;
use App\Modules\Order\Http\Controllers\OrderController;
use App\Modules\Order\Http\Controllers\OrderCreateController;
use App\Modules\Order\Http\Controllers\OrderExchangeController;
use App\Modules\Order\Http\Controllers\OrderNoteController;
use App\Modules\Review\Http\Controllers\CustomerReviewController;
use App\Modules\Seo\Http\Controllers\RobotsController;
use App\Modules\Seo\Http\Controllers\SitemapController;
use App\Modules\Storefront\Http\Controllers\StorefrontController;
use App\Modules\Tracking\Http\Controllers\TrackingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
Route::get('/products', [StorefrontController::class, 'products'])->name('storefront.products');
Route::get('/search/suggest', [StorefrontController::class, 'searchSuggest'])->middleware('throttle:api-public')->name('storefront.search.suggest');
Route::get('/products/{product:slug}', [StorefrontController::class, 'productShow'])->name('storefront.product.show');
Route::get('/categories/{category:slug}', [StorefrontController::class, 'categoryShow'])->name('storefront.category.show');
Route::post('/events/behavior', [BehaviorEventController::class, 'store'])
    ->middleware(['throttle:api-public', 'throttle:behavior-events'])
    ->name('behavior-events.store');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/drawer', [CartController::class, 'drawer'])->name('cart.drawer');
Route::post('/cart/items', [CartController::class, 'add'])->middleware('throttle:cart-mutation')->name('cart.add');
Route::post('/cart/items/batch', [CartController::class, 'addMany'])->middleware('throttle:cart-mutation')->name('cart.add-many');
Route::patch('/cart/items/{key}', [CartController::class, 'update'])->middleware('throttle:cart-mutation')->name('cart.update');
Route::delete('/cart/items/{key}', [CartController::class, 'remove'])->middleware('throttle:cart-mutation')->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])->middleware('throttle:cart-mutation')->name('cart.clear');
Route::middleware('throttle:checkout')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});
Route::post('/checkout/coupon', [CheckoutController::class, 'couponPreview'])->middleware('throttle:cart-mutation')->name('checkout.coupon');
Route::post('/checkout/capture', [CheckoutController::class, 'captureRecovery'])->middleware('throttle:cart-mutation')->name('checkout.capture');
// Not `signed` — bKash appends its own paymentID/status query params on
// redirect, which would break a signature computed before those existed.
// See App\Modules\Checkout\Http\Controllers\BkashPaymentController.
Route::get('/checkout/bkash/callback', [\App\Modules\Checkout\Http\Controllers\BkashPaymentController::class, 'callback'])->middleware('throttle:checkout')->name('checkout.bkash.callback');
Route::post('/landing-order', [\App\Modules\LandingPage\Http\Controllers\LandingOrderController::class, 'store'])->middleware('throttle:checkout')->name('landing.order.store');
Route::post('/landing-order/coupon', [\App\Modules\LandingPage\Http\Controllers\LandingOrderController::class, 'couponPreview'])->middleware('throttle:cart-mutation')->name('landing.order.coupon');
Route::get('/checkout/success/{order:order_number}', [CheckoutController::class, 'success'])->middleware('signed')->name('checkout.success');
Route::get('/checkout/invoice/{order:order_number}', [CheckoutController::class, 'invoice'])->middleware('signed')->name('checkout.invoice');
Route::middleware('throttle:tracking-lookup')->group(function () {
    Route::get('/track', [TrackingController::class, 'publicForm'])->name('tracking.form');
    Route::post('/track', [TrackingController::class, 'publicLookup'])->name('tracking.lookup');
});
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.xml');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots.txt');
Route::get('/customer/login', [CustomerAuthController::class, 'showRequestOtp'])->name('customer.login');
Route::post('/customer/request-otp', [CustomerAuthController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('customer.otp.request');
Route::get('/customer/verify-otp', [CustomerAuthController::class, 'showVerifyOtp'])->name('customer.otp.verify.form');
Route::post('/customer/verify-otp', [CustomerAuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('customer.otp.verify');
Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
Route::get('/customer/dashboard', [CustomerDashboardController::class, 'dashboard'])->name('customer.dashboard');
Route::patch('/customer/profile', [CustomerDashboardController::class, 'updateProfile'])->name('customer.profile.update');
Route::get('/customer/orders', [CustomerDashboardController::class, 'orders'])->name('customer.orders');
Route::get('/customer/orders/{order}/tracking', [TrackingController::class, 'customerOrderTracking'])->name('customer.orders.tracking');
Route::get('/customer/orders/{order}', [CustomerDashboardController::class, 'showOrder'])->name('customer.orders.show');
Route::post('/customer/orders/{order}/reviews', [CustomerReviewController::class, 'store'])->middleware('throttle:review-submit')->name('customer.orders.reviews.store');

$adminPath = config('admin.path');

// Studio admin panel was removed in full on 2026-07-24 to be rebuilt from
// scratch with new design/new code, page by page. Only auth + a landing
// stub survive; every other Studio route/controller/view is gone until
// rebuilt. Backend models/services/migrations were left untouched — only
// the Studio HTTP/view layer was removed.
Route::prefix($adminPath)->group(function () {
    Route::middleware([RedirectIfStaffAuthenticated::class])->group(function () {
        Route::get('login', [AdminLoginController::class, 'showLogin'])->name('staff.login');
        Route::post('login', [AdminLoginController::class, 'login'])->middleware('throttle:staff-login')->name('staff.login.submit');
    });

    Route::middleware([AdminAccess::class, \App\Http\Middleware\EnsureLicenseIsValid::class])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('studio.dashboard');

        Route::post('logout', [AdminLoginController::class, 'logout'])->name('staff.logout');

        // Own account — profile + change password (available to any signed-in staff).
        Route::get('account', [AccountController::class, 'show'])->name('account.show');
        Route::post('account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
        Route::post('account/password', [AccountController::class, 'updatePassword'])->name('account.password');

        // --- Orders (rebuilt 2026-07-24) ---
        // Specific paths registered before orders/{order} so route-model
        // binding never tries to resolve "source"/"exchange"/etc as an
        // Order id.
        Route::middleware(['permission:order.view'])->group(function () {
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/source', [OrderController::class, 'source'])->name('orders.source');
            Route::get('orders/processing-report', [OrderController::class, 'processingReport'])->name('orders.processing-report');
            Route::get('orders/notes', [OrderNoteController::class, 'index'])->name('orders.notes.index');
            Route::get('orders/fraud-check', [OrderController::class, 'fraudCheck'])->name('orders.fraud-check');
            Route::get('orders/new-check', [OrderController::class, 'newCheck'])->name('orders.new-check');
            Route::get('orders/exchange/create', [OrderExchangeController::class, 'create'])->name('orders.exchange.create');
            Route::get('orders/exchange/search', [OrderExchangeController::class, 'search'])->name('orders.exchange.search');
            Route::get('orders/create', [OrderCreateController::class, 'create'])->name('orders.create');
            Route::get('orders/create/products/search', [OrderCreateController::class, 'searchProducts'])->name('orders.create.products.search');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::get('orders/{order}/pos-print', [OrderController::class, 'posPrint'])->name('orders.pos-print');
            Route::get('orders/{order}/label-print', [OrderController::class, 'labelPrint'])->name('orders.label-print');
        });

        Route::middleware(['permission:order.update'])->group(function () {
            Route::post('orders', [OrderCreateController::class, 'store'])->name('orders.store');
            Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
            Route::patch('orders/{order}/address', [OrderController::class, 'updateAddress'])->name('orders.address.update');
            Route::post('orders/{order}/items', [OrderController::class, 'addItem'])->name('orders.items.store');
            Route::patch('orders/{order}/items/{item}', [OrderController::class, 'updateItem'])->name('orders.items.update');
            Route::delete('orders/{order}/items/{item}', [OrderController::class, 'removeItem'])->name('orders.items.destroy');
            Route::post('orders/{order}/discount', [OrderController::class, 'setDiscount'])->name('orders.discount');
            Route::post('orders/{order}/comment', [OrderController::class, 'storeComment'])->name('orders.comment');
            Route::post('orders/{order}/block', [OrderController::class, 'block'])->name('orders.block');
            Route::post('orders/notes', [OrderNoteController::class, 'store'])->name('orders.notes.store');
            Route::post('orders/exchange', [OrderExchangeController::class, 'store'])->name('orders.exchange.store');
        });

        Route::middleware(['permission:courier.assign'])->post('orders/{order}/courier', [OrderController::class, 'assignCourier'])->name('orders.courier.assign');
        Route::middleware(['permission:verification.update'])->post('orders/{order}/verify', [OrderController::class, 'verify'])->name('orders.verify');

        // --- POS (in-store point of sale — reuses the order engine) ---
        Route::middleware(['permission:order.update'])->group(function () {
            Route::get('pos', [\App\Modules\Order\Http\Controllers\PosController::class, 'index'])->name('pos.index');
            Route::get('pos/products/search', [\App\Modules\Order\Http\Controllers\PosController::class, 'searchProducts'])->name('pos.products.search');
            Route::post('pos', [\App\Modules\Order\Http\Controllers\PosController::class, 'store'])->name('pos.store');
        });

        // --- Products (rebuilt 2026-07-25, page by page) ---
        Route::middleware(['permission:product.view'])->group(function () {
            Route::get('products', [\App\Modules\Product\Http\Controllers\ProductController::class, 'index'])->name('products.index');
            Route::get('products/create', [\App\Modules\Product\Http\Controllers\ProductController::class, 'create'])->name('products.create');
            Route::get('products/{product}/edit', [\App\Modules\Product\Http\Controllers\ProductController::class, 'edit'])->name('products.edit');
            Route::get('products/{product}/print', [\App\Modules\Product\Http\Controllers\ProductController::class, 'printLabel'])->name('products.print');
            Route::get('products/{product}/export-customers', [\App\Modules\Product\Http\Controllers\ProductController::class, 'exportCustomers'])->name('products.export-customers');
        });
        Route::middleware(['permission:product.update'])->group(function () {
            Route::put('products/{product}', [\App\Modules\Product\Http\Controllers\ProductController::class, 'update'])->name('products.update');
            Route::post('products/{product}/toggle-status', [\App\Modules\Product\Http\Controllers\ProductController::class, 'toggleStatus'])->name('products.toggle-status');
            // Inline add/remove of colour & size options from the product form.
            Route::post('products/attribute-options', [\App\Modules\Product\Http\Controllers\ProductController::class, 'storeAttributeOption'])->name('products.attr-options.store');
            Route::delete('products/attribute-options/{attributeValue}', [\App\Modules\Product\Http\Controllers\ProductController::class, 'destroyAttributeOption'])->name('products.attr-options.destroy');
        });
        Route::middleware(['permission:product.create'])->post('products', [\App\Modules\Product\Http\Controllers\ProductController::class, 'store'])->name('products.store');
        Route::middleware(['permission:product.create'])->post('products/{product}/duplicate', [\App\Modules\Product\Http\Controllers\ProductController::class, 'duplicate'])->name('products.duplicate');
        Route::middleware(['permission:product.delete'])->delete('products/{product}', [\App\Modules\Product\Http\Controllers\ProductController::class, 'destroy'])->name('products.destroy');

        // --- Products sub-modules: attributes / variants / reviews / view report / damage ---
        Route::middleware(['permission:product.view'])->group(function () {
            Route::get('products/attributes', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'index'])->name('products.attributes.index');
            Route::get('products/attributes/create', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'create'])->name('products.attributes.create');
            Route::get('products/attributes/{attribute}/edit', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'edit'])->name('products.attributes.edit');
            Route::get('products/variants', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'index'])->name('products.variants.index');
            Route::get('products/variants/create', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'create'])->name('products.variants.create');
            Route::get('products/variants/{variant}/edit', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'edit'])->name('products.variants.edit');
            Route::get('stock', [\App\Modules\Inventory\Http\Controllers\StockController::class, 'index'])->name('stock.index');
            Route::get('products/reviews', [\App\Modules\Product\Http\Controllers\ProductReviewController::class, 'index'])->name('products.reviews.index');
            Route::get('products/view-report', [\App\Modules\Product\Http\Controllers\ProductViewReportController::class, 'index'])->name('products.view-report.index');
            Route::get('products/damages', [\App\Modules\Product\Http\Controllers\ProductDamageController::class, 'index'])->name('products.damages.index');
            Route::get('products/damages/create', [\App\Modules\Product\Http\Controllers\ProductDamageController::class, 'create'])->name('products.damages.create');
            Route::get('products/damages/{damage}', [\App\Modules\Product\Http\Controllers\ProductDamageController::class, 'show'])->name('products.damages.show');
        });
        Route::middleware(['permission:product.create'])->group(function () {
            Route::post('products/attributes', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'store'])->name('products.attributes.store');
            Route::post('products/variants', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'store'])->name('products.variants.store');
            Route::post('products/damages', [\App\Modules\Product\Http\Controllers\ProductDamageController::class, 'store'])->name('products.damages.store');
        });
        Route::middleware(['permission:product.update'])->group(function () {
            Route::put('products/attributes/{attribute}', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'update'])->name('products.attributes.update');
            Route::post('products/attributes/{attribute}/toggle', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'toggleStatus'])->name('products.attributes.toggle');
            Route::put('products/variants/{variant}', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'update'])->name('products.variants.update');
            Route::post('products/variants/{variant}/toggle', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'toggleStatus'])->name('products.variants.toggle');
            Route::post('products/reviews/{review}/toggle', [\App\Modules\Product\Http\Controllers\ProductReviewController::class, 'toggleStatus'])->name('products.reviews.toggle');
            Route::post('stock', [\App\Modules\Inventory\Http\Controllers\StockController::class, 'update'])->name('stock.update');
        });
        Route::middleware(['permission:product.delete'])->group(function () {
            Route::delete('products/attributes/{attribute}', [\App\Modules\Product\Http\Controllers\ProductAttributeController::class, 'destroy'])->name('products.attributes.destroy');
            Route::delete('products/variants/{variant}', [\App\Modules\Product\Http\Controllers\ProductAttributeValueController::class, 'destroy'])->name('products.variants.destroy');
            Route::delete('products/reviews/{review}', [\App\Modules\Product\Http\Controllers\ProductReviewController::class, 'destroy'])->name('products.reviews.destroy');
            Route::delete('products/damages/{damage}', [\App\Modules\Product\Http\Controllers\ProductDamageController::class, 'destroy'])->name('products.damages.destroy');
        });

        // --- Purchase (suppliers + purchases) ---
        Route::middleware(['permission:purchase.view'])->group(function () {
            Route::get('purchases', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'index'])->name('purchases.index');
            Route::get('purchases/create', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'create'])->name('purchases.create');
            Route::get('purchases/{purchase}', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'show'])->name('purchases.show');
            Route::get('purchases/{purchase}/edit', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'edit'])->name('purchases.edit');
            Route::get('suppliers', [\App\Modules\Purchase\Http\Controllers\SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('suppliers/{supplier}', [\App\Modules\Purchase\Http\Controllers\SupplierController::class, 'show'])->name('suppliers.show');
        });
        Route::middleware(['permission:purchase.create'])->group(function () {
            Route::post('purchases', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'store'])->name('purchases.store');
            Route::post('suppliers', [\App\Modules\Purchase\Http\Controllers\SupplierController::class, 'store'])->name('suppliers.store');
        });
        Route::middleware(['permission:purchase.update'])->group(function () {
            Route::put('purchases/{purchase}', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'update'])->name('purchases.update');
            Route::put('suppliers/{supplier}', [\App\Modules\Purchase\Http\Controllers\SupplierController::class, 'update'])->name('suppliers.update');
            Route::post('suppliers/{supplier}/payments', [\App\Modules\Purchase\Http\Controllers\SupplierController::class, 'storePayment'])->name('suppliers.payments.store');
        });
        Route::middleware(['permission:purchase.delete'])->group(function () {
            Route::delete('purchases/{purchase}', [\App\Modules\Purchase\Http\Controllers\PurchaseController::class, 'destroy'])->name('purchases.destroy');
            Route::delete('suppliers/{supplier}', [\App\Modules\Purchase\Http\Controllers\SupplierController::class, 'destroy'])->name('suppliers.destroy');
        });

        // --- Category (main / sub / sub-sub — one controller, level via route default) ---
        foreach (['main', 'sub', 'subsub'] as $categoryLevel) {
            Route::middleware(['permission:category.view'])->group(function () use ($categoryLevel) {
                Route::get("categories/$categoryLevel", [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'index'])->defaults('level', $categoryLevel)->name("categories.$categoryLevel.index");
                Route::get("categories/$categoryLevel/create", [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'create'])->defaults('level', $categoryLevel)->name("categories.$categoryLevel.create");
                Route::get("categories/$categoryLevel/{category}/edit", [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'edit'])->defaults('level', $categoryLevel)->name("categories.$categoryLevel.edit");
                Route::get("categories/$categoryLevel/{category}", [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'show'])->defaults('level', $categoryLevel)->name("categories.$categoryLevel.show");
            });
            Route::middleware(['permission:category.create'])->post("categories/$categoryLevel", [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'store'])->defaults('level', $categoryLevel)->name("categories.$categoryLevel.store");
            Route::middleware(['permission:category.update'])->put("categories/$categoryLevel/{category}", [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'update'])->defaults('level', $categoryLevel)->name("categories.$categoryLevel.update");
        }
        Route::middleware(['permission:category.update'])->group(function () {
            Route::post('categories/{category}/toggle', [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'toggleStatus'])->name('categories.toggle');
            Route::post('categories/{category}/discount', [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'applyDiscount'])->name('categories.discount');
        });
        Route::middleware(['permission:category.delete'])->delete('categories/{category}', [\App\Modules\Catalog\Http\Controllers\CategoryController::class, 'destroy'])->name('categories.destroy');

        // --- Backups ---
        Route::middleware(['permission:backup.view'])->get('backups', [\App\Modules\Backup\Http\Controllers\BackupController::class, 'index'])->name('backups.index');
        Route::middleware(['permission:backup.create'])->group(function () {
            Route::put('backups/settings', [\App\Modules\Backup\Http\Controllers\BackupController::class, 'updateSettings'])->name('backups.settings.update');
            Route::post('backups/run', [\App\Modules\Backup\Http\Controllers\BackupController::class, 'runNow'])->name('backups.run');
        });

        // --- Sliders (storefront homepage banners; one page per placement) ---
        $sliderPlacements = ['hero' => 'home_hero', 'side' => 'home_side', 'promo' => 'home_promo'];
        Route::middleware(['permission:theme.view'])->group(function () use ($sliderPlacements) {
            foreach ($sliderPlacements as $seg => $placement) {
                Route::get("sliders/$seg", [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'index'])->defaults('placement', $placement)->name("sliders.$seg.index");
                Route::get("sliders/$seg/create", [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'create'])->defaults('placement', $placement)->name("sliders.$seg.create");
            }
            Route::get('sliders/{slider}/edit', [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'edit'])->name('sliders.edit');
        });
        Route::middleware(['permission:theme.update'])->group(function () {
            Route::post('sliders', [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'store'])->name('sliders.store');
            Route::put('sliders/{slider}', [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'update'])->name('sliders.update');
            Route::post('sliders/{slider}/toggle', [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'toggleStatus'])->name('sliders.toggle');
            Route::delete('sliders/{slider}', [\App\Modules\Storefront\Http\Controllers\SliderController::class, 'destroy'])->name('sliders.destroy');
        });

        // --- Brand ---
        Route::middleware(['permission:brand.view'])->get('brands', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'index'])->name('brands.index');
        Route::middleware(['permission:brand.create'])->group(function () {
            Route::get('brands/create', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'create'])->name('brands.create');
            Route::post('brands', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'store'])->name('brands.store');
        });
        Route::middleware(['permission:brand.update'])->group(function () {
            Route::get('brands/{brand}/edit', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'edit'])->name('brands.edit');
            Route::put('brands/{brand}', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'update'])->name('brands.update');
            Route::post('brands/{brand}/toggle', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'toggleStatus'])->name('brands.toggle');
        });
        Route::middleware(['permission:brand.delete'])->delete('brands/{brand}', [\App\Modules\Catalog\Http\Controllers\BrandController::class, 'destroy'])->name('brands.destroy');

        // --- Accounts (finance ledger, balances, transfers, dues, salary, bills) ---
        Route::prefix('accounts')->name('accounts.')->group(function () {
            $F = '\App\Modules\Finance\Http\Controllers\\';

            // Income (credit) + Expense (debit) share the ledger controller via a type default
            foreach (['income' => 'credit', 'expense' => 'debit'] as $seg => $ledgerType) {
                Route::middleware(['permission:finance.view'])->group(function () use ($seg, $ledgerType, $F) {
                    Route::get($seg, [$F.'LedgerController', 'index'])->defaults('type', $ledgerType)->name("$seg.index");
                    Route::get("$seg/create", [$F.'LedgerController', 'create'])->defaults('type', $ledgerType)->name("$seg.create");
                });
                Route::middleware(['permission:finance.manage'])->group(function () use ($seg, $F) {
                    Route::post($seg, [$F.'LedgerController', 'store'])->defaults('type', $seg === 'income' ? 'credit' : 'debit')->name("$seg.store");
                    Route::get("$seg/{transaction}/edit", [$F.'LedgerController', 'edit'])->name("$seg.edit");
                    Route::put("$seg/{transaction}", [$F.'LedgerController', 'update'])->name("$seg.update");
                    Route::delete("$seg/{transaction}", [$F.'LedgerController', 'destroy'])->name("$seg.destroy");
                });
            }

            // Due
            Route::middleware(['permission:finance.view'])->get('due', [$F.'DueController', 'index'])->name('due.index');
            Route::middleware(['permission:finance.manage'])->post('due/{order}/paid', [$F.'DueController', 'getPaid'])->name('due.paid');

            // Balance (channels overview + CRUD)
            Route::middleware(['permission:finance.view'])->get('balance', [$F.'BalanceController', 'index'])->name('balance.index');
            Route::middleware(['permission:finance.manage'])->group(function () use ($F) {
                Route::post('balance', [$F.'BalanceController', 'store'])->name('balance.store');
                Route::put('balance/{account}', [$F.'BalanceController', 'update'])->name('balance.update');
                Route::post('balance/{account}/toggle', [$F.'BalanceController', 'toggle'])->name('balance.toggle');
                Route::delete('balance/{account}', [$F.'BalanceController', 'destroy'])->name('balance.destroy');
            });

            // Fund Transfer
            Route::middleware(['permission:finance.view'])->group(function () use ($F) {
                Route::get('transfer', [$F.'FundTransferController', 'index'])->name('transfer.index');
                Route::get('transfer/create', [$F.'FundTransferController', 'create'])->name('transfer.create');
            });
            Route::middleware(['permission:finance.manage'])->group(function () use ($F) {
                Route::post('transfer', [$F.'FundTransferController', 'store'])->name('transfer.store');
                Route::delete('transfer/{transfer}', [$F.'FundTransferController', 'destroy'])->name('transfer.destroy');
            });

            // Account Purpose
            Route::middleware(['permission:finance.view'])->group(function () use ($F) {
                Route::get('purpose', [$F.'AccountPurposeController', 'index'])->name('purpose.index');
                Route::get('purpose/create', [$F.'AccountPurposeController', 'create'])->name('purpose.create');
                Route::get('purpose/{purpose}/edit', [$F.'AccountPurposeController', 'edit'])->name('purpose.edit');
            });
            Route::middleware(['permission:finance.manage'])->group(function () use ($F) {
                Route::post('purpose', [$F.'AccountPurposeController', 'store'])->name('purpose.store');
                Route::put('purpose/{purpose}', [$F.'AccountPurposeController', 'update'])->name('purpose.update');
            });

            // Employee Salary
            Route::middleware(['permission:finance.view'])->group(function () use ($F) {
                Route::get('salary', [$F.'EmployeeController', 'index'])->name('salary.index');
                Route::get('salary/create', [$F.'EmployeeController', 'create'])->name('salary.create');
                Route::get('salary/{employee}/edit', [$F.'EmployeeController', 'edit'])->name('salary.edit');
            });
            Route::middleware(['permission:finance.manage'])->group(function () use ($F) {
                Route::post('salary', [$F.'EmployeeController', 'store'])->name('salary.store');
                Route::put('salary/{employee}', [$F.'EmployeeController', 'update'])->name('salary.update');
                Route::post('salary/{employee}/toggle', [$F.'EmployeeController', 'toggle'])->name('salary.toggle');
                Route::delete('salary/{employee}', [$F.'EmployeeController', 'destroy'])->name('salary.destroy');
            });

            // Bill Statement
            Route::middleware(['permission:finance.view'])->group(function () use ($F) {
                Route::get('bill', [$F.'BillStatementController', 'index'])->name('bill.index');
                Route::get('bill/create', [$F.'BillStatementController', 'create'])->name('bill.create');
                Route::get('bill/{bill}/edit', [$F.'BillStatementController', 'edit'])->name('bill.edit');
            });
            Route::middleware(['permission:finance.manage'])->group(function () use ($F) {
                Route::post('bill', [$F.'BillStatementController', 'store'])->name('bill.store');
                Route::put('bill/{bill}', [$F.'BillStatementController', 'update'])->name('bill.update');
                Route::post('bill/{bill}/toggle', [$F.'BillStatementController', 'toggle'])->name('bill.toggle');
            });
        });

        // --- Landing Page ---
        Route::middleware(['permission:landing.view'])->get('landing-pages', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'index'])->name('landing.index');
        Route::middleware(['permission:landing.view'])->get('landing-pages/products/search', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'searchProducts'])->name('landing.products.search');
        Route::middleware(['permission:landing.create'])->group(function () {
            Route::get('landing-pages/create', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'create'])->name('landing.create');
            Route::post('landing-pages', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'store'])->name('landing.store');
        });
        Route::middleware(['permission:landing.update'])->group(function () {
            Route::get('landing-pages/{landingPage}/edit', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'edit'])->name('landing.edit');
            Route::put('landing-pages/{landingPage}', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'update'])->name('landing.update');
            Route::post('landing-pages/{landingPage}/toggle', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'toggleStatus'])->name('landing.toggle');
            Route::post('landing-pages/auto-create-setting', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'toggleAutoCreate'])->name('landing.auto-create');
        });
        Route::middleware(['permission:landing.delete'])->delete('landing-pages/{landingPage}', [\App\Modules\LandingPage\Http\Controllers\LandingPageController::class, 'destroy'])->name('landing.destroy');

        // --- Offers (placement-based storefront offers) ---
        Route::middleware(['permission:promotion.view'])->group(function () {
            Route::get('offers', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'index'])->name('offers.index');
            Route::get('offers/create', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'create'])->name('offers.create');
            Route::get('offers/{offer}/edit', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'edit'])->name('offers.edit');
        });
        Route::middleware(['permission:promotion.create'])->post('offers', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'store'])->name('offers.store');
        Route::middleware(['permission:promotion.update'])->group(function () {
            Route::put('offers/{offer}', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'update'])->name('offers.update');
            Route::post('offers/{offer}/toggle', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'toggleStatus'])->name('offers.toggle');
        });
        Route::middleware(['permission:promotion.delete'])->delete('offers/{offer}', [\App\Modules\Promotion\Http\Controllers\OfferController::class, 'destroy'])->name('offers.destroy');

        // ===== Campaign / Offer group =====

        // --- Offer Banner (promotional banners; reuses the slider table) ---
        Route::middleware(['permission:theme.view'])->group(function () {
            Route::get('offer-banners', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'index'])->name('offer-banners.index');
            Route::get('offer-banners/create', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'create'])->name('offer-banners.create');
            Route::get('offer-banners/{offerBanner}/edit', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'edit'])->name('offer-banners.edit');
        });
        Route::middleware(['permission:theme.update'])->group(function () {
            Route::post('offer-banners', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'store'])->name('offer-banners.store');
            Route::put('offer-banners/{offerBanner}', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'update'])->name('offer-banners.update');
            Route::post('offer-banners/{offerBanner}/toggle', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'toggleStatus'])->name('offer-banners.toggle');
            Route::delete('offer-banners/{offerBanner}', [\App\Modules\Storefront\Http\Controllers\OfferBannerController::class, 'destroy'])->name('offer-banners.destroy');
        });

        // --- Combo Products (bundle products at a combo price) ---
        Route::middleware(['permission:combo.view'])->group(function () {
            Route::get('combos', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'index'])->name('combos.index');
            Route::get('combos/products/search', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'searchProducts'])->name('combos.products.search');
            Route::get('combos/create', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'create'])->name('combos.create');
            Route::get('combos/{combo}/edit', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'edit'])->name('combos.edit');
        });
        Route::middleware(['permission:combo.create'])->post('combos', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'store'])->name('combos.store');
        Route::middleware(['permission:combo.update'])->put('combos/{combo}', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'update'])->name('combos.update');
        Route::middleware(['permission:combo.delete'])->delete('combos/{combo}', [\App\Modules\Combo\Http\Controllers\ComboController::class, 'destroy'])->name('combos.destroy');

        // --- Free Delivery Products (per-product free shipping) ---
        Route::middleware(['permission:product.view'])->group(function () {
            Route::get('free-delivery', [\App\Modules\Product\Http\Controllers\FreeDeliveryController::class, 'index'])->name('free-delivery.index');
            Route::get('free-delivery/products/search', [\App\Modules\Product\Http\Controllers\FreeDeliveryController::class, 'searchProducts'])->name('free-delivery.products.search');
        });
        Route::middleware(['permission:product.update'])->group(function () {
            Route::post('free-delivery', [\App\Modules\Product\Http\Controllers\FreeDeliveryController::class, 'store'])->name('free-delivery.store');
            Route::delete('free-delivery/{product}', [\App\Modules\Product\Http\Controllers\FreeDeliveryController::class, 'destroy'])->name('free-delivery.destroy');
        });

        // --- Coupons (discount codes; applied at checkout by CouponService) ---
        Route::middleware(['permission:promotion.view'])->group(function () {
            Route::get('coupons', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'index'])->name('coupons.index');
            Route::get('coupons/create', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'create'])->name('coupons.create');
            Route::get('coupons/{coupon}/edit', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'edit'])->name('coupons.edit');
        });
        Route::middleware(['permission:promotion.create'])->post('coupons', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'store'])->name('coupons.store');
        Route::middleware(['permission:promotion.update'])->group(function () {
            Route::put('coupons/{coupon}', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'update'])->name('coupons.update');
            Route::post('coupons/{coupon}/toggle', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'toggleStatus'])->name('coupons.toggle');
        });
        Route::middleware(['permission:promotion.delete'])->delete('coupons/{coupon}', [\App\Modules\Promotion\Http\Controllers\CouponController::class, 'destroy'])->name('coupons.destroy');

        // --- Setting & Configuration ---
        $CFG = '\App\Modules\Settings\Http\Controllers\\';
        foreach (['marketing', 'courier', 'payment', 'sms', 'email', 'google', 'verification', 'invoice', 'delivery', 'order', 'social', 'protection'] as $pg) {
            Route::middleware(['permission:settings.view'])->get("config/$pg", [$CFG.'ConfigurationController', 'show'])->defaults('page', $pg)->name("config.$pg");
            Route::middleware(['permission:settings.update'])->put("config/$pg", [$CFG.'ConfigurationController', 'save'])->defaults('page', $pg)->name("config.$pg.save");
        }
        Route::middleware(['permission:settings.update'])->post('config/sms/test', [$CFG.'ConfigurationController', 'testSms'])->name('config.sms.test');
        Route::middleware(['permission:settings.view'])->group(function () use ($CFG) {
            Route::get('cities', [$CFG.'CityController', 'index'])->name('cities.index');
            Route::get('cities/create', [$CFG.'CityController', 'create'])->name('cities.create');
            Route::get('cities/{city}/edit', [$CFG.'CityController', 'edit'])->name('cities.edit');
            Route::get('sub-cities', [$CFG.'SubCityController', 'index'])->name('subcities.index');
            Route::get('sub-cities/create', [$CFG.'SubCityController', 'create'])->name('subcities.create');
            Route::get('sub-cities/{subcity}/edit', [$CFG.'SubCityController', 'edit'])->name('subcities.edit');
        });
        Route::middleware(['permission:settings.update'])->group(function () use ($CFG) {
            Route::post('cities', [$CFG.'CityController', 'store'])->name('cities.store');
            Route::put('cities/{city}', [$CFG.'CityController', 'update'])->name('cities.update');
            Route::post('cities/{city}/toggle', [$CFG.'CityController', 'toggleStatus'])->name('cities.toggle');
            Route::delete('cities/{city}', [$CFG.'CityController', 'destroy'])->name('cities.destroy');
            Route::post('sub-cities', [$CFG.'SubCityController', 'store'])->name('subcities.store');
            Route::put('sub-cities/{subcity}', [$CFG.'SubCityController', 'update'])->name('subcities.update');
            Route::post('sub-cities/{subcity}/toggle', [$CFG.'SubCityController', 'toggleStatus'])->name('subcities.toggle');
            Route::delete('sub-cities/{subcity}', [$CFG.'SubCityController', 'destroy'])->name('subcities.destroy');
        });

        // --- Customers (list + profile + block/unblock + export) ---
        Route::middleware(['permission:customer.view'])->group(function () {
            Route::get('customers', [\App\Modules\Customer\Http\Controllers\CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/export', [\App\Modules\Customer\Http\Controllers\CustomerController::class, 'export'])->name('customers.export');
            Route::get('customers/{customer}', [\App\Modules\Customer\Http\Controllers\CustomerController::class, 'show'])->name('customers.show');
        });
        Route::middleware(['permission:customer.blacklist.manage'])->group(function () {
            Route::post('customers/{customer}/block', [\App\Modules\Customer\Http\Controllers\CustomerController::class, 'block'])->name('customers.block');
            Route::post('customers/{customer}/unblock', [\App\Modules\Customer\Http\Controllers\CustomerController::class, 'unblock'])->name('customers.unblock');
        });

        // --- Incomplete Orders (abandoned checkouts captured live before submit) ---
        Route::middleware(['permission:recovery.view'])->group(function () {
            Route::get('incomplete-orders', [\App\Modules\Recovery\Http\Controllers\RecoveryController::class, 'index'])->name('recoveries.index');
            Route::get('incomplete-orders/{recovery}', [\App\Modules\Recovery\Http\Controllers\RecoveryController::class, 'show'])->name('recoveries.show');
        });
        Route::middleware(['permission:recovery.update'])->post('incomplete-orders/{recovery}/status', [\App\Modules\Recovery\Http\Controllers\RecoveryController::class, 'updateStatus'])->name('recoveries.status');

        // --- Admin (staff user management + per-admin permissions) ---
        $ADM = \App\Modules\AdminAuth\Http\Controllers\StaffManagementController::class;
        Route::middleware(['permission:staff.view'])->group(function () use ($ADM) {
            Route::get('admins', [$ADM, 'index'])->name('admins.index');
            Route::get('admins/{admin}/permissions', [$ADM, 'permissions'])->name('admins.permissions');
        });
        Route::middleware(['permission:staff.create'])->group(function () use ($ADM) {
            Route::get('admins/create', [$ADM, 'create'])->name('admins.create');
            Route::post('admins', [$ADM, 'store'])->name('admins.store');
        });
        Route::middleware(['permission:staff.update'])->group(function () use ($ADM) {
            Route::get('admins/{admin}/edit', [$ADM, 'edit'])->name('admins.edit');
            Route::put('admins/{admin}', [$ADM, 'update'])->name('admins.update');
            Route::post('admins/{admin}/toggle', [$ADM, 'toggleStatus'])->name('admins.toggle');
            Route::post('admins/{admin}/reset-password', [$ADM, 'resetPassword'])->name('admins.reset-password');
            Route::post('admins/{admin}/permissions', [$ADM, 'savePermissions'])->name('admins.permissions.save');
        });

        // --- Website Setup (theme header/footer/color/font + CMS pages) ---
        $WS = '\App\Modules\Theme\Http\Controllers\WebsiteSetupController';
        foreach (['header', 'footer', 'homepage', 'theme', 'font', 'promotions'] as $wp) {
            Route::middleware(['permission:theme.view'])->get("website/$wp", [$WS, 'show'])->defaults('page', $wp)->name("website.$wp");
            Route::middleware(['permission:theme.update'])->put("website/$wp", [$WS, 'save'])->defaults('page', $wp)->name("website.$wp.save");
        }
        $CMS = '\App\Modules\Storefront\Http\Controllers\CmsPageController';
        Route::middleware(['permission:theme.view'])->group(function () use ($CMS) {
            Route::get('pages', [$CMS, 'index'])->name('pages.index');
            Route::get('pages/create', [$CMS, 'create'])->name('pages.create');
            Route::get('pages/{cmsPage}/edit', [$CMS, 'edit'])->name('pages.edit');
        });
        Route::middleware(['permission:theme.update'])->group(function () use ($CMS) {
            Route::post('pages', [$CMS, 'store'])->name('pages.store');
            Route::put('pages/{cmsPage}', [$CMS, 'update'])->name('pages.update');
            Route::post('pages/{cmsPage}/toggle', [$CMS, 'toggleStatus'])->name('pages.toggle');
            Route::delete('pages/{cmsPage}', [$CMS, 'destroy'])->name('pages.destroy');
        });

        // --- Courier ---
        Route::middleware(['permission:courier.view'])->get('courier', [CourierController::class, 'index'])->name('courier.index');
        Route::middleware(['permission:courier.assign'])->group(function () {
            Route::post('courier/assign', [CourierController::class, 'assign'])->name('courier.assign');
            Route::post('courier/shipments/{shipment}/push', [CourierController::class, 'pushToApi'])->name('courier.shipments.push');
            Route::post('courier/shipments/{shipment}/sync-status', [CourierController::class, 'syncStatus'])->name('courier.shipments.sync-status');
        });

        // --- License verification — deliberately not permission-gated (any
        // logged-in staff member must be able to fix a blocked install, not
        // just whoever has settings.update) and reachable even while the
        // license is blocked; see EnsureLicenseIsValid's self-exemption and
        // AdminAccess's route-name check for how that's guaranteed. ---
        $LIC = \App\Modules\License\Http\Controllers\LicenseController::class;
        Route::get('license-verification', [$LIC, 'verification'])->name('license.verification');
        Route::get('license/status', [$LIC, 'status'])->name('license.status');
        Route::post('license/activate', [$LIC, 'activate'])->name('license.activate');
        Route::post('license/recheck', [$LIC, 'recheck'])->middleware('throttle:license-recheck')->name('license.recheck');
    });
});

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/pages/{cmsPage:slug}', [StorefrontController::class, 'cmsPageShow'])->name('storefront.cms-pages.show');
Route::get('/{landingPage:slug}', [StorefrontController::class, 'landingPageShow'])->name('storefront.landing-pages.show');
