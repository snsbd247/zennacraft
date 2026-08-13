<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admins are added with just name/email/password/image (the Add Admin form has
 * no phone field), so phone must be optional. A unique index permits multiple
 * NULLs, so several admins can have no phone.
 *
 * staff_users carries soft-delete-aware unique indexes (see
 * 2026_07_16_000100_restore_soft_delete_aware_unique_indexes). On MySQL a raw
 * MODIFY keeps every index/generated column intact. On SQLite/pgsql a Blueprint
 * change() rebuilds the whole table and would silently turn the raw partial
 * unique indexes into plain ones (breaking reuse-after-soft-delete), so we drop
 * those two indexes, change the column, then re-create them exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `staff_users` MODIFY `phone` VARCHAR(255) NULL');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS staff_users_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS staff_users_phone_active_unique');

        Schema::table('staff_users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });

        DB::statement('CREATE UNIQUE INDEX staff_users_email_active_unique ON staff_users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX staff_users_phone_active_unique ON staff_users (phone) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `staff_users` MODIFY `phone` VARCHAR(255) NOT NULL');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS staff_users_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS staff_users_phone_active_unique');

        Schema::table('staff_users', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });

        DB::statement('CREATE UNIQUE INDEX staff_users_email_active_unique ON staff_users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX staff_users_phone_active_unique ON staff_users (phone) WHERE deleted_at IS NULL');
    }
};
