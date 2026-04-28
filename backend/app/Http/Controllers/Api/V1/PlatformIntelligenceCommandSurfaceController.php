<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PlatformIntelligence\CommandCenter\CommandSurfaceAssembler;
use App\Support\PlatformIntelligence\Correlation\IncidentCorrelationAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformIntelligenceCommandSurfaceController extends Controller
{
    public function commandSurface(Request $request, CommandSurfaceAssembler $assembler): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $data = $assembler->build($user);
        $status = isset($data['error']) && $data['error'] === 'forbidden' ? 403 : 200;

        return response()->json(array_merge($data, [
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]), $status);
    }

    public function incidentCorrelation(
        string $incident_key,
        Request $request,
        IncidentCorrelationAssembler $assembler,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $data = $assembler->build($incident_key, $user);
        if (($data['error'] ?? null) === 'forbidden') {
            return response()->json($data, 403);
        }
        if (($data['error'] ?? null) === 'not_found') {
            return response()->json($data, 404);
        }

        return response()->json([
            'data' => $data,
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }
}
