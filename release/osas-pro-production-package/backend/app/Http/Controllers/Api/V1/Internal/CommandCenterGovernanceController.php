<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StoreCommandCenterGovernanceRequest;
use App\Services\Intelligence\Phase7\CommandCenterGovernanceRef;
use App\Services\Intelligence\Phase7\CommandCenterGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 7A — append-only governance audit for command-center items. No business execution.
 */
class CommandCenterGovernanceController extends Controller
{
    public function __construct(
        private readonly CommandCenterGovernanceService $governance,
    ) {}

    public function store(StoreCommandCenterGovernanceRequest $request): JsonResponse
    {
        $this->assertGovernanceEnabled();
        $this->assertMayRecord($request);

        $user = $request->user();
        $result = $this->governance->record(
            $user,
            (string) $request->input('governance_ref'),
            (string) $request->input('action'),
            $request->input('note'),
            $request->input('client_context'),
        );

        $audit = $result['audit'];

        return response()->json([
            'data' => [
                'id'         => $audit->uuid,
                'created_at' => $audit->created_at?->toIso8601String(),
            ],
            'meta' => [
                'read_only' => false,
                'phase'     => 7,
                'audit_only'=> true,
            ],
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $this->assertGovernanceEnabled();
        $this->assertMayRecord($request);

        $ref = (string) $request->query('governance_ref', '');
        if ($ref === '') {
            return response()->json(['message' => 'governance_ref is required.'], 422);
        }

        $payload = CommandCenterGovernanceRef::verify($ref);
        if ($payload === null) {
            return response()->json(['message' => 'Invalid governance_ref.'], 422);
        }

        $user = $request->user();
        if ((int) ($payload['c'] ?? 0) !== (int) $user->company_id) {
            abort(403, 'governance_ref does not belong to this tenant.');
        }

        $rows = $this->governance->historyForRef((int) $user->company_id, $ref);

        $data = $rows->map(function ($row): array {
            return [
                'id'         => $row->uuid,
                'action'     => $row->action,
                'note'       => $row->note,
                'actor'      => $row->user?->name,
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'data'     => $data,
            'meta'     => [
                'read_only' => true,
                'phase'     => 7,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function assertGovernanceEnabled(): void
    {
        $cc = (bool) config('intelligent.command_center_api.enabled')
            || (bool) config('intelligent.phase2.features.command_center');
        $gov = (bool) config('intelligent.command_center_governance.enabled');

        if (! $cc || ! $gov) {
            abort(404);
        }
    }

    private function assertMayRecord(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('intelligence.governance.record')) {
            abort(403, 'Governance recording is not permitted for this user.');
        }
    }
}
