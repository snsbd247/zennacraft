<?php

namespace Tests\Feature\Checkout;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuplicateOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function product(): Product
    {
        return Product::create(['name' => 'Dup Test', 'slug' => 'dup-'.uniqid(), 'sku' => strtoupper(Str::random(6)), 'price' => 1000, 'status' => 'active', 'stock' => 50]);
    }

    public function test_submitting_the_same_order_twice_does_not_create_a_duplicate(): void
    {
        $product = $this->product();
        $payload = [
            'name' => 'Repeat Buyer', 'phone' => '01712349999',
            'address' => '12 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id, 'quantity' => 1,
        ];

        // First submit succeeds.
        $this->post('/checkout', $payload)->assertRedirect();
        $this->assertSame(1, Order::count());

        // A second identical submit (e.g. double-click / network retry) is
        // blocked — no duplicate order is created.
        $this->post('/checkout', $payload);
        $this->assertSame(1, Order::count());
    }
}
