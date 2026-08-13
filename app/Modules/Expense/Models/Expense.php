<?php

namespace App\Modules\Expense\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'expense_category_id',
        'expense_date',
        'amount',
        'description',
        'receipt_number',
        'staff_user_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
