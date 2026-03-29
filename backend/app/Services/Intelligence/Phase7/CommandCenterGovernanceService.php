<?php

namespace App\Services\Intelligence\Phase7;

use App\Models\IntelligenceCommandCenterGovernanceAudit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Phase 7A — append-only governance audit. No domain side effects.
 */
final class CommandCenterGovernanceService
{
    public const ACTIONS = [
        'acknowledged',
        'needs_follow_up',
        'ignored_for_now',
    ];

    public const NOTE_MAX = 500;

    /**
     * Latest governance summary per ref (max id per governance_ref for company).
     *
     * @param  list<string>  $refs
     * @return array<string, array{action: string, at: string|null, by: string|null}>
     */
    public function latestSummariesForRefs(int $companyId, array $refs): array
    {
        $refs = array_values(array_unique(array_filter($refs)));
        if ($refs === []) {
            return [];
        }

        $rows = IntelligenceCommandCenterGovernanceAudit::query()
            ->where('company_id', $companyId)
            ->whereIn('governance_ref', $refs)
            ->with('user:id,name')
            ->orderByDesc('id')
            ->get()
            ->unique('governance_ref')
            ->values();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->governance_ref] = [
                'action' => (string) $row->action,
                'at'     => $row->created_at ? $row->created_at->toIso8601String() : null,
                'by'     => $row->user?->name ? (string) $row->user->name : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $clientContext
     * @return array{audit: IntelligenceCommandCenterGovernanceAudit}
     */
    public function record(
        User $user,
        string $governanceRef,
        string $action,
        ?string $note,
        ?array $clientContext,
    ): array {
        $payload = CommandCenterGovernanceRef::verify($governanceRef);
        if ($payload === null) {
            abort(422, 'Invalid governance_ref.');
        }
        if ((int) ($payload['c'] ?? 0) !== (int) $user->company_id) {
            abort(403, 'governance_ref does not belong to this tenant.');
        }

        if (! in_array($action, self::ACTIONS, true)) {
            abort(422, 'Unsupported action.');
        }

        $note = $this->sanitizeNote($note);

        $audit = IntelligenceCommandCenterGovernanceAudit::create([
            'uuid'                  => (string) Str::uuid(),
            'company_id'            => (int) $user->company_id,
            'user_id'               => (int) $user->id,
            'governance_ref'        => $governanceRef,
            'item_source'           => (string) ($payload['s'] ?? ''),
            'item_id'               => (string) ($payload['i'] ?? ''),
            'item_title_snapshot'   => Str::limit((string) ($payload['ti'] ?? ''), 512, ''),
            'severity_snapshot'     => Str::limit((string) ($payload['se'] ?? ''), 32, ''),
            'window_from'           => Carbon::parse((string) ($payload['wf'] ?? '')),
            'window_to'             => Carbon::parse((string) ($payload['wt'] ?? '')),
            'snapshot_generated_at' => now(),
            'action'                => $action,
            'note'                  => $note,
            'client_context'        => $clientContext,
            'trace_id'              => app()->bound('trace_id') ? (string) app('trace_id') : null,
        ]);

        return ['audit' => $audit];
    }

    /**
     * @return \Illuminate\Support\Collection<int, IntelligenceCommandCenterGovernanceAudit>
     */
    public function historyForRef(int $companyId, string $governanceRef, int $limit = 50)
    {
        return IntelligenceCommandCenterGovernanceAudit::query()
            ->where('company_id', $companyId)
            ->where('governance_ref', $governanceRef)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function sanitizeNote(?string $note): ?string
    {
        if ($note === null || $note === '') {
            return null;
        }
        $stripped = trim(strip_tags($note));
        if ($stripped === '') {
            return null;
        }
        if (mb_strlen($stripped) > self::NOTE_MAX) {
            abort(422, 'Note exceeds maximum length.');
        }

        return $stripped;
    }
}
