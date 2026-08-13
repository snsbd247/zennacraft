<?php

namespace App\Modules\Courier\Models;

use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierMetric extends Model
{
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'courier_provider_id',
        'total_shipments',
        'assigned_shipments',
        'shipped_shipments',
        'delivered_shipments',
        'returned_shipments',
        'cancelled_shipments',
        'failed_shipments',
        'success_rate',
        'return_rate',
        'failure_rate',
        'avg_delivery_days',
        'total_cod_amount',
        'total_delivery_charge',
        'total_courier_cost',
        'last_calculated_at',
    ];

    protected $casts = [
        'total_shipments' => 'integer',
        'assigned_shipments' => 'integer',
        'shipped_shipments' => 'integer',
        'delivered_shipments' => 'integer',
        'returned_shipments' => 'integer',
        'cancelled_shipments' => 'integer',
        'failed_shipments' => 'integer',
        'success_rate' => 'decimal:2',
        'return_rate' => 'decimal:2',
        'failure_rate' => 'decimal:2',
        'avg_delivery_days' => 'decimal:2',
        'total_cod_amount' => 'decimal:2',
        'total_delivery_charge' => 'decimal:2',
        'total_courier_cost' => 'decimal:2',
        'last_calculated_at' => 'datetime',
    ];

    public function courierProvider(): BelongsTo
    {
        return $this->belongsTo(CourierProvider::class);
    }
}
