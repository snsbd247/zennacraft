<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * checkout.success previously had no signed-URL protection, unlike its
 * sibling checkout.invoice one line below it in routes/web.php — despite
 * exposing the same PII (customer_name in its own markup) and, critically,
 * embedding a valid signed link to the full invoice (customer name, phone,
 * address, itemized order) via URL::signedRoute() in the very page anyone
 * could reach with nothing but a guessed/leaked order_number. Signing the
 * invoice route was meaningless protection if the page that hands out a
 * working signed link to it was itself unprotected. Fixed by signing
 * checkout.success too, matching the existing checkout.invoice precedent
 * exactly (signed, no throttle — that route has none either).
 */
class CheckoutSuccessProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    protected function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'status' => 'active']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 1000.00, 'stock' => 10, 'status' => 'active',
        ]);
    }

    public function test_real_checkout_redirects_to_a_working_signed_success_page(): void
    {
        $product = $this->makeProduct();

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer', 'phone' => '01712345699',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka', 'product_id' => $product->id, 'quantity' => 1,
        ]);

        $response->assertRedirect();
        $successUrl = $response->headers->get('Location');

        // The redirect target itself must be a genuinely valid signed URL,
        // not just any URL.
        $this->assertStringContainsString('signature=', $successUrl);

        $follow = $this->get($successUrl);
        $follow->assertOk();
        $follow->assertSee('Jane Customer');
    }

    public function test_guessing_the_order_number_without_a_signature_is_rejected(): void
    {
        $order = Order::create([
            'order_number' => 'ZC-GUESS-TEST', 'customer_name' => 'Secret Customer',
            'customer_phone' => '01799990000', 'address' => 'Private Address, Dhaka',
            'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000, 'status' => 'pending',
        ]);

        // Someone who only obtained the order_number (leaked via a
        // Referer header, browser history, a shared screenshot, etc.) —
        // not the signed URL — must not be able to load the page.
        $response = $this->get('/checkout/success/'.$order->order_number);

        $response->assertForbidden();
        $response->assertDontSee('Secret Customer');
    }

    public function test_a_tampered_signature_is_also_rejected(): void
    {
        $order = Order::create([
            'order_number' => 'ZC-TAMPER-TEST', 'customer_name' => 'Another Customer',
            'customer_phone' => '01799990001', 'address' => 'Another Address, Dhaka',
            'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000, 'status' => 'pending',
        ]);

        // A validly-signed URL for a DIFFERENT order must not grant access
        // to this one.
        $otherOrder = Order::create([
            'order_number' => 'ZC-OTHER-ORDER', 'customer_name' => 'Other', 'customer_phone' => '01799990002',
            'address' => 'X', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000, 'status' => 'pending',
        ]);
        $signedForOther = URL::signedRoute('checkout.success', ['order' => $otherOrder->order_number]);
        $swapped = str_replace($otherOrder->order_number, $order->order_number, $signedForOther);

        $response = $this->get($swapped);

        $response->assertForbidden();
    }
}
