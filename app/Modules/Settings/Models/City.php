<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = ['name', 'status', 'sort_order'];

    public function subCities(): HasMany
    {
        return $this->hasMany(SubCity::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
