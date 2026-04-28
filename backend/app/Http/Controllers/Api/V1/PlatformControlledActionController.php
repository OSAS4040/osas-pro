<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PlatformIntelligence\ControlledActionExecution\ControlledActionExecutor;
use App\Support\PlatformIntelligence\ControlledActions\PlatformControlledActionContractSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformControlledActionController extends Controller
{
    public function index(Request $request, ControlledActionExecutor $executor, string $incident_key): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $data = $executor->listForIncident($user, $incident_key);

        return response()->json([
            'data' => $data,
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function createFollowUp(Request $request, ControlledActionExecutor $executor, string $incident_key): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->createFollowUp($user, $incident_key, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ], 201);
    }

    public function requestHumanReview(Request $request, ControlledActionExecutor $executor, string $incident_key): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->requestHumanReview($user, $incident_key, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ], 201);
    }

    public function linkInternalTaskReference(Request $request, ControlledActionExecutor $executor, string $incident_key): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->linkInternalTaskReference($user, $incident_key, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ], 201);
    }

    public function assignOwner(Request $request, ControlledActionExecutor $executor, string $action_id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->assignFollowUpOwner($user, $action_id, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function schedule(Request $request, ControlledActionExecutor $executor, string $action_id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->scheduleFollowUpWindow($user, $action_id, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function complete(Request $request, ControlledActionExecutor $executor, string $action_id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->markFollowUpCompleted($user, $action_id, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function cancel(Request $request, ControlledActionExecutor $executor, string $action_id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->cancelFollowUpWithReason($user, $action_id, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }

    public function reopen(Request $request, ControlledActionExecutor $executor, string $action_id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        $c = $executor->reopenFollowUpIfNeeded($user, $action_id, $request->all());

        return response()->json([
            'data' => PlatformControlledActionContractSerializer::toArray($c),
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }
}
