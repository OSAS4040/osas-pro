<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\DomainEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only inspection of persisted domain events (Phase 1).
 *
 * @see config('intelligent.internal_dashboard')
 */
class DomainEventInspectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DomainEvent::query()->orderByDesc('occurred_at');

        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->company_id);
        }

        if ($request->filled('event_name')) {
            $q->where('event_name', $request->string('event_name'));
        }

        if ($request->filled('aggregate_type')) {
            $q->where('aggregate_type', $request->string('aggregate_type'));
        }

        if ($request->filled('aggregate_id')) {
            $q->where('aggregate_id', $request->string('aggregate_id'));
        }

        if ($request->filled('trace_id')) {
            $q->where('trace_id', $request->string('trace_id'));
        }

        if ($request->filled('from')) {
            $q->where('occurred_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $q->where('occurred_at', '<=', $request->date('to')->endOfDay());
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        $paginator = $q->paginate($perPage);

        return response()->json([
            'data'     => $paginator->items(),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
