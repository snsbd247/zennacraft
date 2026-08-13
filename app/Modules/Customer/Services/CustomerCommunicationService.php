<?php

namespace App\Modules\Customer\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerCommunication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

class CustomerCommunicationService
{
    public function __construct(
        private AuditService $auditService,
        private CustomerShadowAccountService $shadowAccountService,
    ) {}

    public function createCommunication(array $data): CustomerCommunication
    {
        $data['staff_user_id'] = $data['staff_user_id'] ?? auth()->guard('staff')->id();

        $communication = CustomerCommunication::create($data);

        $this->touchShadowActivity($communication);
        $this->logCommunicationCreate($communication);

        return $communication->load(['customer', 'order', 'staffUser']);
    }

    public function customerTimeline(Customer $customer, int $limit = 20): Collection
    {
        return CustomerCommunication::with(['order', 'staffUser'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function recentCommunications(int $perPage = 20): LengthAwarePaginator
    {
        return CustomerCommunication::with(['customer', 'order', 'staffUser'])
            ->latest()
            ->paginate($perPage);
    }

    protected function logCommunicationCreate(CustomerCommunication $communication): void
    {
        try {
            $this->auditService->log(
                'communication',
                'communication.create',
                'communication',
                'Customer communication recorded',
                [
                    'communication_id' => $communication->id,
                    'customer_id' => $communication->customer_id,
                    'order_id' => $communication->order_id,
                    'type' => $communication->type,
                    'direction' => $communication->direction,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Communication audit logging failed', [
                'communication_id' => $communication->id,
                'customer_id' => $communication->customer_id,
                'order_id' => $communication->order_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function touchShadowActivity(CustomerCommunication $communication): void
    {
        try {
            $customer = $communication->customer()->first();

            if ($customer) {
                $this->shadowAccountService->touchActivity($customer);
            }
        } catch (Throwable $exception) {
            logger()->warning('Communication shadow account activity tracking failed', [
                'communication_id' => $communication->id,
                'customer_id' => $communication->customer_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
