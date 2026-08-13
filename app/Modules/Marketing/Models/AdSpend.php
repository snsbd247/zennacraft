<?php

namespace App\Modules\Marketing\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSpend extends Model
{
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'spend_date',
        'platform',
        'amount',
        'notes',
        'staff_user_id',
    ];

    protected $casts = [
        'spend_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
