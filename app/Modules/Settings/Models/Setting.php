<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'setting_group',
        'setting_key',
        'value',
        'data_type',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function isPublic(): bool
    {
        return $this->is_public;
    }

    public function valueAsType(): mixed
    {
        return match ($this->data_type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => intval($this->value),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
