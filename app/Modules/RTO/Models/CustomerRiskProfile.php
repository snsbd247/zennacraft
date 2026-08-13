<?php

namespace App\Modules\RTO\Models;

use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRiskProfile extends Model
{
    protected $fillable = [
        'customer_id',
        'risk_score',
        'risk_level',
        'total_orders',
        'delivered_orders',
        'cancelled_orders',
        'returned_orders',
        'delivery_rate',
        'cancel_rate',
        'return_rate',
        'last_calculated_at',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'total_orders' => 'integer',
        'delivered_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'returned_orders' => 'integer',
        'delivery_rate' => 'decimal:2',
        'cancel_rate' => 'decimal:2',
        'return_rate' => 'decimal:2',
        'last_calculated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
