<?php

namespace App\Modules\Media\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'disk',
        'directory',
        'filename',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'alt_text',
        'uploaded_by',
        'watermark_applied',
        'watermark_text',
        'watermarked_at',
        'original_path',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'watermark_applied' => 'boolean',
        'watermarked_at' => 'datetime',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'uploaded_by');
    }
}
