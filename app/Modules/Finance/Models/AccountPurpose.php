<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class AccountPurpose extends Model
{
    public const TYPES = [
        'fixed_expense' => 'Fixed expense',
        'not_expense' => 'Not expense',
    ];

    protected $fillable = [
        'name',
        'type',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? 'Not expense';
    }
}
