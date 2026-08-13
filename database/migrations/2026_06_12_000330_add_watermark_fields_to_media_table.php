<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->boolean('watermark_applied')->default(false)->after('uploaded_by');
            $table->string('watermark_text')->nullable()->after('watermark_applied');
            $table->timestamp('watermarked_at')->nullable()->after('watermark_text');
            $table->string('original_path')->nullable()->after('watermarked_at');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn([
                'watermark_applied',
                'watermark_text',
                'watermarked_at',
                'original_path',
            ]);
        });
    }
};
