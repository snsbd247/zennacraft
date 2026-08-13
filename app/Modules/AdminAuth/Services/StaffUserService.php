<?php

namespace App\Modules\AdminAuth\Services;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\AdminAuth\Repositories\RoleRepository;
use App\Modules\AdminAuth\Repositories\PermissionRepository;
use App\Modules\AdminAuth\Repositories\StaffUserRepository;

class StaffUserService
{
    public function __construct(
        protected StaffUserRepository $repository,
        protected RoleRepository $roleRepository,
        protected PermissionRepository $permissionRepository,
    ) {
    }

    public function createUser(array $data): StaffUser
    {
        return $this->repository->create($data);
    }

    public function assignRole(StaffUser $user, string|Role $role): void
    {
        $role = $role instanceof Role ? $role : $this->roleRepository->findBySlug($role);

        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    public function removeRole(StaffUser $user, string|Role $role): void
    {
        $role = $role instanceof Role ? $role : $this->roleRepository->findBySlug($role);

        if ($role) {
            $user->roles()->detach($role->id);
        }
    }

    public function listRoles(): array
    {
        return $this->roleRepository->all();
    }

    public function listPermissions(): array
    {
        return $this->permissionRepository->all();
    }
}
