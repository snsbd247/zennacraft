<?php

namespace App\Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    protected $fillable = ['supplier_id', 'amount', 'paid_on', 'method', 'note', 'created_by', 'created_by_name'];

    protected $casts = ['amount' => 'decimal:2', 'paid_on' => 'date'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
