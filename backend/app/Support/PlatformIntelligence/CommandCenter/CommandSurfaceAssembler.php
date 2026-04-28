<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CommandCenter;

use App\Models\PlatformDecisionLogEntry;
use App\Models\PlatformGuidedWorkflowIdempotency;
use App\Models\PlatformIncident;
use App\Models\User;
use App\Services\Platform\PlatformPermissionService;
use App\Support\PlatformIntelligence\CandidateEngine\PlatformIncidentCandidateEngine;
use App\Support\PlatformIntelligence\CommandPrioritization\CommandSurfacePrioritizer;
use App\Support\PlatformIntelligence\Correlation\CorrelationTraceEmitter;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\PlatformOperatorPermissionMatrix;
use App\Support\PlatformIntelligence\SignalEngine\PlatformSignalEngine;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;

/**
 * Read-only unified command surface — composes existing intelligence layers.
 */
final class CommandSurfaceAssembler
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
    public function build(User $actor): array
    {
        if (! $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_READ)) {
            return ['error' => 'forbidden', 'message' => 'missing_incidents_read'];
        }

        $signalsRead = $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_SIGNALS_READ);
        $candidatesRead = $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_CANDIDATES_READ);
        $decisionsRead = $this->permissions->hasPermission($actor, PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_READ);

        $rows = $this->incidents->listOrdered([]);
        $incidentArrays = $rows->map(static function ($m): array {
            return [
                'incident_key' => $m->incident_key,
                'title' => $m->title,
                'status' => $m->status,
                'severity' => $m->severity,
                'escalation_state' => $m->escalation_state,
                'last_status_change_at' => $m->last_status_change_at?->toIso8601String(),
                'affected_companies' => $m->affected_companies,
            ];
        })->all();

        $sorted = CommandSurfacePrioritizer::sortIncidentsStable($incidentArrays);
        $openHigh = array_values(array_filter($sorted, static function (array $r): bool {
            $st = (string) ($r['status'] ?? '');
            $sev = (string) ($r['severity'] ?? '');

            return ! in_array($st, [PlatformIncidentStatus::Closed->value, PlatformIncidentStatus::Resolved->value], true)
                && in_array($sev, ['high', 'critical'], true);
        }));
        $openHigh = array_slice($openHigh, 0, 8);

        $escalated = array_values(array_filter($sorted, static fn (array $r) => ($r['escalation_state'] ?? '') === 'escalated'));
        $escalated = array_slice($escalated, 0, 6);

        $monitoringStale = array_values(array_filter($sorted, static fn (array $r) => ($r['status'] ?? '') === 'monitoring'));
        $monitoringStale = array_slice($monitoringStale, 0, 5);

        $decisionsFollowUp = [];
        if ($decisionsRead) {
            $decisionsFollowUp = PlatformDecisionLogEntry::query()
                ->where('follow_up_required', true)
                ->orderByDesc('created_at')
                ->limit(12)
                ->get()
                ->map(static fn ($d) => [
                    'decision_id' => $d->decision_id,
                    'incident_key' => $d->incident_key,
                    'decision_type' => $d->decision_type,
                    'decision_summary' => $d->decision_summary,
                    'created_at' => $d->created_at?->toIso8601String(),
                    'relation_type' => 'timeline_related',
                    'compact_why' => 'قرار يتطلب متابعة لاحقة.',
                ])
                ->all();
        }

        $workflowRecent = PlatformGuidedWorkflowIdempotency::query()
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(static fn ($w) => [
                'workflow_key' => $w->workflow_key,
                'incident_key' => $w->incident_key,
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
                'relation_type' => 'timeline_related',
                'compact_why' => 'آخر تنفيذ لمسار موجّه.',
            ])
            ->all();

        $signalsUncovered = [];
        $candidatesNext = [];
        $companyRisk = [];

        $trace = new NullPlatformIntelligenceTraceRecorder;
        $signals = [];
        if ($signalsRead) {
            $signals = $this->signalEngine->build($trace);
            $openKeys = [];
            foreach ($rows as $m) {
                if (in_array((string) $m->status, [PlatformIncidentStatus::Closed->value, PlatformIncidentStatus::Resolved->value], true)) {
                    continue;
                }
                foreach ((array) $m->source_signals as $sk) {
                    $openKeys[(string) $sk] = true;
                }
            }
            $n = 0;
            foreach ($signals as $sig) {
                foreach ($sig->affected_companies as $cid) {
                    $k = (string) $cid;
                    $companyRisk[$k] = ($companyRisk[$k] ?? 0) + 1;
                }
                if (! isset($openKeys[$sig->signal_key])) {
                    $signalsUncovered[] = [
                        'signal_key' => $sig->signal_key,
                        'severity' => $sig->severity->value,
                        'title' => $sig->title,
                        'relation_type' => 'contextual',
                        'compact_why' => 'لا يظهر كمصدر لأي حادث مفتوح حاليًا.',
                    ];
                    $n++;
                    if ($n >= 10) {
                        break;
                    }
                }
            }
        }

        if ($signalsRead && $candidatesRead && $signals !== []) {
            $candidates = $this->candidateEngine->buildFromSignals($signals, $trace);
            $materialized = PlatformIncident::query()->pluck('incident_key')->all();
            $matSet = array_fill_keys($materialized, true);
            foreach ($candidates as $c) {
                if (! isset($matSet[$c->incident_key])) {
                    $candidatesNext[] = [
                        'incident_key' => $c->incident_key,
                        'title' => $c->title,
                        'severity' => $c->severity->value,
                        'confidence' => $c->confidence,
                        'relation_type' => 'derived',
                        'compact_why' => 'مرشح بلا صف حادث مادّي بعد — مرشح للتمثيل.',
                    ];
                }
                if (count($candidatesNext) >= 8) {
                    break;
                }
            }
        }

        arsort($companyRisk);
        $companyRiskCards = [];
        $i = 0;
        foreach ($companyRisk as $cid => $cnt) {
            $companyRiskCards[] = [
                'company_id' => $cid,
                'stacked_signal_refs' => (int) $cnt,
                'relation_type' => 'contextual',
                'compact_why' => 'كثافة إشارات على نفس الشركة في اللقطة الحالية.',
            ];
            $i++;
            if ($i >= 8) {
                break;
            }
        }

        $payload = [
            'summary' => [
                'open_high_severity' => count($openHigh),
                'escalated' => count($escalated),
                'monitoring_sample' => count($monitoringStale),
                'decisions_follow_up' => count($decisionsFollowUp),
                'workflows_recent' => count($workflowRecent),
                'signals_uncovered' => count($signalsUncovered),
                'candidates_next' => count($candidatesNext),
                'company_risk_cards' => count($companyRiskCards),
            ],
            'open_high_severity_incidents' => $openHigh,
            'recently_escalated_incidents' => $escalated,
            'monitoring_incidents_sample' => $monitoringStale,
            'decisions_requiring_follow_up' => $decisionsFollowUp,
            'recent_workflow_executions' => $workflowRecent,
            'signals_not_on_open_incidents' => $signalsUncovered,
            'candidates_likely_to_materialize' => $candidatesNext,
            'companies_with_stacked_signals' => $companyRiskCards,
            'meta' => [
                'incident_ordering' => 'command_prioritizer_stable',
                'permissions_used' => [
                    'signals' => $signalsRead,
                    'candidates' => $candidatesRead,
                    'decisions' => $decisionsRead,
                ],
            ],
        ];

        $this->traceEmitter->commandSurfaceRendered($actor, [
            'sections' => array_keys($payload),
            'summary' => $payload['summary'],
        ]);

        return $payload;
    }
}
