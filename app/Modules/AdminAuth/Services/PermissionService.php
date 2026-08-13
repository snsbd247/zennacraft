<?php

namespace App\Modules\AdminAuth\Services;

use App\Modules\AdminAuth\Models\Permission;
use App\Modules\AdminAuth\Repositories\PermissionRepository;

class PermissionService
{
    public function __construct(protected PermissionRepository $repository)
    {
    }

    public function createPermission(array $data): Permission
    {
        return $this->repository->create($data);
    }

    public function listPermissions(): array
    {
        return $this->repository->all();
    }

    public function findBySlug(string $slug): ?Permission
    {
        return $this->repository->findBySlug($slug);
    }
}
