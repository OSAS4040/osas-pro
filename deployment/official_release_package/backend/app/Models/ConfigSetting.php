<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigSetting extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_key',
        'config_key',
        'config_value',
        'value_type',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
