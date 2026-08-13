<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductDamage extends Model
{
    protected $fillable = ['damage_date', 'total_amount', 'note'];

    protected $casts = ['damage_date' => 'date', 'total_amount' => 'decimal:2'];

    public function items(): HasMany
    {
        return $this->hasMany(ProductDamageItem::class, 'damage_id');
    }
}
