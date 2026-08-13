<?php

namespace Tests\Feature\Checkout;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_a_clean_standalone_document_without_storefront_chrome(): void
    {
        Http::fake();
        $product = Product::create(['name' => 'Wild Honey', 'slug' => 'wh-'.uniqid(), 'sku' => strtoupper(Str::random(5)), 'price' => 1100, 'status' => 'active', 'stock' => 5]);
        $order = Order::create(['order_number' => 'ZC-INV-1', 'customer_name' => 'Rahim', 'customer_phone' => '01711100000', 'address' => 'Dhaka', 'subtotal' => 1100, 'delivery_fee' => 130, 'total' => 1230, 'paid_amount' => 0, 'payment_method' => 'cod', 'status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'Wild Honey', 'sku' => 'H-1', 'price' => 1100, 'quantity' => 1, 'subtotal' => 1100]);

        $url = URL::signedRoute('checkout.invoice', ['order' => $order->order_number]);
        $res = $this->get($url)->assertOk();

        // Professional invoice content
        $res->assertSee('INVOICE')->assertSee('ZC-INV-1')->assertSee('Bill To')->assertSee('Wild Honey')->assertSee('Amount Due');
        // No storefront chrome (announcement bar / category nav / footer social)
        $res->assertDontSee('zc-announce', false);
        $res->assertDontSee('Cash on delivery across Bangladesh');
    }
}
