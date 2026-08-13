<?php

namespace App\Modules\Finance\Models;

use App\Modules\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = [
        'joining_date',
        'name',
        'position',
        'email',
        'phone',
        'office_phone',
        'designation',
        'image_id',
        'cv_path',
        'salary',
        'status',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
