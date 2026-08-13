<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supplier (accounts-payable) logic. A supplier's outstanding due is always
 * Σ(purchase.total_amount − purchase.paid_amount); a payment recorded here is
 * both logged (supplier_payments, for history) AND applied to the supplier's
 * outstanding purchases oldest-first, so purchase.paid_amount stays the single
 * source of truth for what is still owed.
 */
class SupplierService
{
    /**
     * Record a payment to a supplier and settle it against open purchases
     * (FIFO). The recorded amount is clamped to the current total due so a
     * payment can never drive a purchase's due negative.
     */
    public function recordPayment(Supplier $supplier, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data) {
            $totalDue = $this->stats($supplier)['due'];
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter a payment amount greater than zero.']);
            }

            $amount = min($amount, $totalDue);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'This supplier has no outstanding due to pay.']);
            }

            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'amount' => $amount,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'method' => $data['method'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => auth()->guard('staff')->id(),
                'created_by_name' => auth()->guard('staff')->user()?->name,
            ]);

            $remaining = $amount;
            $openPurchases = $supplier->purchases()->orderBy('purchase_date')->orderBy('id')->get();

            foreach ($openPurchases as $purchase) {
                if ($remaining <= 0) {
                    break;
                }

                $due = max(0, (float) $purchase->total_amount - (float) $purchase->paid_amount);
                if ($due <= 0) {
                    continue;
                }

                $apply = min($due, $remaining);
                $purchase->paid_amount = (float) $purchase->paid_amount + $apply;
                $purchase->save();
                $remaining = round($remaining - $apply, 2);
            }

            return $payment;
        });
    }

    /**
     * @return array{purchased: float, paid: float, due: float, count: int}
     */
    public function stats(Supplier $supplier): array
    {
        $purchased = (float) $supplier->purchases()->sum('total_amount');
        $paid = (float) $supplier->purchases()->sum('paid_amount');

        return [
            'purchased' => $purchased,
            'paid' => $paid,
            'due' => max(0, round($purchased - $paid, 2)),
            'count' => (int) $supplier->purchases()->count(),
        ];
    }

    /**
     * Portfolio totals across every supplier for the list header.
     *
     * @return array{suppliers: int, purchased: float, paid: float, payable: float}
     */
    public function overview(): array
    {
        $purchased = (float) DB::table('purchases')->sum('total_amount');
        $paid = (float) DB::table('purchases')->sum('paid_amount');

        return [
            'suppliers' => (int) Supplier::count(),
            'purchased' => $purchased,
            'paid' => $paid,
            'payable' => max(0, round($purchased - $paid, 2)),
        ];
    }
}
