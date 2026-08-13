<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class BillStatement extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
