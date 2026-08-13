<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Default backfills every existing row to 'website' automatically —
            // no separate data migration needed.
            $table->string('source')->default('website')->after('status');
            $table->foreignId('source_landing_page_id')->nullable()->after('source')
                ->constrained('landing_pages')->nullOnDelete();
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_landing_page_id');
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
