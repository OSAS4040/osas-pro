<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformIntelligence\ExecuteGuidedWorkflowRequest;
use App\Models\PlatformGuidedWorkflowIdempotency;
use App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowExecutorException;
use App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowKey;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentLifecycleException;
use App\Support\PlatformIntelligence\WorkflowExecution\GuidedWorkflowExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class PlatformIncidentWorkflowController extends Controller
{
    public function index(string $incident_key, IncidentRepository $incidents, GuidedWorkflowExecutor $executor): JsonResponse
    {
        $row = $incidents->findByIncidentKey($incident_key);
        if ($row === null) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $user = request()->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        return response()->json([
            'data' => $executor->catalog($user, $row),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function execute(
        string $incident_key,
        ExecuteGuidedWorkflowRequest $request,
        IncidentRepository $incidents,
        GuidedWorkflowExecutor $executor,
    ): JsonResponse {
        $row = $incidents->findByIncidentKey($incident_key);
        if ($row === null) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $payload = $request->validated();
        $idempotencyKey = $payload['idempotency_key'];
        $workflowKey = GuidedWorkflowKey::from($payload['workflow_key']);

        $lock = Cache::lock('guided_workflow_exec:'.$idempotencyKey, 30);

        return $lock->block(10, function () use ($idempotencyKey, $incident_key, $workflowKey, $user, $executor, $payload): JsonResponse {
            $existing = PlatformGuidedWorkflowIdempotency::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null && $existing->status === 'completed') {
                return response()->json($existing->response_json, $existing->http_status);
            }

            try {
                $result = $executor->execute($workflowKey, $incident_key, $user, $payload);
                $body = [
                    'data' => $result,
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ];
                PlatformGuidedWorkflowIdempotency::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'incident_key' => $incident_key,
                    'workflow_key' => $workflowKey->value,
                    'actor_user_id' => $user->id,
                    'status' => 'completed',
                    'http_status' => 200,
                    'response_json' => $body,
                ]);

                return response()->json($body, 200);
            } catch (GuidedWorkflowExecutorException $e) {
                return response()->json(['message' => $e->getMessage()], $e->httpStatus);
            } catch (IncidentLifecycleException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    }
}
