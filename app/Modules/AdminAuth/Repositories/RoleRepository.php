<?php

namespace App\Modules\AdminAuth\Repositories;

use App\Modules\AdminAuth\Models\Role;

class RoleRepository
{
    public function all(): array
    {
        return Role::all()->toArray();
    }

    public function find(int $id): ?Role
    {
        return Role::find($id);
    }

    public function findBySlug(string $slug): ?Role
    {
        return Role::where('slug', $slug)->first();
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $role = $this->find($id);

        return $role ? $role->update($data) : false;
    }

    public function delete(int $id): bool
    {
        $role = $this->find($id);

        return $role ? $role->delete() : false;
    }
}
