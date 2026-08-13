<?php

namespace App\Modules\Fraud\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBlacklist extends Model
{
    use InvalidatesAnalyticsCache;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'phone',
        'reason',
        'created_by',
        'active',
        'created_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }
}
