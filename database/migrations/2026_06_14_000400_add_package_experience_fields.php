<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('description');
            $table->longText('craft_story')->nullable()->after('video_url');
            $table->text('materials')->nullable()->after('craft_story');
            $table->text('dimensions')->nullable()->after('materials');
            $table->text('care_guide')->nullable()->after('dimensions');
            $table->json('faq_json')->nullable()->after('care_guide');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('badge')->nullable()->after('name');
            $table->text('short_description')->nullable()->after('badge');
            $table->string('package_type')->nullable()->after('short_description');
            $table->decimal('weight', 10, 2)->nullable()->after('stock');
            $table->string('dimensions')->nullable()->after('weight');
            $table->unsignedInteger('sort_order')->default(0)->after('dimensions');
            $table->boolean('is_featured')->default(false)->after('sort_order');
            $table->boolean('show_on_storefront')->default(true)->after('is_featured');

            $table->index(['product_id', 'status', 'show_on_storefront'], 'variants_storefront_visibility_index');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('variants_storefront_visibility_index');
            $table->dropIndex(['sort_order']);
            $table->dropColumn([
                'badge',
                'short_description',
                'package_type',
                'weight',
                'dimensions',
                'sort_order',
                'is_featured',
                'show_on_storefront',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'video_url',
                'craft_story',
                'materials',
                'dimensions',
                'care_guide',
                'faq_json',
            ]);
        });
    }
};
