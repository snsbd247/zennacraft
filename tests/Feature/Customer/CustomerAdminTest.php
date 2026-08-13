<?php

namespace Tests\Feature\Customer;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Cust Owner', 'email' => 'cust-owner@zennacraft.test', 'phone' => '+8801700000210', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function customer(string $name = 'Rahim', string $phone = '+8801711100000'): Customer
    {
        return Customer::create(['name' => $name, 'phone' => $phone, 'address' => 'Madaripur', 'total_orders' => 0]);
    }

    public function test_index_lists_and_searches_customers(): void
    {
        $owner = $this->owner();
        $this->customer('Mahmuda', '+8801966451375');
        $this->customer('Asraful Islam', '+8801333114825');

        $this->actingAs($owner, 'staff')->get(route('customers.index'))
            ->assertOk()->assertSee('Customers')->assertSee('Mahmuda')->assertSee('Asraful Islam');

        // search by a fragment of the phone
        $this->actingAs($owner, 'staff')->get(route('customers.index', ['q' => '01966']))
            ->assertOk()->assertSee('Mahmuda')->assertDontSee('Asraful Islam');
    }

    public function test_profile_shows_order_and_add_to_cart_history(): void
    {
        $owner = $this->owner();
        $customer = $this->customer();
        $product = Product::create(['name' => 'Premium Hoodie', 'slug' => 'ph-'.uniqid(), 'sku' => 'PH-1', 'price' => 1200, 'status' => 'active', 'stock' => 5]);

        $order = Order::create(['customer_id' => $customer->id, 'order_number' => 'ZC-CUST-1', 'customer_name' => 'Rahim', 'customer_phone' => $customer->phone, 'address' => 'Madaripur', 'subtotal' => 1200, 'delivery_fee' => 60, 'total' => 1260, 'status' => 'delivered']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'Premium Hoodie', 'sku' => 'PH-1', 'price' => 1200, 'quantity' => 1, 'subtotal' => 1200]);

        CustomerBehaviorEvent::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'event_type' => 'added_to_cart', 'occurred_at' => now()]);

        $this->actingAs($owner, 'staff')->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Order history')
            ->assertSee('ZC-CUST-1')
            ->assertSee('Cart products')            // the cart-products panel
            ->assertSee('Premium Hoodie')           // product name
            ->assertSee('PH-1')                      // SKU
            ->assertSee('৳1,200');                   // price
    }

    public function test_cart_products_are_deduped_to_distinct_products(): void
    {
        $owner = $this->owner();
        $customer = $this->customer();
        $product = Product::create(['name' => 'Sling Bag', 'slug' => 'sb-'.uniqid(), 'sku' => 'BAG-3', 'price' => 850, 'status' => 'active', 'stock' => 9]);

        // Same product added twice -> should appear once.
        CustomerBehaviorEvent::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'event_type' => 'added_to_cart', 'occurred_at' => now()->subMinutes(5)]);
        CustomerBehaviorEvent::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'event_type' => 'cart_updated', 'occurred_at' => now()]);

        $html = $this->actingAs($owner, 'staff')->get(route('customers.show', $customer))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'BAG-3'));
    }

    public function test_block_and_unblock_customer(): void
    {
        $owner = $this->owner();
        $customer = $this->customer();

        $this->actingAs($owner, 'staff')->postJson(route('customers.block', $customer))
            ->assertOk()->assertJson(['blocked' => true]);
        $this->assertTrue(CustomerBlacklist::where('customer_id', $customer->id)->where('active', true)->exists());

        $this->actingAs($owner, 'staff')->postJson(route('customers.unblock', $customer))
            ->assertOk()->assertJson(['blocked' => false]);
        $this->assertFalse(CustomerBlacklist::where('customer_id', $customer->id)->where('active', true)->exists());
    }

    public function test_export_returns_csv_of_customers(): void
    {
        $owner = $this->owner();
        $this->customer('Exported One', '+8801700000999');

        $response = $this->actingAs($owner, 'staff')->get(route('customers.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Exported One', $response->streamedContent());
    }

    public function test_staff_without_customer_permission_is_blocked(): void
    {
        $this->owner();
        $stranger = StaffUser::create(['name' => 'No Perm', 'email' => 'noperm-cust@zennacraft.test', 'phone' => '+8801700000211', 'password' => 'Password123!', 'status' => 'active']);

        $this->actingAs($stranger, 'staff')->get(route('customers.index'))->assertForbidden();
    }
}
