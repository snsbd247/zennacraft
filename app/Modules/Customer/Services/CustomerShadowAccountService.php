<?php

namespace App\Modules\Customer\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerShadowAccount;
use Illuminate\Support\Str;
use Throwable;

class CustomerShadowAccountService
{
    public function __construct(private AuditService $auditService) {}

    public function createForCustomer(Customer $customer): CustomerShadowAccount
    {
        $existing = $this->findByCustomer($customer);

        if ($existing) {
            return $existing;
        }

        $account = CustomerShadowAccount::create([
            'customer_id' => $customer->id,
            'uuid' => $this->generateUuid(),
            'status' => 'active',
        ]);

        $this->logShadowCreate($account);

        return $account;
    }

    public function findByCustomer(Customer $customer): ?CustomerShadowAccount
    {
        return CustomerShadowAccount::where('customer_id', $customer->id)->first();
    }

    public function touchActivity(Customer $customer): CustomerShadowAccount
    {
        $account = $this->createForCustomer($customer);
        $account->forceFill(['last_activity_at' => now()])->save();

        $account = $account->refresh();
        $this->logShadowActivity($account);

        return $account;
    }

    public function generateUuid(): string
    {
        do {
            $uuid = (string) Str::uuid();
        } while (CustomerShadowAccount::where('uuid', $uuid)->exists());

        return $uuid;
    }

    protected function logShadowCreate(CustomerShadowAccount $account): void
    {
        try {
            $this->auditService->log(
                'customer.shadow',
                'create',
                'customer',
                'Customer shadow account created',
                [
                    'customer_id' => $account->customer_id,
                    'shadow_account_id' => $account->id,
                    'uuid' => $account->uuid,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Customer shadow account create audit logging failed', [
                'customer_id' => $account->customer_id,
                'shadow_account_id' => $account->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function logShadowActivity(CustomerShadowAccount $account): void
    {
        try {
            $this->auditService->log(
                'customer.shadow',
                'activity',
                'customer',
                'Customer shadow account activity tracked',
                [
                    'customer_id' => $account->customer_id,
                    'shadow_account_id' => $account->id,
                    'uuid' => $account->uuid,
                    'last_activity_at' => $account->last_activity_at?->toDateTimeString(),
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Customer shadow account activity audit logging failed', [
                'customer_id' => $account->customer_id,
                'shadow_account_id' => $account->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
