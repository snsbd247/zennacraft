<?php

namespace Tests\Feature\Recovery;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Recovery\Models\CheckoutRecovery;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncompleteOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Rec Owner', 'email' => 'rec-owner@zennacraft.test', 'phone' => '+8801700000220', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_capture_saves_typed_details_before_submit(): void
    {
        $this->post('/checkout/capture', [
            'name' => 'Kamal Uddin',
            'phone' => '01711100000',
            'address' => 'House 5, Road 2, Dhaka',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('checkout_recoveries', [
            'customer_name' => 'Kamal Uddin',
            'address' => 'House 5, Road 2, Dhaka',
        ]);
    }

    public function test_empty_capture_is_ignored(): void
    {
        $this->post('/checkout/capture', ['name' => '', 'phone' => '', 'address' => ''])
            ->assertOk()->assertJson(['ok' => false]);

        $this->assertSame(0, CheckoutRecovery::count());
    }

    public function test_admin_list_shows_incomplete_orders_with_the_product(): void
    {
        $owner = $this->owner();
        $product = \App\Modules\Product\Models\Product::create(['name' => 'Winter Hoodie', 'slug' => 'wh-'.uniqid(), 'sku' => 'HOODIE-99', 'price' => 1200, 'status' => 'active', 'stock' => 5]);
        CheckoutRecovery::create(['product_id' => $product->id, 'customer_name' => 'Mizan', 'customer_phone' => '+8801912345678', 'address' => 'Mirpur, Dhaka', 'status' => 'open']);

        $this->actingAs($owner, 'staff')->get(route('recoveries.index'))
            ->assertOk()
            ->assertSee('Incomplete Orders')
            ->assertSee('Mizan')
            ->assertSee('Mirpur, Dhaka')
            ->assertSee('Winter Hoodie')
            ->assertSee('HOODIE-99'); // the product SKU the customer wanted
    }

    public function test_detail_page_shows_the_product_they_wanted(): void
    {
        $owner = $this->owner();
        $product = \App\Modules\Product\Models\Product::create(['name' => 'Silk Panjabi', 'slug' => 'sp-'.uniqid(), 'sku' => 'PANJABI-7', 'price' => 2200, 'status' => 'active', 'stock' => 3]);
        $rec = CheckoutRecovery::create(['product_id' => $product->id, 'customer_name' => 'Nadia', 'customer_phone' => '+8801912345111', 'address' => 'Bashundhara', 'status' => 'open']);

        $this->actingAs($owner, 'staff')->get(route('recoveries.show', $rec))
            ->assertOk()
            ->assertSee('Product they wanted')
            ->assertSee('Silk Panjabi')
            ->assertSee('PANJABI-7')
            ->assertDontSee('Journey'); // removed
    }

    public function test_owner_can_mark_a_recovery_status(): void
    {
        $owner = $this->owner();
        $rec = CheckoutRecovery::create(['customer_name' => 'Sadia', 'customer_phone' => '+8801912345000', 'address' => 'Uttara', 'status' => 'open']);

        $this->actingAs($owner, 'staff')->postJson(route('recoveries.status', $rec), ['status' => 'called'])
            ->assertOk()->assertJson(['status' => 'called']);

        $this->assertSame('called', $rec->fresh()->status);
    }

    public function test_staff_without_recovery_permission_is_blocked(): void
    {
        $this->owner();
        $stranger = StaffUser::create(['name' => 'No Perm', 'email' => 'noperm-rec@zennacraft.test', 'phone' => '+8801700000221', 'password' => 'Password123!', 'status' => 'active']);

        $this->actingAs($stranger, 'staff')->get(route('recoveries.index'))->assertForbidden();
    }
}
