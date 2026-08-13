<?php

namespace App\Modules\Fraud\Models;

use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudEvent extends Model
{
    use InvalidatesAnalyticsCache;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'order_id',
        'type',
        'severity',
        'score',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
