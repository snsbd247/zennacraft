<?php

namespace App\Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerShadowAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'uuid',
        'status',
        'last_activity_at',
        'notes',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
