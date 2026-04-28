<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\IncidentSerialization\PlatformIncidentContractSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformIntelligenceIncidentsController extends Controller
{
    public function index(Request $request, IncidentRepository $repository): JsonResponse
    {
        $filters = [
            'status' => $request->query('status', ''),
            'severity' => $request->query('severity', ''),
            'owner' => $request->query('owner', ''),
            'incident_type' => $request->query('incident_type', ''),
            'escalation_state' => $request->query('escalation_state', ''),
            'company_id' => $request->query('company_id', ''),
            'fresh_hours' => $request->query('fresh_hours', ''),
        ];

        $rows = $repository->listOrdered($filters);
        $data = $rows->map(static fn ($m) => PlatformIncidentContractSerializer::toArray($m->toContract()))->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'order' => 'severity_desc,last_status_change_desc,incident_key_asc',
            ],
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function show(string $incident_key, IncidentRepository $repository): JsonResponse
    {
        $row = $repository->findByIncidentKey($incident_key);
        if ($row === null) {
            return response()->json(['message' => 'not_found'], 404);
        }

        return response()->json([
            'data' => PlatformIncidentContractSerializer::toArray($row->toContract()),
            'timeline' => $repository->timelineFor($incident_key),
            'operator_notes' => $row->operator_notes ?? [],
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }
}
