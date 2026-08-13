<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rich landing sections: gallery, video, feature list, contact buttons, reviews. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->json('gallery')->nullable()->after('hero_image_id');
            $table->string('video_url')->nullable()->after('gallery');
            $table->text('features')->nullable()->after('content');
            $table->string('contact_phone')->nullable()->after('cta_url');
            $table->string('whatsapp_number')->nullable()->after('contact_phone');
            $table->boolean('show_reviews')->default(false)->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['gallery', 'video_url', 'features', 'contact_phone', 'whatsapp_number', 'show_reviews']);
        });
    }
};
