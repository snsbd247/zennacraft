<?php

namespace App\Modules\Courier\Models;

use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourierProvider extends Model
{
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'api_enabled',
        'config',
        'notes',
    ];

    protected $casts = [
        'api_enabled' => 'boolean',
        'config' => 'array',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function metric(): HasOne
    {
        return $this->hasOne(CourierMetric::class);
    }
}
