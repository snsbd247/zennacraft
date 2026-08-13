<?php

namespace App\Modules\Promotion\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    /**
     * Where an offer appears. "where" is the human hint shown in Studio so the
     * admin understands exactly which spot each offer controls.
     */
    public const PLACEMENTS = [
        'cart_free_gift' => [
            'label' => 'Cart — Free Gift Bar',
            'where' => 'Slide-out cart (top progress bar)',
            'desc' => 'Shows a live "add ৳X more to unlock your free gift" progress bar at the top of the cart drawer.',
        ],
    ];

    protected $fillable = [
        'name',
        'placement',
        'threshold_amount',
        'reward_text',
        'reward_product_id',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'threshold_amount' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function rewardProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reward_product_id');
    }

    public static function placementMeta(?string $placement): array
    {
        return self::PLACEMENTS[$placement] ?? self::PLACEMENTS['cart_free_gift'];
    }

    public function getPlacementLabelAttribute(): string
    {
        return self::placementMeta($this->placement)['label'];
    }

    /** The single active offer that drives a given placement (lowest sort_order wins). */
    public static function activeFor(string $placement): ?self
    {
        return static::where('placement', $placement)->where('active', true)
            ->orderBy('sort_order')->orderByDesc('id')->first();
    }
}
