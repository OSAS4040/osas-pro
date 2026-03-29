<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        // Check if leaves table exists
        if (!DB::getSchemaBuilder()->hasTable('leaves')) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $query = DB::table('leaves')
            ->join('employees', 'leaves.employee_id', '=', 'employees.id')
            ->where('leaves.company_id', $companyId)
            ->select('leaves.*', 'employees.name as employee_name', 'employees.employee_number');

        if ($request->filled('status')) {
            $query->where('leaves.status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('leaves.employee_id', $request->employee_id);
        }

        $total = $query->count();
        $leaves = $query->orderByDesc('leaves.created_at')->paginate(20);

        return response()->json([
            'data' => $leaves->items(),
            'meta' => ['total' => $total, 'current_page' => $leaves->currentPage(), 'last_page' => $leaves->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('leaves')) {
            return response()->json(['message' => 'Leaves module not enabled'], 503);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'type'        => 'required|in:annual,sick,emergency,unpaid,other',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'nullable|string|max:1000',
        ]);

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end   = \Carbon\Carbon::parse($validated['end_date']);
        $days  = $start->diffInWeekdays($end) + 1;

        $id = DB::table('leaves')->insertGetId([
            'uuid'        => (string) \Illuminate\Support\Str::uuid(),
            'company_id'  => $companyId,
            'employee_id' => $validated['employee_id'],
            'type'        => $validated['type'],
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'days'        => $days,
            'reason'      => $validated['reason'] ?? null,
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $leave = DB::table('leaves')->find($id);
        return response()->json(['data' => $leave, 'message' => 'Leave request submitted'], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('leaves')) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('leaves')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'updated_at' => now()]);

        return response()->json(['message' => 'Leave approved']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('leaves')) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('leaves')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update(['status' => 'rejected', 'updated_at' => now()]);

        return response()->json(['message' => 'Leave rejected']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('leaves')) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('leaves')->where('id', $id)->where('company_id', $companyId)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
