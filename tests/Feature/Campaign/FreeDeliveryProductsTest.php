<?php

namespace Tests\Feature\Campaign;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Checkout\Services\DeliveryChargeService;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FreeDeliveryProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'FD Owner', 'email' => 'fd-owner@zennacraft.test', 'phone' => '+8801700000203', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function product(bool $free = false, float $price = 1000): Product
    {
        return Product::create(['name' => 'Prod '.uniqid(), 'slug' => 'prod-'.uniqid(), 'sku' => strtoupper(Str::random(6)), 'price' => $price, 'status' => 'active', 'stock' => 10, 'free_delivery' => $free]);
    }

    public function test_owner_can_flag_and_unflag_a_product_for_free_delivery(): void
    {
        $owner = $this->owner();
        $p = $this->product();

        $this->actingAs($owner, 'staff')->postJson(route('free-delivery.store'), ['product_id' => $p->id])->assertOk();
        $this->assertTrue((bool) $p->fresh()->free_delivery);

        $this->actingAs($owner, 'staff')->get(route('free-delivery.index'))->assertOk()->assertSee('Free Delivery Products');

        $this->actingAs($owner, 'staff')->deleteJson(route('free-delivery.destroy', $p))->assertOk();
        $this->assertFalse((bool) $p->fresh()->free_delivery);
    }

    public function test_delivery_service_waives_the_fee_when_a_free_item_is_present(): void
    {
        app(SettingService::class)->set('delivery', 'outside_dhaka_charge', 170, 'string', true);
        app(SettingService::class)->set('delivery', 'free_delivery_threshold', 0, 'string', true);
        $service = app(DeliveryChargeService::class);

        $this->assertEquals(170.0, $service->feeFor(DeliveryChargeService::ZONE_OUTSIDE_DHAKA, 100, false));
        $this->assertEquals(0.0, $service->feeFor(DeliveryChargeService::ZONE_OUTSIDE_DHAKA, 100, true));
    }

    public function test_a_free_delivery_product_in_the_cart_makes_the_whole_order_ship_free(): void
    {
        app(SettingService::class)->set('delivery', 'outside_dhaka_charge', 170, 'string', true);
        app(SettingService::class)->set('delivery', 'free_delivery_threshold', 0, 'string', true);
        $product = $this->product(true, 1000);

        $this->post('/checkout', [
            'name' => 'Free Ship', 'phone' => '01712345699',
            'address' => '123 Test Road, Outside Dhaka', 'delivery_zone' => 'outside_dhaka',
            'product_id' => $product->id, 'quantity' => 1,
        ])->assertRedirect();

        $order = Order::where('customer_phone', '01712345699')->firstOrFail();
        $this->assertEquals(0.0, (float) $order->delivery_fee);
        $this->assertEquals(1000.0, (float) $order->total);
    }
}
