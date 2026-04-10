<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgUnit extends Model
{
    use HasTenantScope;

    public const TYPE_SECTOR = 'sector';

    public const TYPE_DEPARTMENT = 'department';

    public const TYPE_DIVISION = 'division';

    /** @var list<string> */
    public const TYPES = [self::TYPE_SECTOR, self::TYPE_DEPARTMENT, self::TYPE_DIVISION];

    protected $fillable = [
        'uuid', 'company_id', 'parent_id', 'type', 'name', 'name_ar', 'code', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'org_unit_id');
    }
}
