<?php

namespace App\Modules\LandingPage\Models;

use App\Modules\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPage extends Model
{
    /**
     * Visual templates a landing page can render with. Each maps to a partial
     * under resources/views/storefront/landing-pages/templates/{key}.blade.php.
     */
    public const TEMPLATES = [
        'classic' => ['label' => 'Classic', 'desc' => 'Clean centered hero over the image, with a readable content card and one call-to-action.', 'accent' => '#1f7a3d'],
        'bold' => ['label' => 'Bold Promo', 'desc' => 'Full-bleed dark hero, oversized headline and a glowing gradient button — high impact.', 'accent' => '#0f172a'],
        'minimal' => ['label' => 'Minimal', 'desc' => 'Elegant and airy with lots of whitespace and a refined serif headline — editorial feel.', 'accent' => '#111827'],
        'sales' => ['label' => 'Sales Page', 'desc' => 'Long-form conversion page: offer badge, benefit points, sticky order button, repeated CTA.', 'accent' => '#f2a20c'],
    ];

    protected $fillable = [
        'product_id',
        'title',
        'slug',
        'status',
        'template',
        'hero_title',
        'hero_subtitle',
        'hero_image_id',
        'gallery',
        'video_url',
        'features',
        'contact_phone',
        'whatsapp_number',
        'show_reviews',
        'content',
        'cta_text',
        'cta_url',
        'suggested_products',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'suggested_products' => 'array',
        'gallery' => 'array',
        'show_reviews' => 'boolean',
    ];

    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_image_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function templateMeta(): array
    {
        return self::TEMPLATES[$this->template] ?? self::TEMPLATES['classic'];
    }
}
