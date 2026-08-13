<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDamageItem extends Model
{
    protected $fillable = ['damage_id', 'product_id', 'product_name', 'quantity', 'unit_cost', 'subtotal'];

    protected $casts = ['unit_cost' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function damage(): BelongsTo
    {
        return $this->belongsTo(ProductDamage::class, 'damage_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
