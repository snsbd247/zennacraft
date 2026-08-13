<?php

namespace Tests\Feature\Customer;

use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use Database\Seeders\GeneralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeneralSettingsSeeder::class);
    }

    private function customerWithOrder(): Customer
    {
        $customer = Customer::create(['name' => 'Rahim Uddin', 'phone' => '01403261159', 'email' => 'r@x.test']);
        $order = Order::create([
            'order_number' => 'ZC-DASH-1', 'customer_id' => $customer->id,
            'customer_name' => 'Rahim Uddin', 'customer_phone' => '01403261159', 'address' => 'Dhaka',
            'subtotal' => 1200, 'delivery_fee' => 0, 'total' => 1200, 'status' => 'delivered', 'source' => 'website',
        ]);
        $order->items()->create(['product_name' => 'Kantha Throw', 'sku' => 'K1', 'price' => 1200, 'quantity' => 1, 'subtotal' => 1200]);

        return $customer;
    }

    public function test_dashboard_renders_premium_with_stats_and_orders(): void
    {
        $customer = $this->customerWithOrder();

        $res = $this->withSession(['customer_id' => $customer->id])->get(route('customer.dashboard'));

        $res->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Rahim Uddin')
            ->assertSee('Total orders')
            ->assertSee('ZC-DASH-1')
            ->assertSee('Recent orders')
            ->assertSee('Loyalty');
    }

    public function test_profile_update_via_ajax_returns_json(): void
    {
        $customer = $this->customerWithOrder();

        $res = $this->withSession(['customer_id' => $customer->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->patch(route('customer.profile.update'), ['name' => 'New Name', 'email' => 'new@x.test']);

        $res->assertOk()->assertJson(['success' => true, 'customer' => ['name' => 'New Name', 'email' => 'new@x.test']]);
        $this->assertSame('New Name', $customer->fresh()->name);
    }


    public function test_order_tracking_uses_premium_view(): void
    {
        $customer = $this->customerWithOrder();
        $order = $customer->orders()->first();

        $res = $this->withSession(['customer_id' => $customer->id])->get(route('customer.orders.tracking', $order));

        $res->assertOk()
            ->assertSee('Order #'.$order->order_number)
            ->assertSee('Order Placed')   // premium 6-step timeline label
            ->assertSee('Delivered')      // step label
            ->assertSee('Kantha Throw');  // product from the premium items panel
    }

    public function test_dashboard_redirects_to_login_when_not_authenticated(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
    }
}
