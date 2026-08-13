<?php

namespace Tests\Feature\DataIntegrity;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Review\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category-'.uniqid(), 'status' => 'active']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'reusable-slug',
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price' => 1000.00,
            'stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    public function test_soft_deleted_product_retains_its_reviews(): void
    {
        $product = $this->makeProduct();

        $review = ProductReview::create([
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great product',
            'body' => 'Really happy with this purchase.',
            'status' => 'approved',
            'reviewer_name' => 'Happy Customer',
        ]);

        $product->delete();

        $this->assertSoftDeleted($product);
        // The review must still exist — a hard delete would have cascade-
        // deleted it via product_reviews' cascadeOnDelete() FK.
        $this->assertDatabaseHas('product_reviews', ['id' => $review->id, 'product_id' => $product->id]);
        $this->assertNotNull(ProductReview::find($review->id));
    }

    public function test_soft_deleted_product_slug_can_be_reused(): void
    {
        $original = $this->makeProduct(['slug' => 'reusable-slug']);
        $original->delete();

        $this->assertSoftDeleted($original);

        // A brand new active product taking the same slug must succeed —
        // the unique DB constraint was dropped specifically so this
        // wouldn't throw a duplicate-key error.
        $reused = $this->makeProduct(['slug' => 'reusable-slug', 'sku' => 'SKU-REUSED-'.uniqid()]);

        $this->assertNotNull($reused->id);
        $this->assertSame('reusable-slug', $reused->slug);
        $this->assertTrue(Product::where('slug', 'reusable-slug')->whereNull('deleted_at')->exists());
    }

    public function test_default_queries_do_not_return_soft_deleted_products(): void
    {
        $product = $this->makeProduct();
        $product->delete();

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    public function test_soft_deleted_staff_user_retains_audit_log_attribution(): void
    {
        $staff = StaffUser::create([
            'name' => 'Audit Test Staff',
            'email' => 'audit-staff@zennacraft.test',
            'phone' => '01799990001',
            'password' => 'Password123!',
            'status' => 'active',
        ]);

        $log = AuditLog::create([
            'staff_user_id' => $staff->id,
            'event_type' => 'test',
            'event_action' => 'test.action',
            'module' => 'test',
            'description' => 'Test audit entry attributed to this staff member.',
        ]);

        $staff->delete();

        $this->assertSoftDeleted($staff);
        // staff_user_id must still point at this staff member — a hard
        // delete would have nulled it via audit_logs' nullOnDelete() FK,
        // erasing "who did this" from the trail.
        $log->refresh();
        $this->assertSame($staff->id, $log->staff_user_id);
    }

    public function test_soft_deleted_staff_users_email_and_phone_can_be_reused(): void
    {
        $original = StaffUser::create([
            'name' => 'Reusable Staff', 'email' => 'reusable@zennacraft.test',
            'phone' => '01799990002', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $original->delete();

        $reused = StaffUser::create([
            'name' => 'New Staff', 'email' => 'reusable@zennacraft.test',
            'phone' => '01799990002', 'password' => 'Password123!', 'status' => 'active',
        ]);

        $this->assertNotNull($reused->id);
        $this->assertNotSame($original->id, $reused->id);
    }

    public function test_soft_deleted_staff_user_cannot_log_in(): void
    {
        $staff = StaffUser::create([
            'name' => 'Deleted Staff', 'email' => 'deleted-staff@zennacraft.test',
            'phone' => '01799990003', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->delete();

        $response = $this->post(route('staff.login.submit'), [
            'email' => 'deleted-staff@zennacraft.test',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest('staff');
    }
}
