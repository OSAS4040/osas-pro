<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasRoles
{
    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles')
            ->withTimestamps();
    }

    public function directPermissions(): MorphToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions')
            ->withTimestamps();
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (in_array($this->role, $roles)) {
            return true;
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->role instanceof \BackedEnum ? $this->role->value : (string) $this->role;
        $configPerms = config('permissions.roles.' . $role, []);
        if (in_array('*', $configPerms) || in_array($permission, $configPerms)) {
            return true;
        }

        if ($this->directPermissions()->where('name', $permission)->exists()) {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)
                ->where(fn($q) => $q->whereNull('company_id')
                    ->orWhere('company_id', $this->company_id))
                ->firstOrFail();
        }

        if (! $this->roles()->where('roles.id', $role->id)->exists()) {
            $this->roles()->attach($role->id);
        }
    }

    public function syncRoles(array $roleNames): void
    {
        $roleIds = Role::whereIn('name', $roleNames)
            ->where(fn($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $this->company_id))
            ->pluck('id');

        $this->roles()->sync($roleIds);
    }

    public function removeRole(string $roleName): void
    {
        $this->roles()->where('name', $roleName)->detach();
    }
}
