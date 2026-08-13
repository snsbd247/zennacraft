<?php

namespace App\Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSegment extends Model
{
    protected $fillable = [
        'customer_id',
        'segment',
        'lifetime_value',
        'total_orders',
        'delivered_orders',
        'cancelled_orders',
        'returned_orders',
        'last_order_at',
        'last_calculated_at',
    ];

    protected $casts = [
        'lifetime_value' => 'decimal:2',
        'total_orders' => 'integer',
        'delivered_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'returned_orders' => 'integer',
        'last_order_at' => 'datetime',
        'last_calculated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
