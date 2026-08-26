<?php

namespace App\Modules\License\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton — always exactly one row (id = 1). Use LicenseState::current()
 * rather than querying directly.
 */
class LicenseState extends Model
{
    protected $fillable = [
        'license_key',
        'status',
        'expires_at',
        'last_checked_at',
        'last_check_ok',
        'message',
        'signature',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'last_check_ok' => 'boolean',
    ];

    public static function current(): self
    {
        if ($state = static::query()->orderBy('id')->first()) {
            return $state;
        }

        // Direct property assignment (not mass-assignment) so this doesn't
        // need 'id' in $fillable — keeping it out of $fillable means no
        // request input can ever target this column.
        $state = new static();
        $state->id = 1;
        $state->save();

        return $state;
    }
}
