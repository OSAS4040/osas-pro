<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'guard_name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_system) {
            $systemPerms = config('permissions.roles.' . $this->name, []);
            if (in_array('*', $systemPerms) || in_array($permission, $systemPerms)) {
                return true;
            }
        }

        return $this->permissions()->where('name', $permission)->exists();
    }
}
