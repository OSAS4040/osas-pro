<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Correlation;

use App\Models\PlatformDecisionLogEntry;
use App\Models\PlatformGuidedWorkflowIdempotency;
use App\Models\PlatformIncident;
use App\Models\User;
use App\Services\Platform\PlatformPermissionService;
use App\Support\PlatformIntelligence\CandidateEngine\PlatformIncidentCandidateEngine;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use App\Support\PlatformIntelligence\DecisionSerialization\PlatformDecisionLogEntryContractSerializer;
use App\Support\PlatformIntelligence\Enums\CorrelationRelationType;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\PlatformOperatorPermissionMatrix;
use App\Support\PlatformIntelligence\SignalEngine\PlatformSignalEngine;
use App\Support\PlatformIntelligence\SignalEngine\Serialization\PlatformSignalContractSerializer;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;

/**
 * Assembles read-only correlated context for one incident — no new mutations.
 */
final class IncidentCorrelationAssembler
{
    public function __construct(
        private readonly PlatformPermissionService $permissions,
        private readonly IncidentRepository $incidents,
        private readonly PlatformSignalEngine $signalEngine,
        private readonly PlatformIncidentCandidateEngine $candidateEngine,
        private readonly CorrelationTraceEmitter $traceEmitter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $incidentKey, User $actor): array
    {
        if (! $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_READ)) {
            return ['error' => 'forbidden', 'message' => 'missing_incidents_read'];
        }

        $row = $this->incidents->findByIncidentKey($incidentKey);
        if ($row === null) {
            return ['error' => 'not_found', 'message' => 'incident_not_found'];
        }

        $incident = $row->toContract();

        $signalsRead = $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_SIGNALS_READ);
        $candidatesRead = $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_CANDIDATES_READ);
        $decisionsRead = $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_READ);

        $trace = new NullPlatformIntelligenceTraceRecorder;
        $signals = $signalsRead ? $this->signalEngine->build($trace) : [];
        $candidates = ($signalsRead && $candidatesRead)
            ? $this->candidateEngine->buildFromSignals($signals, $trace)
            : [];

        $causalSignalLinks = [];
        $contextualSignals = [];
        foreach ($signals as $sig) {
            $link = CorrelationRuleEvaluator::signalToIncident($sig, $incident);
            if ($link === null) {
                continue;
            }
            $entry = array_merge([
                'entity_type' => 'signal',
                'entity_ref' => $sig->signal_key,
                'signal' => PlatformSignalContractSerializer::toArray($sig),
            ], $link);
            if ($link['relation_type'] === CorrelationRelationType::Causal->value) {
                $causalSignalLinks[] = $entry;
            } elseif ($link['relation_type'] === CorrelationRelationType::Contextual->value && count($contextualSignals) < 15) {
                $contextualSignals[] = $entry;
            }
            if (count($causalSignalLinks) + count($contextualSignals) >= 40) {
                break;
            }
        }

        $candidateLinks = [];
        $originating = null;
        foreach ($candidates as $cand) {
            $link = CorrelationRuleEvaluator::candidateToIncident($cand, $incident);
            if ($link !== null) {
                $entry = array_merge([
                    'entity_type' => 'candidate',
                    'entity_ref' => $cand->incident_key,
                    'candidate_incident_key' => $cand->incident_key,
                ], $link);
                $candidateLinks[] = $entry;
                if ($originating === null) {
                    $originating = [
                        'incident_key' => $cand->incident_key,
                        'title' => $cand->title,
                        'why_summary' => $cand->why_summary,
                        'severity' => $cand->severity->value,
                        'confidence' => $cand->confidence,
                        'source_signals' => $cand->source_signals,
                        'grouping_reason' => $cand->grouping_reason,
                    ];
                }
            }
        }

        $decisions = [];
        if ($decisionsRead) {
            $decRows = PlatformDecisionLogEntry::query()
                ->where('incident_key', $incidentKey)
                ->orderByDesc('created_at')
                ->orderBy('decision_id')
                ->limit(30)
                ->get();
            foreach ($decRows as $d) {
                $decisions[] = [
                    'entry' => PlatformDecisionLogEntryContractSerializer::toArray($d->toContract()),
                    'relation_type' => CorrelationRelationType::TimelineRelated->value,
                    'relation_reason' => 'decision_row_scoped_to_incident_key',
                    'compact_why' => 'قرار مسجّل ضمن نطاق هذا الحادث.',
                ];
            }
        }

        $workflowRuns = [];
        if ($this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_READ)) {
            $runs = PlatformGuidedWorkflowIdempotency::query()
                ->where('incident_key', $incidentKey)
                ->orderByDesc('id')
                ->limit(25)
                ->get();
            foreach ($runs as $r) {
                $workflowRuns[] = [
                    'workflow_key' => $r->workflow_key,
                    'status' => $r->status,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'relation_type' => CorrelationRelationType::TimelineRelated->value,
                    'relation_reason' => 'workflow_idempotency_row_scoped_to_incident',
                    'compact_why' => 'تنفيذ مسار موجّه مرتبط بهذا الحادث.',
                ];
            }
        }

        $summary = [
            'causal_signal_links' => count($causalSignalLinks),
            'contextual_signals' => count($contextualSignals),
            'candidate_links' => count($candidateLinks),
            'decisions' => count($decisions),
            'workflow_runs' => count($workflowRuns),
        ];

        $this->traceEmitter->correlationBuilt($actor, $incidentKey, $summary);
        $this->traceEmitter->incidentContextLinked(
            $actor,
            $incidentKey,
            count($causalSignalLinks) + count($contextualSignals) + count($candidateLinks) + count($decisions),
        );

        return [
            'incident' => $this->incidentSummary($incident),
            'causal_signal_links' => $causalSignalLinks,
            'contextual_signals' => $contextualSignals,
            'candidate_links' => $candidateLinks,
            'originating_candidate' => $originating,
            'decisions' => $decisions,
            'workflow_runs' => $workflowRuns,
            'executive_summary' => $this->executiveSummaryText($incident, $summary),
            'meta' => [
                'ordering' => 'signals_scan_order_engine_default,decisions_created_at_desc,workflow_id_desc',
                'permissions_used' => [
                    'signals' => $signalsRead,
                    'candidates' => $candidatesRead,
                    'decisions' => $decisionsRead,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incidentSummary(PlatformIncidentContract $i): array
    {
        return [
            'incident_key' => $i->incident_key,
            'title' => $i->title,
            'status' => $i->status->value,
            'severity' => $i->severity->value,
            'confidence' => $i->confidence,
            'escalation_state' => $i->escalation_state->value,
            'why_summary' => $i->why_summary,
            'affected_companies' => $i->affected_companies,
            'source_signals' => $i->source_signals,
        ];
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function executiveSummaryText(PlatformIncidentContract $incident, array $summary): string
    {
        return sprintf(
            'حادث %s — %d ربط سببي بالإشارات، %d سياق إشارة، %d ربط مرشح، %d قرار، %d تنفيذ مسار موجّه.',
            $incident->incident_key,
            $summary['causal_signal_links'],
            $summary['contextual_signals'],
            $summary['candidate_links'],
            $summary['decisions'],
            $summary['workflow_runs'],
        );
    }
}
