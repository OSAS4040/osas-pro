<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerticalProfile extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'defaults',
        'is_active',
    ];

    protected $casts = [
        'defaults' => 'array',
        'is_active' => 'boolean',
    ];
}
