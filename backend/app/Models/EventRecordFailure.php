<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRecordFailure extends Model
{
    public $timestamps = false;

    protected $table = 'event_record_failures';

    protected $fillable = [
        'event_name', 'aggregate_type', 'aggregate_id', 'company_id',
        'trace_id', 'error_message', 'payload_json', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at'   => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
