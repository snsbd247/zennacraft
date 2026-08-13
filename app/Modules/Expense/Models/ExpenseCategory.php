<?php

namespace App\Modules\Expense\Models;

use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'notes',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
