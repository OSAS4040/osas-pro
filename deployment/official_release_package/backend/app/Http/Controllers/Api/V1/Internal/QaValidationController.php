<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\QaValidationRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QaValidationController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== UserRole::Owner) {
            return response()->json(['message' => 'Forbidden — owner only'], 403);
        }

        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $email = $user->email;
        $stressOps = (int) $request->input('stress_ops', 1000);
        $raceWorkers = (int) $request->input('race_workers', 20);
        $runSimulation = filter_var($request->input('include_simulation', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $payload = QaValidationRunnerService::make()->run(
                $email,
                $stressOps,
                $raceWorkers,
                $runSimulation,
            );

            return response()->json([
                'data'     => $payload,
                'trace_id' => app('trace_id'),
            ]);
        } catch (\Throwable $e) {
            Log::error('qa.validation.run.exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'QA validation failed: '.$e->getMessage(),
                'trace_id' => app('trace_id'),
            ], 500);
        }
    }

    public function results(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== UserRole::Owner) {
            return response()->json(['message' => 'Forbidden — owner only'], 403);
        }

        $storagePath = storage_path('app/qa-validation-latest.json');
        if (! is_readable($storagePath)) {
            return response()->json([
                'data'     => null,
                'message'  => 'No validation run yet — use POST /internal/run-tests',
                'trace_id' => app('trace_id'),
            ]);
        }

        $json = file_get_contents($storagePath);
        $data = json_decode($json, true);

        return response()->json([
            'data'     => $data,
            'trace_id' => app('trace_id'),
        ]);
    }
}
