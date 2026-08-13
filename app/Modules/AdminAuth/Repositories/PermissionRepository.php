<?php

namespace App\Modules\AdminAuth\Repositories;

use App\Modules\AdminAuth\Models\Permission;

class PermissionRepository
{
    public function all(): array
    {
        return Permission::all()->toArray();
    }

    public function find(int $id): ?Permission
    {
        return Permission::find($id);
    }

    public function findBySlug(string $slug): ?Permission
    {
        return Permission::where('slug', $slug)->first();
    }

    public function create(array $data): Permission
    {
        return Permission::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $permission = $this->find($id);

        return $permission ? $permission->update($data) : false;
    }

    public function delete(int $id): bool
    {
        $permission = $this->find($id);

        return $permission ? $permission->delete() : false;
    }
}
