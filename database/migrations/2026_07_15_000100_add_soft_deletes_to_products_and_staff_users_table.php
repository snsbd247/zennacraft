<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products and staff users get soft deletes: deleting a product cascade
     * -deletes every product_reviews row for it (permanent), and deleting a
     * staff user nulls staff_user_id on every audit log / order status
     * history / inventory log / communication / verification attempt / ad
     * spend / expense they ever touched (erasing "who did this"). Category,
     * Coupon, and ProductVariant deliberately stay hard-delete.
     *
     * The unique constraints on slug/sku/email/phone are dropped (kept as
     * plain, non-unique indexes for lookup performance) rather than
     * replaced with a composite unique(column, deleted_at). A composite
     * unique index doesn't work for this on any of the databases this app
     * targets (MySQL, PostgreSQL, SQLite all treat NULL as distinct from
     * NULL in a unique index), so unique(slug, deleted_at) would silently
     * stop preventing two ACTIVE rows from sharing a slug — a worse bug
     * than the one being fixed. Uniqueness among non-deleted rows is
     * enforced at the application layer instead (Rule::unique(...)->where
     * (fn ($q) => $q->whereNull('deleted_at')) in the Store/Update request
     * classes), which is scoped correctly regardless of NULL semantics.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
            // The original products migration already has plain (non-
            // unique) indexes on slug and sku alongside the unique
            // constraints, so only the unique constraints need dropping —
            // adding another index here would collide with those.
            $table->dropUnique(['slug']);
            $table->dropUnique(['sku']);
        });

        Schema::table('staff_users', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropUnique(['email']);
            $table->dropUnique(['phone']);
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('slug');
            $table->unique('sku');
        });

        Schema::table('staff_users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->unique('email');
            $table->unique('phone');
        });
    }
};
