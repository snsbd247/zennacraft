<?php

namespace App\Modules\Purchase\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'product_code', 'product_name', 'purchase_price', 'quantity', 'subtotal'];

    protected $casts = ['purchase_price' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
