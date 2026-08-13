<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing pages can now pick one of several visual templates and carry a hero
 * image + call-to-action, so different-style pages render from the same record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->string('template', 40)->default('classic')->after('status');
            $table->foreignId('hero_image_id')->nullable()->after('hero_subtitle')->constrained('media')->nullOnDelete();
            $table->string('cta_text')->nullable()->after('content');
            $table->string('cta_url')->nullable()->after('cta_text');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hero_image_id');
            $table->dropColumn(['template', 'cta_text', 'cta_url']);
        });
    }
};
