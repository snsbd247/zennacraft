<?php

namespace App\Modules\Combo\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Combo extends Model
{
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'name',
        'slug',
        'code',
        'image_id',
        'description',
        'price',
        'regular_price',
        'status',
        'feature_on_home',
        'sold_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'regular_price' => 'decimal:2',
        'feature_on_home' => 'boolean',
        'sold_count' => 'integer',
    ];

    public function image(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Media\Models\Media::class, 'image_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'combo_items')
            ->withPivot(['quantity', 'sort_order'])
            ->withTimestamps()
            ->orderBy('combo_items.sort_order');
    }

    /**
     * What buying every item in this combo separately would cost — the
     * "was" price the drawer's live savings box and the grid card both
     * compare the combo price against.
     */
    public function itemsTotal(): float
    {
        return (float) $this->products->sum(fn (Product $product) => (float) $product->price * (int) $product->pivot->quantity);
    }

    /**
     * The "was" price the Studio list shows in the Price column. Falls back
     * to what the member items cost separately when no regular price is set,
     * so a combo always shows a sensible strike-through figure.
     */
    public function regularPriceValue(): float
    {
        $regular = (float) $this->regular_price;

        return $regular > 0 ? $regular : $this->itemsTotal();
    }

    /** Money saved by buying the combo instead of the regular price (never negative). */
    public function discountAmount(): float
    {
        return max(0.0, $this->regularPriceValue() - (float) $this->price);
    }
}
