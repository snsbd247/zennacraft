<?php

namespace App\Modules\Tracking\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'order_id',
        'created_by',
        'status',
        'title',
        'description',
        'event_time',
    ];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }
}
