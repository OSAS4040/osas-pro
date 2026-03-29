<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $primaryKey = 'id';

    protected $fillable = [
        'key_id', 'company_id', 'created_by_user_id', 'name',
        'secret_hash', 'permissions_scope', 'rate_limit', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['secret_hash'];

    protected $casts = [
        'permissions_scope' => 'array',
        'expires_at'        => 'datetime',
        'revoked_at'        => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at)
            && (is_null($this->expires_at) || $this->expires_at > now());
    }
}
