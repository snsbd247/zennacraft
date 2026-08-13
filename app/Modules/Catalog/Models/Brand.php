<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image_id',
        'position',
        'status',
    ];

    protected $casts = [
        'position' => 'integer',
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
