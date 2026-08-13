<?php

namespace Tests\Feature\System;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Theme\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetStoreContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_wipes_content_but_keeps_configuration(): void
    {
        // --- Config (must survive) ---
        app(SettingService::class)->set('sms', 'api_key', 'REAL-TOKEN', 'string');
        app(ThemeService::class)->set('brand_name', 'My Real Store');
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'reset-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $courier = CourierProvider::create(['name' => 'Steadfast', 'slug' => 'sf-'.uniqid(), 'status' => 'active']);

        // --- Content (must be wiped) ---
        $cat = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid(), 'status' => 'active']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Demo', 'slug' => 'demo-'.uniqid(), 'sku' => 'D-'.strtoupper(uniqid()), 'price' => 100, 'status' => 'active', 'stock' => 5]);
        $order = Order::create(['order_number' => 'ZC-DEMO-1', 'customer_name' => 'X', 'customer_phone' => '01700000000', 'address' => 'Dhaka', 'subtotal' => 100, 'delivery_fee' => 0, 'total' => 100, 'status' => 'pending', 'source' => 'website']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'Demo', 'sku' => 'D-1', 'price' => 100, 'quantity' => 1, 'subtotal' => 100]);
        Customer::create(['name' => 'Buyer', 'phone' => '01700000001']);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);

        $this->artisan('studio:reset-content --force')->assertSuccessful();

        // Content gone
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('customers', 0);

        // Config kept
        $this->assertSame('REAL-TOKEN', app(SettingService::class)->get('sms', 'api_key'));
        $this->assertSame('My Real Store', app(ThemeService::class)->get('brand_name'));
        $this->assertDatabaseHas('staff_users', ['id' => $staff->id]);
        $this->assertDatabaseHas('courier_providers', ['id' => $courier->id]);
        $this->assertTrue(DB::table('settings')->count() > 0);
    }
}
