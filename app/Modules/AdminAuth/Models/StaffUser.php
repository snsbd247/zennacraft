<?php

namespace App\Modules\AdminAuth\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class StaffUser extends Authenticatable
{
    use HasApiTokens;
    use SoftDeletes;

    protected $table = 'staff_users';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'avatar',
        'last_login_at',
        'email_verified_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'staff_user_role', 'staff_user_id', 'role_id');
    }

    /** Permissions granted directly to this admin (Admin > Permissions grid). */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'staff_user_permission', 'staff_user_id', 'permission_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('slug', $permission))
            ->exists()
            || $this->permissions()->where('slug', $permission)->exists();
    }

    public function canAccess(string $permission): bool
    {
        return $this->hasPermission($permission);
    }
}
