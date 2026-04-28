<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PlatformIntelligence\CandidateEngine\PlatformIncidentCandidateEngine;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentMaterializationConflictException;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentMaterializationService;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentLifecycleException;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentLifecycleService;
use App\Support\PlatformIntelligence\IncidentSerialization\PlatformIncidentContractSerializer;
use App\Support\PlatformIntelligence\SignalEngine\PlatformSignalEngine;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformIncidentLifecycleController extends Controller
{
    public function materialize(
        Request $request,
        PlatformSignalEngine $signalEngine,
        PlatformIncidentCandidateEngine $candidateEngine,
        IncidentMaterializationService $materialization,
    ): JsonResponse {
        $validated = $request->validate([
            'incident_key' => ['required', 'string', 'max:128'],
        ]);

        $signals = $signalEngine->build(new NullPlatformIntelligenceTraceRecorder());
        $candidates = $candidateEngine->buildFromSignals($signals, new NullPlatformIntelligenceTraceRecorder());
        $match = null;
        foreach ($candidates as $c) {
            if ($c->incident_key === $validated['incident_key']) {
                $match = $c;
                break;
            }
        }
        if ($match === null) {
            return response()->json(['message' => 'candidate_not_found'], 404);
        }

        try {
            $incident = $materialization->materialize($match, $request->user());
        } catch (IncidentMaterializationConflictException) {
            return response()->json(['message' => 'already_materialized'], 409);
        }

        return response()->json([
            'data' => PlatformIncidentContractSerializer::toArray($incident),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ], 201);
    }

    public function acknowledge(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        return $this->runLifecycle(fn () => $lifecycle->acknowledge($incident_key, $request->user()));
    }

    public function moveUnderReview(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        return $this->runLifecycle(fn () => $lifecycle->moveUnderReview($incident_key, $request->user()));
    }

    public function escalate(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        $v = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        return $this->runLifecycle(fn () => $lifecycle->escalate($incident_key, $request->user(), $v['reason'] ?? null));
    }

    public function moveMonitoring(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        return $this->runLifecycle(fn () => $lifecycle->moveMonitoring($incident_key, $request->user()));
    }

    public function resolve(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:8000']]);

        return $this->runLifecycle(fn () => $lifecycle->resolve($incident_key, $request->user(), $v['reason']));
    }

    public function close(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:8000']]);

        return $this->runLifecycle(fn () => $lifecycle->close($incident_key, $request->user(), $v['reason']));
    }

    public function assignOwner(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        $v = $request->validate(['owner_ref' => ['required', 'string', 'max:190']]);

        return $this->runLifecycle(fn () => $lifecycle->assignOwner($incident_key, $request->user(), $v['owner_ref']));
    }

    public function appendNote(string $incident_key, Request $request, IncidentLifecycleService $lifecycle): JsonResponse
    {
        $v = $request->validate(['text' => ['required', 'string', 'min:1', 'max:2000']]);

        return $this->runLifecycle(fn () => $lifecycle->appendNote($incident_key, $request->user(), $v['text']));
    }

    /**
     * @param  callable(): void  $action
     */
    private function runLifecycle(callable $action): JsonResponse
    {
        try {
            $action();
        } catch (IncidentLifecycleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'trace_id' => app()->bound('trace_id') ? app('trace_id') : null]);
    }
}
