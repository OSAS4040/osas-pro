<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'created_by_user_id',
        'url', 'events', 'secret_hash', 'is_active',
    ];

    protected $hidden = ['secret_hash'];

    protected $casts = [
        'events'    => 'array',
        'is_active' => 'boolean',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
