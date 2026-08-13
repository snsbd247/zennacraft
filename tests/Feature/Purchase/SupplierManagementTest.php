<?php

namespace Tests\Feature\Purchase;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Purchase\Services\SupplierService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private ?StaffUser $ownerUser = null;

    private function owner(): StaffUser
    {
        if ($this->ownerUser) {
            return $this->ownerUser;
        }
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'sup-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    private function supplierWithDue(): Supplier
    {
        $supplier = Supplier::create(['name' => 'ACME Textiles', 'phone' => '01710000000', 'status' => 'active']);
        // Two purchases: 5000 (paid 2000) and 3000 (paid 0) => due 6000 total.
        Purchase::create(['supplier_id' => $supplier->id, 'purchase_date' => '2026-08-01', 'total_amount' => 5000, 'paid_amount' => 2000]);
        Purchase::create(['supplier_id' => $supplier->id, 'purchase_date' => '2026-08-03', 'total_amount' => 3000, 'paid_amount' => 0]);

        return $supplier;
    }

    public function test_index_lists_suppliers_with_payable_totals(): void
    {
        $this->supplierWithDue();

        $this->actingAs($this->owner(), 'staff')->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('ACME Textiles')
            ->assertSee('Payable (due)')
            ->assertSee('6,000'); // portfolio payable
    }

    public function test_show_renders_stats_and_history(): void
    {
        $supplier = $this->supplierWithDue();

        $this->actingAs($this->owner(), 'staff')->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Purchase history')
            ->assertSee('Record a payment')
            ->assertSee('Payable (due)');
    }

    public function test_stats_compute_due_correctly(): void
    {
        $supplier = $this->supplierWithDue();
        $stats = app(SupplierService::class)->stats($supplier);

        $this->assertEquals(8000, $stats['purchased']);
        $this->assertEquals(2000, $stats['paid']);
        $this->assertEquals(6000, $stats['due']);
        $this->assertSame(2, $stats['count']);
    }

    public function test_record_payment_settles_purchases_fifo_and_logs(): void
    {
        $supplier = $this->supplierWithDue();

        // Pay 4000: fills the 1st purchase's 3000 due, then 1000 into the 2nd.
        $this->actingAs($this->owner(), 'staff')
            ->post(route('suppliers.payments.store', $supplier), ['amount' => 4000])
            ->assertRedirect(route('suppliers.show', $supplier));

        $purchases = $supplier->purchases()->orderBy('purchase_date')->get();
        $this->assertEquals(5000, (float) $purchases[0]->paid_amount); // fully paid
        $this->assertEquals(1000, (float) $purchases[1]->paid_amount); // partially
        $this->assertEquals(2000, app(SupplierService::class)->stats($supplier)['due']);
        $this->assertDatabaseHas('supplier_payments', ['supplier_id' => $supplier->id, 'amount' => 4000]);
    }

    public function test_payment_is_capped_at_total_due(): void
    {
        $supplier = $this->supplierWithDue(); // due 6000

        $this->actingAs($this->owner(), 'staff')->post(route('suppliers.payments.store', $supplier), ['amount' => 999999])->assertRedirect();

        $this->assertEquals(0, app(SupplierService::class)->stats($supplier)['due']);
        // Only the real due was logged, not the inflated input.
        $this->assertEquals(6000, (float) SupplierPayment::where('supplier_id', $supplier->id)->sum('amount'));
    }

    public function test_update_supplier_details(): void
    {
        $supplier = Supplier::create(['name' => 'Old', 'status' => 'active']);

        $this->actingAs($this->owner(), 'staff')
            ->put(route('suppliers.update', $supplier), ['name' => 'New Name', 'status' => 'inactive'])
            ->assertRedirect(route('suppliers.show', $supplier));

        $supplier->refresh();
        $this->assertSame('New Name', $supplier->name);
        $this->assertSame('inactive', $supplier->status);
    }

    public function test_cannot_delete_supplier_with_purchases(): void
    {
        $supplier = $this->supplierWithDue();

        $this->actingAs($this->owner(), 'staff')->delete(route('suppliers.destroy', $supplier))->assertSessionHasErrors('supplier');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
