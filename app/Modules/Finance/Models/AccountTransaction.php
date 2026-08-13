<?php

namespace App\Modules\Finance\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Expense\Models\Expense;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransaction extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    protected $fillable = [
        'account_id',
        'type',
        'purpose',
        'account_purpose_id',
        'amount',
        'description',
        'transaction_date',
        'order_id',
        'expense_id',
        'fund_transfer_id',
        'staff_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /** Display invoice — credits get CR-{id}, debits DR-{id}. */
    public function getInvoiceAttribute(): string
    {
        return ($this->type === self::TYPE_CREDIT ? 'CR-' : 'DR-').$this->id;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function accountPurpose(): BelongsTo
    {
        return $this->belongsTo(AccountPurpose::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function fundTransfer(): BelongsTo
    {
        return $this->belongsTo(FundTransfer::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
