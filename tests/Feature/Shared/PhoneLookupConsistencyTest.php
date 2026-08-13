<?php

namespace Tests\Feature\Shared;

use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * whereNormalizedPhone() was duplicated byte-for-byte across 8 classes
 * (OrderSecurityService, CouponService, RecoveryService,
 * CustomerFraudService, CheckoutService, CustomerIntelligenceService,
 * CustomerAuthController, CustomerAuthApiController) before this
 * consolidation. Verified all 8 were functionally identical (two cosmetic-
 * only differences found: OrderSecurityService called an intermediate
 * phoneLookupValues() wrapper that itself delegated to PhoneService::
 * lookupValues(), and CustomerFraudService returned the query builder
 * instead of void — neither changed the SQL produced or affected any
 * caller, since every call site uses it inside a where(Closure) whose
 * return value Eloquent ignores). Now a single method on PhoneService.
 */
class PhoneLookupConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_where_normalized_phone_matches_all_equivalent_formats(): void
    {
        Customer::create(['name' => 'Format Test', 'phone' => '01712345678']);

        $phoneService = app(PhoneService::class);

        // Local format, international format, and formats needing space/
        // dash/plus stripping must all resolve to the same stored customer.
        $formatsToTry = ['01712345678', '8801712345678', '+8801712345678', '017-1234-5678', ' 01712345678 '];

        foreach ($formatsToTry as $format) {
            $match = Customer::where(fn ($query) => $phoneService->whereNormalizedPhone($query, 'phone', $format))->first();

            $this->assertNotNull($match, "Expected format '{$format}' to match the stored customer.");
            $this->assertSame('01712345678', $match->phone);
        }
    }

    public function test_where_normalized_phone_does_not_match_a_different_number(): void
    {
        Customer::create(['name' => 'Format Test', 'phone' => '01712345678']);

        $phoneService = app(PhoneService::class);

        $match = Customer::where(fn ($query) => $phoneService->whereNormalizedPhone($query, 'phone', '01799999999'))->first();

        $this->assertNull($match);
    }

    public function test_checkout_service_and_order_security_service_agree_on_the_same_customer_across_phone_formats(): void
    {
        Http::fake();

        $category = \App\Modules\Catalog\Models\Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'status' => 'active']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 500.00, 'stock' => 100, 'status' => 'active',
        ]);

        // First checkout: local format.
        $first = $this->post('/checkout', [
            'name' => 'Cross Format Customer', 'phone' => '01755512345',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka', 'product_id' => $product->id, 'quantity' => 1,
        ]);
        $first->assertRedirect();

        $firstOrder = Order::where('customer_phone', '01755512345')->firstOrFail();
        $customersAfterFirst = Customer::count();

        // Second checkout: same underlying number, international format
        // with a plus sign, but a different quantity — so the order total
        // differs and OrderSecurityService::assertNotRecentDuplicate()'s
        // (legitimate, unrelated) same-product-same-total-within-30-minutes
        // protection doesn't block it. That duplicate check itself relies
        // on whereNormalizedPhone() being consistent, which is exactly why
        // the first attempt at this test (quantity => 1 on both) tripped
        // it: the fix correctly recognized both requests as the same
        // customer, and the duplicate guard correctly did its job.
        $second = $this->post('/checkout', [
            'name' => 'Cross Format Customer', 'phone' => '+8801755512345',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka', 'product_id' => $product->id, 'quantity' => 2,
        ]);
        $second->assertRedirect();

        // No new Customer record should have been created for the second
        // order — both phone formats must resolve to the same customer.
        $this->assertSame($customersAfterFirst, Customer::count(), 'A different phone format must not create a duplicate customer.');

        $secondOrder = Order::where('id', '!=', $firstOrder->id)->latest()->firstOrFail();
        $this->assertSame($firstOrder->customer_id, $secondOrder->customer_id);
    }
}
