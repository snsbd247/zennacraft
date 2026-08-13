<?php

namespace App\Modules\RBAC\Services;

use App\Modules\AdminAuth\Models\Permission;
use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;

class RBACService
{
    public function assignRole(StaffUser $user, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    public function removeRole(StaffUser $user, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->detach($role->id);
    }

    public function grantPermission(Role $role, string $permissionSlug): void
    {
        $permission = Permission::where('slug', $permissionSlug)->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function revokePermission(Role $role, string $permissionSlug): void
    {
        $permission = Permission::where('slug', $permissionSlug)->firstOrFail();
        $role->permissions()->detach($permission->id);
    }

    public function userHasRole(StaffUser $user, string $roleSlug): bool
    {
        return $user->hasRole($roleSlug);
    }

    public function userHasPermission(StaffUser $user, string $permissionSlug): bool
    {
        return $user->hasPermission($permissionSlug);
    }
}
