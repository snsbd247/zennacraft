<?php

namespace App\Modules\Finance\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends Model
{
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'cost',
        'transfer_amount',
        'comment',
        'staff_user_id',
        'transfer_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
        'transfer_date' => 'date',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
