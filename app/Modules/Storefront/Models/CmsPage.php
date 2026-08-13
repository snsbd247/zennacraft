<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CmsPage extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushStorefrontCache());
        static::deleted(fn (): null => static::flushStorefrontCache());
    }

    protected static function flushStorefrontCache(): null
    {
        try {
            app(CacheService::class)->invalidateStorefrontContent();
        } catch (Throwable) {
            // Storefront cache invalidation must not interrupt CMS writes.
        }

        return null;
    }
}
