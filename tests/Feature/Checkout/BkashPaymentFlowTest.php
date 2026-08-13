<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function configureBkash(): void
    {
        $settings = app(SettingService::class);
        $settings->set('payment', 'bkash_enabled', true, 'boolean');
        $settings->set('payment', 'bkash_sandbox', true, 'boolean');
        $settings->set('payment', 'bkash_app_key', 'APPKEY');
        $settings->setEncrypted('payment', 'bkash_app_secret', 'APPSECRET');
        $settings->set('payment', 'bkash_username', 'USERNAME');
        $settings->setEncrypted('payment', 'bkash_password', 'PASSWORD');
    }

    private function makeProduct(float $price = 1000.00): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'status' => 'active']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => $price, 'stock' => 10, 'status' => 'active',
        ]);
    }

    private function fakeGrantAndCreate(string $paymentId = 'PAY123', string $bkashUrl = 'https://checkout.sandbox.bka.sh/pay/PAY123'): void
    {
        Http::fake([
            '*checkout.sandbox.bka.sh*/checkout/token/grant' => Http::response(['id_token' => 'ID_TOKEN', 'refresh_token' => 'R', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            '*checkout.sandbox.bka.sh*/checkout/payment/create' => Http::response(['paymentID' => $paymentId, 'bkashURL' => $bkashUrl, 'statusCode' => '0000', 'statusMessage' => 'Successful']),
        ]);
    }

    public function test_checkout_with_bkash_redirects_to_the_bkash_hosted_page(): void
    {
        $this->configureBkash();
        $this->fakeGrantAndCreate();
        $product = $this->makeProduct(1000.00);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer', 'phone' => '01712345691',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka',
            'payment_method' => 'bkash',
            'product_id' => $product->id, 'quantity' => 1,
        ]);

        $response->assertRedirect('https://checkout.sandbox.bka.sh/pay/PAY123');

        $order = Order::where('customer_phone', '01712345691')->firstOrFail();
        $this->assertSame('PAY123', $order->payment_gateway_reference);
        $this->assertSame('pending', $order->payment_status);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/payment/create')
            && $request['merchantInvoiceNumber'] === $order->order_number);
    }

    public function test_checkout_falls_back_to_the_normal_success_page_when_bkash_create_fails(): void
    {
        $this->configureBkash();
        Http::fake([
            '*checkout.sandbox.bka.sh*/checkout/token/grant' => Http::response(['id_token' => 'ID_TOKEN', 'expires_in' => 3600]),
            '*checkout.sandbox.bka.sh*/checkout/payment/create' => Http::response(['statusCode' => '2001', 'statusMessage' => 'Invalid amount'], 200),
        ]);
        $product = $this->makeProduct(1000.00);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer', 'phone' => '01712345692',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka',
            'payment_method' => 'bkash',
            'product_id' => $product->id, 'quantity' => 1,
        ]);

        // No bkash_url returned -> falls through to the normal signed success redirect.
        $response->assertRedirect();
        $this->assertStringContainsString('checkout/success', $response->headers->get('Location'));

        $order = Order::where('customer_phone', '01712345692')->firstOrFail();
        $this->assertNull($order->payment_gateway_reference);
    }

    public function test_callback_with_successful_status_executes_payment_and_marks_order_paid(): void
    {
        $this->configureBkash();
        $order = Order::create([
            'order_number' => 'ZC-BK-'.uniqid(), 'customer_name' => 'Jane', 'customer_phone' => '01712345693',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 80, 'total' => 1080, 'status' => 'pending',
            'source' => 'website', 'payment_method' => 'bkash', 'payment_gateway_reference' => 'PAY555', 'payment_status' => 'pending',
        ]);
        Http::fake([
            '*checkout.sandbox.bka.sh*/checkout/token/grant' => Http::response(['id_token' => 'ID_TOKEN', 'expires_in' => 3600]),
            '*checkout.sandbox.bka.sh*/checkout/payment/execute/PAY555' => Http::response([
                'paymentID' => 'PAY555', 'trxID' => 'TRX999', 'transactionStatus' => 'Completed',
                'amount' => '1080', 'statusCode' => '0000', 'statusMessage' => 'Successful',
            ]),
        ]);

        $response = $this->get(route('checkout.bkash.callback', ['paymentID' => 'PAY555', 'status' => 'success']));

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('TRX999', $order->payment_transaction_id);
        $this->assertSame('bkash', $order->paid_by);
        $this->assertEquals(1080.0, (float) $order->paid_amount);
    }

    public function test_callback_with_failure_status_marks_the_order_failed_without_calling_execute(): void
    {
        $this->configureBkash();
        $order = Order::create([
            'order_number' => 'ZC-BK-'.uniqid(), 'customer_name' => 'Jane', 'customer_phone' => '01712345694',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 80, 'total' => 1080, 'status' => 'pending',
            'source' => 'website', 'payment_method' => 'bkash', 'payment_gateway_reference' => 'PAY556', 'payment_status' => 'pending',
        ]);
        Http::fake();

        $response = $this->get(route('checkout.bkash.callback', ['paymentID' => 'PAY556', 'status' => 'cancel']));

        $response->assertRedirect();
        $this->assertSame('failed', $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_callback_for_an_unknown_payment_id_404s(): void
    {
        $this->configureBkash();
        Http::fake();

        $this->get(route('checkout.bkash.callback', ['paymentID' => 'NOPE', 'status' => 'success']))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_callback_is_idempotent_for_an_already_paid_order(): void
    {
        $this->configureBkash();
        $order = Order::create([
            'order_number' => 'ZC-BK-'.uniqid(), 'customer_name' => 'Jane', 'customer_phone' => '01712345695',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 80, 'total' => 1080, 'status' => 'pending',
            'source' => 'website', 'payment_method' => 'bkash', 'payment_gateway_reference' => 'PAY557',
            'payment_status' => 'paid', 'payment_transaction_id' => 'TRX_OLD',
        ]);
        Http::fake();

        $response = $this->get(route('checkout.bkash.callback', ['paymentID' => 'PAY557', 'status' => 'success']));

        $response->assertRedirect();
        $this->assertSame('TRX_OLD', $order->fresh()->payment_transaction_id);
        Http::assertNothingSent();
    }
}
