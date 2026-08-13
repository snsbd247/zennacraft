<?php

namespace Tests\Feature\Order;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the six Orders sub-pages rebuilt 2026-07-24 (Manage Order, Add
 * Exchange Order, Order Processing Report, Order Source, Order Processing
 * Note, Courier) and the new exchange-order backend.
 */
class OrderPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private ?StaffUser $ownerUser = null;

    protected function owner(): StaffUser
    {
        // Memoized — several tests act as the owner for multiple requests,
        // and the fixed phone would collide on a second create.
        if ($this->ownerUser) {
            return $this->ownerUser;
        }

        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Order Owner', 'email' => 'order-owner@zennacraft.test',
            'phone' => '+8801700000090', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    protected function order(string $status = 'pending', array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ZC-OP-'.uniqid(),
            'customer_name' => 'Buyer', 'customer_phone' => '017'.rand(10000000, 99999999),
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'product_cost_total' => 300, 'courier_cost_total' => 0, 'gross_profit' => 700,
            'status' => $status, 'source' => 'website',
        ], $overrides));
    }

    protected function product(): Product
    {
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid(), 'status' => 'active']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Kantha', 'slug' => 'kantha-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 1500.00, 'stock' => 20, 'status' => 'active',
        ]);
    }

    public function test_manage_order_lists_and_filters_orders(): void
    {
        $this->order('pending', ['order_number' => 'ZC-OP-PENDING']);
        $this->order('delivered', ['order_number' => 'ZC-OP-DELIVERED']);

        $response = $this->actingAs($this->owner(), 'staff')->get(route('orders.index'));
        $response->assertOk();
        $response->assertSee('ZC-OP-PENDING');
        $response->assertSee('ZC-OP-DELIVERED');

        // Filter to delivered only.
        $filtered = $this->actingAs($this->owner(), 'staff')->get(route('orders.index', ['status' => 'delivered']));
        $filtered->assertOk();
        $filtered->assertSee('ZC-OP-DELIVERED');
        $filtered->assertDontSee('ZC-OP-PENDING');
    }

    public function test_manage_order_respects_per_page_and_shows_verification_column(): void
    {
        $this->order('pending');

        $response = $this->actingAs($this->owner(), 'staff')->get(route('orders.index', ['per_page' => 100]));
        $response->assertOk();
        $response->assertSee('Call Verification');
        $this->assertSame(100, $response->viewData('perPage'));

        // Invalid per_page falls back to the default.
        $fallback = $this->actingAs($this->owner(), 'staff')->get(route('orders.index', ['per_page' => 999]));
        $this->assertSame(50, $fallback->viewData('perPage'));
    }

    public function test_call_verification_verified_outcome_confirms_the_order(): void
    {
        $order = $this->order('pending');

        $response = $this->actingAs($this->owner(), 'staff')
            ->post(route('orders.verify', $order), ['outcome' => 'verified']);

        $response->assertRedirect();
        $order->refresh();
        // A 'verified' outcome runs the standalone Verification service's
        // real flow, which confirms the order and marks it verified.
        $this->assertSame('verified', $order->verification_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertDatabaseHas('order_verification_attempts', ['order_id' => $order->id, 'outcome' => 'verified']);
    }

    public function test_call_verification_no_answer_records_attempt_without_confirming(): void
    {
        $order = $this->order('pending');

        $response = $this->actingAs($this->owner(), 'staff')
            ->post(route('orders.verify', $order), ['outcome' => 'no_answer']);

        $response->assertRedirect();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseHas('order_verification_attempts', ['order_id' => $order->id, 'outcome' => 'no_answer']);
    }

    public function test_manage_order_filters_by_district_and_creator(): void
    {
        $this->order('pending', ['order_number' => 'ZC-OP-DHK', 'district' => 'Dhaka', 'source' => 'website']);
        $this->order('pending', ['order_number' => 'ZC-OP-CTG', 'district' => 'Chattogram', 'source' => 'custom']);

        $owner = $this->owner();

        $byDistrict = $this->actingAs($owner, 'staff')->get(route('orders.index', ['district' => 'Dhaka']));
        $byDistrict->assertOk()->assertSee('ZC-OP-DHK')->assertDontSee('ZC-OP-CTG');

        // creator=admin → source 'custom' only.
        $byCreator = $this->actingAs($owner, 'staff')->get(route('orders.index', ['creator' => 'admin']));
        $byCreator->assertOk()->assertSee('ZC-OP-CTG')->assertDontSee('ZC-OP-DHK');
    }

    public function test_order_detail_and_status_update(): void
    {
        $order = $this->order('pending');

        $this->actingAs($this->owner(), 'staff')->get(route('orders.show', $order))->assertOk();

        $response = $this->actingAs($this->owner(), 'staff')
            ->post(route('orders.status', $order), ['status' => 'confirmed', 'note' => 'Called customer']);

        $response->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_order_source_page_renders_with_real_breakdown(): void
    {
        $this->order('delivered', ['source' => 'website', 'gross_profit' => 700]);
        $this->order('pending', ['source' => 'whatsapp']);

        $response = $this->actingAs($this->owner(), 'staff')->get(route('orders.source'));
        $response->assertOk();
        $this->assertSame(2, $response->viewData('totalOrders'));
        // delivered-only profit for website
        $this->assertEquals(700.0, (float) $response->viewData('deliveredProfit')['website']);
    }

    public function test_processing_report_renders(): void
    {
        $this->order('delivered');
        $this->order('cancelled');

        $response = $this->actingAs($this->owner(), 'staff')->get(route('orders.processing-report'));
        $response->assertOk();
        $this->assertSame(2, $response->viewData('totalOrders'));
        $this->assertEquals(50.0, $response->viewData('deliveryRate'));
        $this->assertEquals(50.0, $response->viewData('cancelRate'));
    }

    public function test_order_notes_index_and_store(): void
    {
        $order = $this->order();

        $this->actingAs($this->owner(), 'staff')->get(route('orders.notes.index'))->assertOk();

        $response = $this->actingAs($this->owner(), 'staff')->post(route('orders.notes.store'), [
            'order_number' => $order->order_number,
            'note' => 'Packed and ready',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('order_notes', ['order_id' => $order->id, 'note' => 'Packed and ready']);
    }

    public function test_exchange_order_creates_a_linked_order(): void
    {
        $original = $this->order('delivered', ['order_number' => 'ZC-OP-ORIG']);
        $product = $this->product();

        $this->actingAs($this->owner(), 'staff')->get(route('orders.exchange.create'))->assertOk();

        $response = $this->actingAs($this->owner(), 'staff')->post(route('orders.exchange.store'), [
            'original_order_id' => $original->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertRedirect();
        $exchange = Order::where('exchanged_from_order_id', $original->id)->first();
        $this->assertNotNull($exchange, 'Expected an exchange order linked to the original.');
        $this->assertSame($original->customer_name, $exchange->customer_name);
        $this->assertSame('custom', $exchange->source);
        $this->assertEquals(1500.0, (float) $exchange->total);
    }

    public function test_exchange_search_returns_matching_orders(): void
    {
        $this->order('delivered', ['order_number' => 'ZC-OP-FINDME']);

        $response = $this->actingAs($this->owner(), 'staff')
            ->getJson(route('orders.exchange.search', ['q' => 'FINDME']));

        $response->assertOk();
        $response->assertJsonFragment(['order_number' => 'ZC-OP-FINDME']);
    }

    public function test_courier_page_and_assignment(): void
    {
        $order = $this->order('confirmed');
        $provider = CourierProvider::create(['name' => 'Steadfast', 'slug' => 'steadfast-'.uniqid(), 'status' => 'active']);

        $this->actingAs($this->owner(), 'staff')->get(route('courier.index'))->assertOk();

        $response = $this->actingAs($this->owner(), 'staff')->post(route('courier.assign'), [
            'order_id' => $order->id,
            'courier_provider_id' => $provider->id,
            'tracking_number' => 'TRK-123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'courier_provider_id' => $provider->id]);
    }

    public function test_ajax_status_change_returns_the_refreshed_row(): void
    {
        $order = $this->order('pending');

        $response = $this->actingAs($this->owner(), 'staff')
            ->postJson(route('orders.status', $order), ['status' => 'confirmed']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        // The response carries the re-rendered row for in-place swap.
        $this->assertArrayHasKey('order-row-'.$order->id, $response->json('regions'));
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_order_comment_saves_preset_plus_freetext_as_a_note(): void
    {
        $order = $this->order('pending');

        $response = $this->actingAs($this->owner(), 'staff')->post(route('orders.comment', $order), [
            'preset' => 'Phone Off !!',
            'note' => 'tried twice',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('order_notes', ['order_id' => $order->id, 'note' => 'Phone Off !! — tried twice']);
    }

    public function test_ip_block_blacklists_the_customer_phone(): void
    {
        $order = $this->order('pending', ['customer_phone' => '01712349999']);

        $response = $this->actingAs($this->owner(), 'staff')->post(route('orders.block', $order));

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_blacklists', ['phone' => '01712349999', 'active' => true]);
    }

    public function test_per_order_courier_assign_creates_a_shipment(): void
    {
        $order = $this->order('confirmed');
        $provider = CourierProvider::create(['name' => 'RedX', 'slug' => 'redx-'.uniqid(), 'status' => 'active']);

        $response = $this->actingAs($this->owner(), 'staff')->post(route('orders.courier.assign', $order), [
            'courier_provider_id' => $provider->id,
            'tracking_number' => 'MEMO-9',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'courier_provider_id' => $provider->id, 'tracking_number' => 'MEMO-9']);
    }

    public function test_fraud_check_returns_courier_history_for_a_phone(): void
    {
        $provider = CourierProvider::create(['name' => 'SteadFast', 'slug' => 'sf-'.uniqid(), 'status' => 'active']);
        $delivered = $this->order('delivered', ['customer_phone' => '01712340000']);
        \App\Modules\Courier\Models\Shipment::create(['order_id' => $delivered->id, 'courier_provider_id' => $provider->id, 'status' => 'delivered', 'cod_amount' => 1000]);

        $response = $this->actingAs($this->owner(), 'staff')->getJson(route('orders.fraud-check', ['phone' => '01712340000']));

        $response->assertOk();
        $response->assertJson(['total_orders' => 1, 'total_delivered' => 1]);
        $response->assertJsonFragment(['name' => 'SteadFast', 'orders' => 1, 'delivered' => 1, 'success_rate' => 100]);
    }

    public function test_pos_and_label_print_pages_render(): void
    {
        $order = $this->order('confirmed');

        $this->actingAs($this->owner(), 'staff')->get(route('orders.pos-print', $order))->assertOk()->assertSee('ZENNA CRAFT');
        $this->actingAs($this->owner(), 'staff')->get(route('orders.label-print', $order))->assertOk()->assertSee('Deliver To');
    }

    public function test_create_order_page_and_product_search(): void
    {
        $owner = $this->owner();
        $product = $this->product();

        $this->actingAs($owner, 'staff')->get(route('orders.create'))->assertOk()->assertSee('Customer Information');

        $search = $this->actingAs($owner, 'staff')->getJson(route('orders.create.products.search', ['q' => 'Kantha']));
        $search->assertOk()->assertJsonFragment(['id' => $product->id, 'name' => 'Kantha']);
    }

    public function test_create_order_stores_a_manual_order(): void
    {
        $owner = $this->owner();
        $product = $this->product();

        $response = $this->actingAs($owner, 'staff')->post(route('orders.store'), [
            'name' => 'Walk-in Buyer',
            'phone' => '01712347777',
            'address' => 'Bashundhara',
            'district' => 'Dhaka',
            'source' => 'custom',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 1600],
            ],
            'discount' => 100,
            'shipping_charge' => 60,
            'paid' => 500,
            'paid_by' => 'Cash',
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $order = Order::where('customer_phone', '01712347777')->first();
        $this->assertNotNull($order);
        $this->assertSame('custom', $order->source);
        $this->assertSame('Dhaka', $order->district);
        // total = 2*1600 - 100 discount + 60 shipping = 3160
        $this->assertEquals(3160.0, (float) $order->total);
        $this->assertEquals(500.0, (float) $order->paid_amount);
        $this->assertSame('Cash', $order->paid_by);
        $this->assertSame(2, (int) $order->items()->sum('quantity'));
    }

    public function test_create_order_requires_at_least_one_item(): void
    {
        $response = $this->actingAs($this->owner(), 'staff')->post(route('orders.store'), [
            'name' => 'No Items', 'phone' => '01712340000',
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_staff_without_order_permission_is_forbidden(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Low Priv', 'email' => 'low-order@zennacraft.test',
            'phone' => '+8801700000091', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'staff')->firstOrFail());

        // 'staff' role has no order.view — the Orders list must be forbidden.
        $this->actingAs($staff, 'staff')->get(route('orders.index'))->assertForbidden();
    }
}
