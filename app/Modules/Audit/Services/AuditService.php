<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Shared\Services\PhoneService;

class AuditService
{
    public function __construct(
        private AuditLogRepository $repository,
        private PhoneService $phoneService,
    ) {}

    public function log(
        string $eventType,
        string $eventAction,
        string $module,
        string $description,
        array $metadata = []
    ): void {
        $request = request();
        $staffUserId = auth()->guard('staff')->id();

        $this->repository->create([
            'staff_user_id' => $staffUserId,
            'event_type' => $eventType,
            'event_action' => $eventAction,
            'module' => $module,
            'description' => $this->sanitizeDescription($description),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => !empty($metadata) ? $this->sanitizeMetadata($metadata) : null,
        ]);
    }

    protected function sanitizeMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (str_contains($lowerKey, 'email') || str_contains($lowerKey, 'address')) {
                continue;
            }

            if (in_array($lowerKey, ['phone_hash', 'phone_last4'], true)) {
                $clean[$key] = $value;

                continue;
            }

            if (str_contains($lowerKey, 'phone')) {
                $normalized = $this->phoneService->normalize(is_scalar($value) ? (string) $value : '');

                if ($normalized !== '') {
                    $clean['phone_hash'] ??= $this->phoneService->hash($normalized);
                    $clean['phone_last4'] ??= substr($normalized, -4);
                }

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitizeMetadata($value) : $value;
        }

        return $clean;
    }

    protected function sanitizeDescription(string $description): string
    {
        $description = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $description) ?? $description;

        return preg_replace('/(?:\+?8801|01)[3-9]\d[\s-]?\d{3}[\s-]?\d{4}/', '[phone]', $description) ?? $description;
    }
}
