<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCity extends Model
{
    protected $fillable = ['city_id', 'name', 'status', 'sort_order'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
