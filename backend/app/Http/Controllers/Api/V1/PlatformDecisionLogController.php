<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformIntelligence\StorePlatformDecisionLogEntryRequest;
use App\Support\PlatformIntelligence\DecisionLog\DecisionLogRepository;
use App\Support\PlatformIntelligence\DecisionLog\DecisionRecordingException;
use App\Support\PlatformIntelligence\DecisionLog\DecisionRecordingService;
use App\Support\PlatformIntelligence\DecisionSerialization\PlatformDecisionLogEntryContractSerializer;
use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformDecisionLogController extends Controller
{
    public function index(Request $request, DecisionLogRepository $decisions, IncidentRepository $incidents): JsonResponse
    {
        $validated = $request->validate([
            'incident_key' => ['required', 'string', 'max:128'],
            'decision_type' => ['nullable', 'string', Rule::in(PlatformDecisionType::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($incidents->findByIncidentKey($validated['incident_key']) === null) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $typeFilter = isset($validated['decision_type']) && $validated['decision_type'] !== ''
            ? (string) $validated['decision_type']
            : null;

        $paginator = $decisions->paginateByIncidentKey($validated['incident_key'], $perPage, $typeFilter);

        $data = collect($paginator->items())
            ->map(static fn ($m) => PlatformDecisionLogEntryContractSerializer::toArray($m->toContract()))
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'order' => 'created_at_asc,decision_id_asc',
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    /**
     * Same as {@see index} with incident_key from route (URL-encoded keys).
     */
    public function indexForIncident(Request $request, string $incident_key, DecisionLogRepository $decisions, IncidentRepository $incidents): JsonResponse
    {
        $request->merge(['incident_key' => $incident_key]);

        return $this->index($request, $decisions, $incidents);
    }

    public function store(
        StorePlatformDecisionLogEntryRequest $request,
        string $incident_key,
        DecisionRecordingService $recording,
        IncidentRepository $incidents,
    ): JsonResponse {
        $key = $incident_key;
        if ($incidents->findByIncidentKey($key) === null) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $before = $incidents->findByIncidentKey($key);
        $statusBefore = $before?->status;

        try {
            $contract = $recording->record($key, $request->user(), $request->validated());
        } catch (DecisionRecordingException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $after = $incidents->findByIncidentKey($key);
        if ($after === null || $after->status !== $statusBefore) {
            return response()->json(['message' => 'incident_state_integrity_violation'], 500);
        }

        return response()->json([
            'data' => PlatformDecisionLogEntryContractSerializer::toArray($contract),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ], 201);
    }
}
