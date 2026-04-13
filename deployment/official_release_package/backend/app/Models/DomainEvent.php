<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainEvent extends Model
{
    protected $table = 'domain_events';

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'aggregate_type', 'aggregate_id',
        'event_name', 'event_version', 'payload_json', 'metadata_json',
        'trace_id', 'correlation_id', 'caused_by_user_id', 'caused_by_type',
        'source_context', 'processing_status', 'occurred_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json'   => 'array',
            'metadata_json'  => 'array',
            'occurred_at'    => 'datetime',
            'processed_at'   => 'datetime',
            'event_version'  => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function causedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by_user_id');
    }
}
