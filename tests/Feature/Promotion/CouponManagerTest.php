<?php

namespace Tests\Feature\Promotion;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Coupon Owner', 'email' => 'coupon-owner@zennacraft.test', 'phone' => '+8801700000133', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_index_and_create_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('coupons.index'))->assertOk()->assertSee('Coupons');
        $this->actingAs($owner, 'staff')->get(route('coupons.create'))->assertOk()->assertSee('Coupon Code');
    }

    public function test_store_creates_coupon_with_uppercased_code(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('coupons.store'), [
            'code' => 'save10', 'name' => 'Save 10%', 'discount_type' => 'percentage',
            'discount_value' => 10, 'min_order_amount' => 0, 'status' => 'active',
        ])->assertRedirect(route('coupons.index'));

        $coupon = Coupon::firstOrFail();
        $this->assertSame('SAVE10', $coupon->code);
        $this->assertSame('percentage', $coupon->discount_type);
        $this->assertSame('all', $coupon->applies_to);
        $this->assertSame($owner->id, $coupon->created_by);
    }

    public function test_free_shipping_coupon_needs_no_value(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('coupons.store'), [
            'code' => 'FREESHIP', 'name' => 'Free shipping', 'discount_type' => 'free_shipping', 'status' => 'active',
        ])->assertRedirect(route('coupons.index'));
        $this->assertEquals(0.0, (float) Coupon::where('code', 'FREESHIP')->firstOrFail()->discount_value);
    }

    public function test_toggle_status(): void
    {
        $owner = $this->owner();
        $coupon = Coupon::create(['code' => 'X1', 'name' => 'X', 'discount_type' => 'fixed', 'discount_value' => 50, 'status' => 'active', 'applies_to' => 'all', 'min_order_amount' => 0]);
        $this->actingAs($owner, 'staff')->postJson(route('coupons.toggle', $coupon))->assertOk()->assertJson(['status' => 'inactive']);
    }

    public function test_created_coupon_actually_discounts_at_checkout(): void
    {
        $this->owner();
        $product = Product::create(['name' => 'Ghee', 'slug' => 'ghee-x', 'sku' => 'GH-1', 'price' => 1000, 'status' => 'active', 'stock' => 10]);
        Coupon::create(['code' => 'TAKA100', 'name' => '৳100 off', 'discount_type' => 'fixed', 'discount_value' => 100, 'status' => 'active', 'applies_to' => 'all', 'min_order_amount' => 0]);

        $preview = app(CheckoutService::class)->preview($product->id, null, 1, 'TAKA100');

        $this->assertEquals(100.0, (float) $preview['discount_amount'], 'A ৳100 coupon must reduce the checkout total by 100.');
    }
}
