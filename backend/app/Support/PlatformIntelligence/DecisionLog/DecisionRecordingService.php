<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\DecisionLog;

use App\Models\PlatformDecisionLogEntry;
use App\Models\User;
use App\Support\PlatformIntelligence\Contracts\PlatformDecisionLogEntryContract;
use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use Illuminate\Support\Str;

/**
 * Records institutional decisions linked to persisted incidents — no incident lifecycle side effects.
 */
final class DecisionRecordingService
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly DecisionTraceEmitter $traceEmitter,
    ) {}

    /**
     * @param  array{
     *     decision_type: string,
     *     decision_summary: string,
     *     rationale: string,
     *     expected_outcome?: string|null,
     *     evidence_refs?: list<string>|null,
     *     linked_notes?: list<string>|null,
     *     linked_signals?: list<string>|null,
     *     follow_up_required?: bool|null,
     * }  $payload
     */
    public function record(string $incidentKey, User $actor, array $payload): PlatformDecisionLogEntryContract
    {
        if ($this->incidents->findByIncidentKey($incidentKey) === null) {
            throw new DecisionRecordingException('incident_not_found');
        }

        $type = PlatformDecisionType::from($payload['decision_type']);

        $row = PlatformDecisionLogEntry::query()->create([
            'decision_id' => (string) Str::uuid(),
            'incident_key' => $incidentKey,
            'decision_type' => $type->value,
            'decision_summary' => $payload['decision_summary'],
            'rationale' => $payload['rationale'],
            'actor_user_id' => $actor->id,
            'linked_signals' => $this->stringList($payload['linked_signals'] ?? []),
            'linked_notes' => $this->stringList($payload['linked_notes'] ?? []),
            'expected_outcome' => isset($payload['expected_outcome']) && is_string($payload['expected_outcome'])
                ? $payload['expected_outcome']
                : '',
            'evidence_refs' => $this->stringList($payload['evidence_refs'] ?? []),
            'follow_up_required' => (bool) ($payload['follow_up_required'] ?? false),
        ]);

        $this->traceEmitter->emitDecisionRecorded($actor, $incidentKey, $row->decision_id, $type);

        return $row->toContract();
    }

    /**
     * @param  list<mixed>|null  $items
     * @return list<string>
     */
    private function stringList(?array $items): array
    {
        if ($items === null) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return array_values($out);
    }
}
