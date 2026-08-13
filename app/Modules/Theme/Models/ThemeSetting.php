<?php

namespace App\Modules\Theme\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];
}
