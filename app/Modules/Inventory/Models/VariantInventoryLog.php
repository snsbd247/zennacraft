<?php

namespace App\Modules\Inventory\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantInventoryLog extends Model
{
    protected $fillable = [
        'product_variant_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'staff_user_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'metadata' => 'array',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
