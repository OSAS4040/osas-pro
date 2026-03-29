<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'api_key_id', 'method', 'endpoint',
        'http_status', 'response_time_ms', 'ip_address', 'trace_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
