<?php

namespace App\Modules\AdminAuth\Services;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Repositories\RoleRepository;

class RoleService
{
    public function __construct(protected RoleRepository $repository)
    {
    }

    public function createRole(array $data): Role
    {
        return $this->repository->create($data);
    }

    public function listRoles(): array
    {
        return $this->repository->all();
    }

    public function findBySlug(string $slug): ?Role
    {
        return $this->repository->findBySlug($slug);
    }
}
