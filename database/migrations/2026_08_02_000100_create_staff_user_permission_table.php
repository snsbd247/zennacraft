<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-admin permissions. The panel is role-based, but the Admin > Permissions
 * screen assigns permissions to a single staff user (matching the reference
 * "Assign Permissions" grid). StaffUser::hasPermission() checks both a user's
 * role permissions and these direct ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_user_permission', function (Blueprint $table) {
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['staff_user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_user_permission');
    }
};
