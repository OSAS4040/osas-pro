<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleIdentityScanEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vehicle_identity_token_id',
        'token_prefix',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(VehicleIdentityToken::class, 'vehicle_identity_token_id');
    }
}
