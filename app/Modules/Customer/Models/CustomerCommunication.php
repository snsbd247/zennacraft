<?php

namespace App\Modules\Customer\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCommunication extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'staff_user_id',
        'type',
        'direction',
        'subject',
        'message',
        'status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
