<?php

namespace App\Modules\Audit\Repositories;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Pagination\Paginator;

class AuditLogRepository
{
    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    public function latest(int $limit = 50)
    {
        return AuditLog::latest('created_at')->limit($limit)->get();
    }

    public function paginate(int $perPage = 25)
    {
        return AuditLog::with('staffUser')->latest('created_at')->paginate($perPage);
    }

    public function filterByModule(string $module, int $perPage = 25)
    {
        return AuditLog::where('module', $module)
            ->with('staffUser')
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function filterByStaff(int $staffUserId, int $perPage = 25)
    {
        return AuditLog::where('staff_user_id', $staffUserId)
            ->with('staffUser')
            ->latest('created_at')
            ->paginate($perPage);
    }
}
