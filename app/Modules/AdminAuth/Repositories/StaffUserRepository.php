<?php

namespace App\Modules\AdminAuth\Repositories;

use App\Modules\AdminAuth\Models\StaffUser;

class StaffUserRepository
{
    public function all(): array
    {
        return StaffUser::all()->toArray();
    }

    public function find(int $id): ?StaffUser
    {
        return StaffUser::find($id);
    }

    public function create(array $data): StaffUser
    {
        return StaffUser::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $staffUser = $this->find($id);

        return $staffUser ? $staffUser->update($data) : false;
    }

    public function delete(int $id): bool
    {
        $staffUser = $this->find($id);

        return $staffUser ? $staffUser->delete() : false;
    }
}
