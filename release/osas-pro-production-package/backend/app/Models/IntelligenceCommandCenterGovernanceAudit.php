<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 7A — append-only governance audit rows. Application code must not UPDATE/DELETE.
 */
class IntelligenceCommandCenterGovernanceAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'intelligence_command_center_governance_audits';

    protected $fillable = [
        'uuid', 'company_id', 'user_id', 'governance_ref', 'item_source', 'item_id',
        'item_title_snapshot', 'severity_snapshot', 'window_from', 'window_to',
        'snapshot_generated_at', 'action', 'note', 'client_context', 'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'window_from'           => 'datetime',
            'window_to'             => 'datetime',
            'snapshot_generated_at' => 'datetime',
            'client_context'        => 'array',
            'created_at'            => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
