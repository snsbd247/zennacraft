<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * B4 dropped the plain unique(slug)/unique(sku)/unique(email)/
     * unique(phone) constraints, because a composite unique(column,
     * deleted_at) does not work on any target database — MySQL,
     * PostgreSQL, and SQLite all treat NULL as distinct from NULL inside
     * a unique index, so it would silently stop preventing two ACTIVE rows
     * from sharing a slug. B4 enforced uniqueness among non-deleted rows
     * at the validation layer instead (Rule::unique()->where(fn ($q) =>
     * $q->whereNull('deleted_at')) in the Store/Update request classes).
     *
     * That closed the "slug reuse after soft-delete throws" trap, but it
     * meant the database no longer enforces uniqueness at all — a seeder,
     * a tinker session, a direct service call, a future controller
     * someone forgets to validate, or two creates racing between the
     * validator's SELECT and the INSERT could all now write a duplicate.
     * This restores DB-level uniqueness among active rows only, kept
     * alongside (not instead of) the validation-layer rules: the
     * validation gives a clean user-facing error message, this is the
     * backstop for everything that bypasses it.
     *
     * Two different mechanisms, because the target databases support
     * different things:
     *   - SQLite / PostgreSQL: a genuine partial unique index
     *     (CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL). Deleted rows
     *     are excluded from the index entirely, so any number of them can
     *     share a slug/sku/email/phone — reuse after soft-delete still
     *     works, and it works precisely because those rows aren't indexed
     *     at all, not because of any NULL-comparison trick.
     *   - MySQL / MariaDB: partial indexes aren't supported. A stored
     *     generated column stands in for "is this row active" instead —
     *     0 for every active row (so the composite (column, guard) must
     *     be unique among all of them, i.e. active rows can't collide) and
     *     NULL once soft-deleted. MySQL unique indexes treat NULL as
     *     distinct from every other NULL, so deleted rows never collide
     *     with each other or with an active row's guard value of 0.
     *     (An earlier version of this guard used the row's own id instead
     *     of NULL for deleted rows, but MySQL rejects AUTO_INCREMENT
     *     columns inside a generated column expression — error 1901.)
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->addMysqlGuardedUnique('products', ['slug', 'sku']);
            $this->addMysqlGuardedUnique('staff_users', ['email', 'phone']);

            return;
        }

        // sqlite and pgsql both support this exact partial-index syntax.
        DB::statement('CREATE UNIQUE INDEX products_slug_active_unique ON products (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX products_sku_active_unique ON products (sku) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX staff_users_email_active_unique ON staff_users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX staff_users_phone_active_unique ON staff_users (phone) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `products` DROP INDEX `products_slug_active_unique`');
            DB::statement('ALTER TABLE `products` DROP INDEX `products_sku_active_unique`');
            DB::statement('ALTER TABLE `products` DROP COLUMN `deleted_guard`');

            DB::statement('ALTER TABLE `staff_users` DROP INDEX `staff_users_email_active_unique`');
            DB::statement('ALTER TABLE `staff_users` DROP INDEX `staff_users_phone_active_unique`');
            DB::statement('ALTER TABLE `staff_users` DROP COLUMN `deleted_guard`');

            return;
        }

        // Both sqlite and pgsql use this exact syntax, and index names are
        // database-wide unique on both (no table qualification needed).
        DB::statement('DROP INDEX IF EXISTS products_slug_active_unique');
        DB::statement('DROP INDEX IF EXISTS products_sku_active_unique');
        DB::statement('DROP INDEX IF EXISTS staff_users_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS staff_users_phone_active_unique');
    }

    protected function addMysqlGuardedUnique(string $table, array $columns): void
    {
        DB::statement("ALTER TABLE `{$table}` ADD COLUMN `deleted_guard` TINYINT UNSIGNED GENERATED ALWAYS AS (IF(deleted_at IS NULL, 0, NULL)) STORED");

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$table}_{$column}_active_unique` (`{$column}`, `deleted_guard`)");
        }
    }
};
