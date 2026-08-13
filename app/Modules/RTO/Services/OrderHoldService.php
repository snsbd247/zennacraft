<?php

namespace App\Modules\RTO\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Services\CustomerSegmentationService;
use App\Modules\Order\Models\Order;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderHoldService
{
    public function __construct(
        private AuditService $auditService,
        private CustomerSegmentationService $customerSegmentationService,
    ) {}

    public function placeOnHold(Order $order, string $reason): Order
    {
        if ($this->isHeld($order)) {
            return $order->refresh();
        }

        $order->forceFill([
            'risk_hold_status' => 'active',
            'risk_hold_reason' => $reason,
            'risk_hold_at' => now(),
            'risk_hold_released_at' => null,
            'risk_hold_released_by' => null,
            'risk_hold_release_note' => null,
        ])->save();

        $order = $order->refresh();
        $this->logAudit('risk.hold.place', $order, [
            'reason' => $reason,
        ]);
        $this->recalculateCustomerSegment($order, 'risk_hold:place');

        return $order;
    }

    public function releaseHold(Order $order, string $note): Order
    {
        if (blank($note)) {
            throw ValidationException::withMessages([
                'note' => 'A release note is required.',
            ]);
        }

        $order->forceFill([
            'risk_hold_status' => 'released',
            'risk_hold_released_at' => now(),
            'risk_hold_released_by' => auth()->guard('staff')->id(),
            'risk_hold_release_note' => $note,
        ])->save();

        $order = $order->refresh();
        $this->logAudit('risk.hold.release', $order, [
            'note' => $note,
        ]);
        $this->recalculateCustomerSegment($order, 'risk_hold:release');

        return $order;
    }

    public function isHeld(Order $order): bool
    {
        return $order->risk_hold_status === 'active';
    }

    protected function logAudit(string $action, Order $order, array $metadata = []): void
    {
        try {
            $this->auditService->log(
                'risk.hold',
                $action,
                'risk',
                'Order risk hold updated: '.$order->order_number,
                array_merge([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'risk_hold_status' => $order->risk_hold_status,
                ], $metadata)
            );
        } catch (Throwable $exception) {
            logger()->warning('Order risk hold audit logging failed', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function recalculateCustomerSegment(Order $order, string $trigger): void
    {
        try {
            $order->loadMissing('customer');

            if (! $order->customer) {
                return;
            }

            $this->customerSegmentationService->recalculateCustomer($order->customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Customer segment recalculation after risk hold failed', [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
