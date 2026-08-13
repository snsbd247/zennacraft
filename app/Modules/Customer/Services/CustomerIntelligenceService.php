<?php

namespace App\Modules\Customer\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\Order\Models\Order;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Support\Collection;
use Throwable;

class CustomerIntelligenceService
{
    public function __construct(
        private AuditService $auditService,
        private CustomerShadowAccountService $shadowAccountService,
        private PhoneService $phoneService,
        private MarketingSegmentEngineService $marketingSegmentEngine,
    ) {}

    public function syncFromOrder(Order $order): Customer
    {
        $normalizedPhone = $this->phoneService->normalize((string) $order->customer_phone);

        if ($normalizedPhone !== '' && $order->customer_phone !== $normalizedPhone) {
            $order->forceFill(['customer_phone' => $normalizedPhone])->save();
        }

        $customer = Customer::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $normalizedPhone ?: $order->customer_phone))->first();

        if (! $customer) {
            $customer = Customer::create([
                'phone' => $normalizedPhone ?: $order->customer_phone,
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'address' => $order->address,
            ]);
        }

        $customer->fill([
            'name' => $order->customer_name ?: $customer->name,
            'email' => $order->customer_email ?: $customer->email,
            'address' => $order->address ?: $customer->address,
        ])->save();

        Order::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $customer->phone))
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id]);

        if ($order->customer_id !== $customer->id) {
            $order->forceFill(['customer_id' => $customer->id])->save();
        }

        $customer = $this->recalculateCustomer($customer);
        $this->touchShadowActivity($customer);
        $this->evaluateMarketingSegments($customer, 'customer_sync:order');
        $this->logCustomerSync($customer, $order);

        return $customer;
    }

    public function recalculateCustomer(Customer $customer): Customer
    {
        $orders = Order::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $customer->phone))->get();

        $customer->fill([
            'total_orders' => $orders->count(),
            'total_spent' => $this->deliveredTotal($orders),
            'delivered_orders' => $orders->where('status', 'delivered')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'returned_orders' => $orders->where('status', 'returned')->count(),
            'first_order_at' => $orders->min('created_at'),
            'last_order_at' => $orders->max('created_at'),
        ]);

        $customer->cod_score = $this->calculateCodScore($customer);
        $customer->save();

        return $customer->refresh();
    }

    public function calculateCodScore(Customer $customer): int
    {
        $score = 100;
        $score -= ((int) $customer->returned_orders) * 15;
        $score -= ((int) $customer->cancelled_orders) * 10;

        return max(0, min(100, $score));
    }

    protected function deliveredTotal(Collection $orders): float
    {
        return (float) $orders
            ->where('status', 'delivered')
            ->sum(fn (Order $order) => (float) $order->total);
    }

    protected function logCustomerSync(Customer $customer, Order $order): void
    {
        try {
            $this->auditService->log(
                'customer',
                'sync',
                'customer',
                'Customer profile synced.',
                [
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'phone_hash' => $this->phoneService->hash($customer->phone),
                    'phone_last4' => substr($this->phoneService->normalize($customer->phone), -4) ?: null,
                    'cod_score' => $customer->cod_score,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Customer sync audit logging failed', [
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function touchShadowActivity(Customer $customer): void
    {
        try {
            $this->shadowAccountService->touchActivity($customer);
        } catch (Throwable $exception) {
            logger()->warning('Customer shadow account activity tracking failed', [
                'customer_id' => $customer->id,
                'phone_hash' => $this->phoneService->hash($customer->phone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function evaluateMarketingSegments(Customer $customer, string $trigger): void
    {
        try {
            $this->marketingSegmentEngine->evaluateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment customer sync evaluation failed', [
                'customer_id' => $customer->id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
