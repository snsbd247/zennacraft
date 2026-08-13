<?php

namespace App\Modules\RTO\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRiskProfile extends Model
{
    protected $fillable = [
        'order_id',
        'risk_score',
        'risk_level',
        'risk_reasons',
        'customer_risk_score',
        'courier_risk_score',
        'verification_risk_score',
        'last_calculated_at',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'risk_reasons' => 'array',
        'customer_risk_score' => 'integer',
        'courier_risk_score' => 'integer',
        'verification_risk_score' => 'integer',
        'last_calculated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
