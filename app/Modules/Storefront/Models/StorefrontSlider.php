<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Media\Models\Media;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class StorefrontSlider extends Model
{
    /**
     * The homepage spots a slider can drive. One image per slider, shown
     * responsively (auto-covers whatever device views it). "multiple" marks
     * spots that rotate several slides (the hero) vs. show a single banner.
     */
    public const PLACEMENTS = [
        'home_hero' => ['label' => 'Hero Slider', 'desc' => 'Big rotating banner at the top-left of the homepage.', 'multiple' => true, 'group' => 'slider', 'size' => '720 × 300 px (landscape · JPG/PNG)'],
        'home_side' => ['label' => 'Side Banner', 'desc' => 'Card beside the hero (top-right of the homepage).', 'multiple' => false, 'group' => 'slider', 'size' => '470 × 300 px (portrait-ish)'],
        'home_promo' => ['label' => 'Promo Banner', 'desc' => 'Wide banner in the middle of the homepage.', 'multiple' => false, 'group' => 'slider', 'size' => '1200 × 250 px (wide strip)'],
        // Campaign/Offer > Offer Banner — promotional banners rendered in
        // storefront content slots (managed from one unified Studio page).
        'before_footer' => ['label' => 'before-footer-section', 'desc' => 'Wide promo banner just above the storefront footer.', 'multiple' => true, 'group' => 'offer', 'size' => '1200 × 300 px (wide strip)'],
        'after_top_selling_1' => ['label' => 'after-top-selling-first-banner', 'desc' => 'Banner shown right after the Top Selling row.', 'multiple' => true, 'group' => 'offer', 'size' => '1200 × 300 px (wide strip)'],
        'after_top_selling_2' => ['label' => 'after-top-selling-second-banner', 'desc' => 'Second banner after the Top Selling row.', 'multiple' => true, 'group' => 'offer', 'size' => '1200 × 300 px (wide strip)'],
    ];

    /** Placement keys that belong to the Campaign/Offer "Offer Banner" page. */
    public static function offerPlacements(): array
    {
        return array_keys(array_filter(self::PLACEMENTS, fn ($meta) => ($meta['group'] ?? 'slider') === 'offer'));
    }

    protected $fillable = [
        'placement',
        'title',
        'subtitle',
        'description',
        'button_text',
        'button_url',
        'desktop_image_id',
        'mobile_image_id',
        'badge_text',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushStorefrontCache());
        static::deleted(fn (): null => static::flushStorefrontCache());
    }

    public function desktopImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'desktop_image_id');
    }

    public function mobileImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'mobile_image_id');
    }

    /** The single, device-responsive slider image (stored on desktop_image_id). */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'desktop_image_id');
    }

    public static function placementMeta(?string $placement): array
    {
        return self::PLACEMENTS[$placement] ?? self::PLACEMENTS['home_hero'];
    }

    public function getPlacementLabelAttribute(): string
    {
        return self::placementMeta($this->placement)['label'];
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
