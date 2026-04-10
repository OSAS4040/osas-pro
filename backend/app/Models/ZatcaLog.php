<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'company_id',
        'reference_type',
        'reference_id',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'zatca_uuid',
        'zatca_status',
        'error_message',
        'trace_id',
        'created_at',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'created_at'       => 'datetime',
    ];
}
