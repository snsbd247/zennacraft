<?php

namespace App\Modules\Courier\Models;

use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Tracking\Models\ShipmentTrackingEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'courier_provider_id',
        'tracking_number',
        'consignment_id',
        'status',
        'delivery_charge',
        'cod_amount',
        'courier_cost',
        'assigned_at',
        'shipped_at',
        'delivered_at',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'delivery_charge' => 'decimal:2',
        'cod_amount' => 'decimal:2',
        'courier_cost' => 'decimal:2',
        'assigned_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushAnalyticsCache());
        static::deleted(fn (): null => static::flushAnalyticsCache());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courierProvider(): BelongsTo
    {
        return $this->belongsTo(CourierProvider::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderBy('event_time')->orderBy('id');
    }

    protected static function flushAnalyticsCache(): null
    {
        try {
            app(CacheService::class)->invalidateAnalyticsDashboard();
        } catch (Throwable) {
            // Courier analytics cache invalidation must never interrupt shipment writes.
        }

        return null;
    }
}
