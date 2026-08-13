<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\VariantInventoryLog;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class VariantInventoryService
{
    public function logInitialStock(ProductVariant $variant, int $stock): VariantInventoryLog
    {
        $this->validateStock($stock);

        return VariantInventoryLog::create([
            'product_variant_id' => $variant->id,
            'type' => 'initial',
            'quantity' => $stock,
            'previous_stock' => 0,
            'new_stock' => $stock,
            'reason' => 'Initial variant stock',
            'staff_user_id' => $this->staffUserId(),
        ]);
    }

    public function adjustStock(
        ProductVariant $variant,
        int $newStock,
        ?string $reason = null,
        ?int $previousStock = null
    ): VariantInventoryLog {
        $this->validateStock($newStock);

        $previousStock = $previousStock ?? (int) $variant->stock;

        return VariantInventoryLog::create([
            'product_variant_id' => $variant->id,
            'type' => 'adjustment',
            'quantity' => $newStock - $previousStock,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'reason' => $reason,
            'staff_user_id' => $this->staffUserId(),
        ]);
    }

    protected function validateStock(int $stock): void
    {
        if ($stock < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stock cannot be negative.',
            ]);
        }
    }

    protected function staffUserId(): ?int
    {
        return auth()->guard('staff')->id();
    }
}
