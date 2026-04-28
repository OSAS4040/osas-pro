<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $incident_key
 * @property string $incident_type
 * @property string $title
 * @property string $summary
 * @property string $why_summary
 * @property string $severity
 * @property float $confidence
 * @property string $status
 * @property string|null $owner
 * @property string $ownership_state
 * @property string $escalation_state
 * @property string $affected_scope
 * @property list<string> $affected_entities
 * @property list<int|string> $affected_companies
 * @property list<string> $source_signals
 * @property list<string> $recommended_actions
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $last_status_change_at
 * @property string|null $resolve_reason
 * @property string|null $close_reason
 * @property list<array<string, mixed>>|null $operator_notes
 */
final class PlatformIncident extends Model
{
    protected $table = 'platform_incidents';

    public $timestamps = true;

    protected $fillable = [
        'incident_key', 'incident_type', 'title', 'summary', 'why_summary',
        'severity', 'confidence', 'status', 'owner', 'ownership_state', 'escalation_state',
        'affected_scope', 'affected_entities', 'affected_companies', 'source_signals', 'recommended_actions',
        'first_seen_at', 'last_seen_at', 'acknowledged_at', 'resolved_at', 'closed_at', 'last_status_change_at',
        'resolve_reason', 'close_reason', 'operator_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'affected_entities' => 'array',
            'affected_companies' => 'array',
            'source_signals' => 'array',
            'recommended_actions' => 'array',
            'operator_notes' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_status_change_at' => 'datetime',
        ];
    }

    public function toContract(): PlatformIncidentContract
    {
        $utc = new DateTimeZone('UTC');

        return new PlatformIncidentContract(
            incident_key: $this->incident_key,
            incident_type: $this->incident_type,
            title: $this->title,
            summary: $this->summary,
            why_summary: $this->why_summary,
            severity: PlatformIntelligenceSeverity::from($this->severity),
            confidence: (float) $this->confidence,
            status: PlatformIncidentStatus::from($this->status),
            owner: $this->owner,
            ownership_state: PlatformIncidentOwnershipState::from($this->ownership_state),
            escalation_state: PlatformIncidentEscalationState::from($this->escalation_state),
            affected_scope: $this->affected_scope,
            affected_entities: array_values((array) $this->affected_entities),
            affected_companies: array_values((array) $this->affected_companies),
            source_signals: array_values((array) $this->source_signals),
            recommended_actions: array_values((array) $this->recommended_actions),
            first_seen_at: $this->immutable($this->first_seen_at, $utc),
            last_seen_at: $this->immutable($this->last_seen_at, $utc),
            acknowledged_at: $this->immutableNullable($this->acknowledged_at, $utc),
            resolved_at: $this->immutableNullable($this->resolved_at, $utc),
            closed_at: $this->immutableNullable($this->closed_at, $utc),
            last_status_change_at: $this->immutableNullable($this->last_status_change_at, $utc),
            resolve_reason: $this->resolve_reason,
            close_reason: $this->close_reason,
        );
    }

    private function immutable(\Illuminate\Support\Carbon $c, DateTimeZone $utc): DateTimeImmutable
    {
        return DateTimeImmutable::createFromMutable($c->clone()->timezone($utc));
    }

    private function immutableNullable(?\Illuminate\Support\Carbon $c, DateTimeZone $utc): ?DateTimeImmutable
    {
        if ($c === null) {
            return null;
        }

        return DateTimeImmutable::createFromMutable($c->clone()->timezone($utc));
    }
}
