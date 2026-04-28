<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlatformIntelligence\Contracts\PlatformDecisionLogEntryContract;
use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $decision_id
 * @property string $incident_key
 * @property string $decision_type
 * @property string $decision_summary
 * @property string $rationale
 * @property int $actor_user_id
 * @property list<string> $linked_signals
 * @property list<string> $linked_notes
 * @property string $expected_outcome
 * @property list<string> $evidence_refs
 * @property bool $follow_up_required
 * @property \Illuminate\Support\Carbon $created_at
 */
final class PlatformDecisionLogEntry extends Model
{
    protected $table = 'platform_decision_log_entries';

    public const UPDATED_AT = null;

    protected $fillable = [
        'decision_id',
        'incident_key',
        'decision_type',
        'decision_summary',
        'rationale',
        'actor_user_id',
        'linked_signals',
        'linked_notes',
        'expected_outcome',
        'evidence_refs',
        'follow_up_required',
    ];

    protected function casts(): array
    {
        return [
            'linked_signals' => 'array',
            'linked_notes' => 'array',
            'evidence_refs' => 'array',
            'follow_up_required' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function toContract(): PlatformDecisionLogEntryContract
    {
        $type = PlatformDecisionType::from($this->decision_type);
        $created = $this->created_at instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($this->created_at)->setTimezone(new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new PlatformDecisionLogEntryContract(
            decision_id: $this->decision_id,
            incident_key: $this->incident_key,
            decision_type: $type,
            decision_summary: $this->decision_summary,
            rationale: $this->rationale,
            actor: 'user:'.$this->actor_user_id,
            created_at: $created,
            linked_signals: $this->normalizeStringList($this->linked_signals ?? []),
            linked_notes: $this->normalizeStringList($this->linked_notes ?? []),
            expected_outcome: $this->expected_outcome ?? '',
            evidence_refs: $this->normalizeStringList($this->evidence_refs ?? []),
            follow_up_required: (bool) $this->follow_up_required,
        );
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return array_values($out);
    }
}
