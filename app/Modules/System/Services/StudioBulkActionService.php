<?php

namespace App\Modules\System\Services;

use App\Modules\AdminAuth\Models\StaffUser;

class StudioBulkActionService
{
    public function resources(StaffUser $staffUser): array
    {
        return collect(['orders', 'reviews', 'recoveries', 'verification', 'campaigns'])
            ->mapWithKeys(fn (string $resource): array => [$resource => $this->availableActions($staffUser, $resource)])
            ->filter(fn (array $actions): bool => $actions !== [])
            ->all();
    }

    public function availableActions(StaffUser $staffUser, string $resource): array
    {
        return collect($this->definitions()[$resource] ?? [])
            ->filter(fn (array $action): bool => $staffUser->canAccess($action['permission']))
            ->values()
            ->all();
    }

    public function selectionSchema(StaffUser $staffUser, string $resource): array
    {
        return [
            'resource' => $resource,
            'max_selection' => 100,
            'requires_confirmation' => true,
            'actions' => $this->availableActions($staffUser, $resource),
        ];
    }

    protected function definitions(): array
    {
        return [
            'orders' => [
                ['key' => 'mark_for_review', 'label' => 'Mark for review', 'permission' => 'order.update', 'destructive' => false],
                ['key' => 'export_selection', 'label' => 'Export selection', 'permission' => 'report.export', 'destructive' => false],
            ],
            'reviews' => [
                ['key' => 'approve_reviews', 'label' => 'Approve reviews', 'permission' => 'reviews.manage', 'destructive' => false],
                ['key' => 'reject_reviews', 'label' => 'Reject reviews', 'permission' => 'reviews.manage', 'destructive' => true],
            ],
            'recoveries' => [
                ['key' => 'queue_recovery_message', 'label' => 'Queue recovery message', 'permission' => 'recovery.update', 'destructive' => false],
            ],
            'verification' => [
                ['key' => 'assign_callback', 'label' => 'Assign callback', 'permission' => 'verification.update', 'destructive' => false],
            ],
            'campaigns' => [
                ['key' => 'queue_campaigns', 'label' => 'Queue campaigns', 'permission' => 'marketing.campaign.queue', 'destructive' => false],
            ],
        ];
    }
}
