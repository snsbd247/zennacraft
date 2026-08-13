<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Product\Models\Product;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function logInitialStock(Product $product, int $stock): InventoryLog
    {
        $this->validateStock($stock);

        return InventoryLog::create([
            'product_id' => $product->id,
            'type' => 'initial',
            'quantity' => $stock,
            'previous_stock' => 0,
            'new_stock' => $stock,
            'reason' => 'Initial stock',
            'staff_user_id' => $this->staffUserId(),
        ]);
    }

    public function adjustStock(Product $product, int $newStock, ?string $reason = null, ?int $previousStock = null): InventoryLog
    {
        $this->validateStock($newStock);

        $previousStock = $previousStock ?? (int) $product->stock;

        return InventoryLog::create([
            'product_id' => $product->id,
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
