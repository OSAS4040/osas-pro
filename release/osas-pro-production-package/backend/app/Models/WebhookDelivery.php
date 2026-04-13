<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'webhook_endpoint_id', 'event', 'payload',
        'status', 'attempt', 'http_status', 'response_body',
        'trace_id', 'next_attempt_at',
    ];

    protected $casts = [
        'payload'        => 'array',
        'next_attempt_at' => 'datetime',
    ];

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
