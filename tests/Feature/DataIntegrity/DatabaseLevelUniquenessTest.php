<?php

namespace Tests\Feature\DataIntegrity;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B4 dropped the plain unique() constraints on products.slug/sku and
 * staff_users.email/phone because a composite unique(column, deleted_at)
 * doesn't work on any target database, and moved enforcement to the
 * validation layer. That closed the slug-reuse-after-soft-delete trap, but
 * left the database enforcing nothing — anything that doesn't route
 * through a FormRequest (a seeder, tinker, a direct service call, a
 * future controller someone forgets to validate) could write a duplicate.
 * These tests bypass validation on purpose, using DB::table()->insert()
 * directly, to prove the database itself — not the validator — is what
 * rejects the duplicate.
 */
class DatabaseLevelUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCategory(): Category
    {
        return Category::create(['name' => 'Test Category', 'slug' => 'test-category-'.uniqid(), 'status' => 'active']);
    }

    public function test_two_active_products_cannot_share_a_slug_even_bypassing_validation(): void
    {
        $category = $this->makeCategory();

        Product::create([
            'category_id' => $category->id, 'name' => 'First', 'slug' => 'duplicate-slug',
            'sku' => 'SKU-FIRST', 'price' => 100, 'stock' => 1, 'status' => 'active',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Raw insert, not Product::create() — no FormRequest, no
        // Rule::unique() in the path at all.
        DB::table('products')->insert([
            'category_id' => $category->id, 'name' => 'Second', 'slug' => 'duplicate-slug',
            'sku' => 'SKU-SECOND', 'price' => 100, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_two_active_products_cannot_share_a_sku_even_bypassing_validation(): void
    {
        $category = $this->makeCategory();

        Product::create([
            'category_id' => $category->id, 'name' => 'First', 'slug' => 'first-slug',
            'sku' => 'DUPLICATE-SKU', 'price' => 100, 'stock' => 1, 'status' => 'active',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('products')->insert([
            'category_id' => $category->id, 'name' => 'Second', 'slug' => 'second-slug',
            'sku' => 'DUPLICATE-SKU', 'price' => 100, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_two_active_staff_users_cannot_share_an_email_even_bypassing_validation(): void
    {
        StaffUser::create([
            'name' => 'First', 'email' => 'duplicate@zennacraft.test',
            'phone' => '01799991001', 'password' => 'Password123!', 'status' => 'active',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('staff_users')->insert([
            'name' => 'Second', 'email' => 'duplicate@zennacraft.test',
            'phone' => '01799991002', 'password' => 'Password123!', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_soft_deleted_products_slug_and_sku_can_still_be_reused(): void
    {
        $category = $this->makeCategory();

        $original = Product::create([
            'category_id' => $category->id, 'name' => 'Original', 'slug' => 'reuse-slug',
            'sku' => 'REUSE-SKU', 'price' => 100, 'stock' => 1, 'status' => 'active',
        ]);
        $original->delete();

        // Direct insert, not Product::create() — proving the DATABASE
        // itself allows the reuse, not just the validator.
        $newId = DB::table('products')->insertGetId([
            'category_id' => $category->id, 'name' => 'Reused', 'slug' => 'reuse-slug',
            'sku' => 'REUSE-SKU', 'price' => 100, 'stock' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNotNull($newId);
        $this->assertNotSame($original->id, $newId);
    }

    public function test_soft_deleted_staff_users_email_can_still_be_reused(): void
    {
        $original = StaffUser::create([
            'name' => 'Original', 'email' => 'reuse@zennacraft.test',
            'phone' => '01799991003', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $original->delete();

        $newId = DB::table('staff_users')->insertGetId([
            'name' => 'Reused', 'email' => 'reuse@zennacraft.test',
            'phone' => '01799991004', 'password' => 'Password123!', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNotNull($newId);
        $this->assertNotSame($original->id, $newId);
    }
}
