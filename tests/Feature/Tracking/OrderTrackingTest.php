<?php

namespace Tests\Feature\Tracking;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function orderWithItem(string $number = 'ZC-TRK-1'): Order
    {
        $product = Product::create(['name' => 'African Honey', 'slug' => 'honey-'.uniqid(), 'sku' => strtoupper(Str::random(5)), 'price' => 1100, 'status' => 'active', 'stock' => 10]);
        $order = Order::create(['order_number' => $number, 'customer_name' => 'Rahim', 'customer_phone' => '01711100000', 'address' => 'Amtali', 'district' => 'Barguna', 'subtotal' => 1100, 'delivery_fee' => 130, 'total' => 1230, 'paid_amount' => 0, 'payment_method' => 'cod', 'status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'African Honey', 'sku' => 'H-1', 'price' => 1100, 'quantity' => 1, 'subtotal' => 1100]);

        return $order;
    }

    public function test_public_tracking_by_order_number_shows_the_live_view(): void
    {
        $this->orderWithItem('ZC-TRK-1');

        $this->get('/track?order=ZC-TRK-1')->assertOk()
            ->assertSee('Track Your Order')
            ->assertSee('ZC-TRK-1')
            ->assertSee('Order Timeline')
            ->assertSee('Order Placed')
            ->assertSee('African Honey')
            ->assertSee('Delivery Address')
            ->assertSee('Amount Due');
    }

    public function test_unknown_order_number_shows_not_found(): void
    {
        $this->get('/track?order=NOPE-999')->assertOk()->assertSee('No order found');
    }

    public function test_empty_track_page_prompts_for_a_number(): void
    {
        $this->get('/track')->assertOk()->assertSee('Enter your order number');
    }

    public function test_checkout_success_shows_the_order_placed_popup(): void
    {
        $order = $this->orderWithItem('ZC-TRK-2');
        $url = URL::signedRoute('checkout.success', ['order' => $order->order_number]);

        $this->get($url)->assertOk()
            ->assertSee('Order Placed!')
            ->assertSee('data-op', false)   // the popup element
            ->assertSee('African Honey')
            ->assertSee('Order Timeline');
    }
}
