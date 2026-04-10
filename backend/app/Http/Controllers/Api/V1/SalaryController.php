<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ApprovalWorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SalaryController extends Controller
{
    public function __construct(private readonly ApprovalWorkflowService $approvalWorkflowService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('salaries')) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $query = DB::table('salaries')
            ->join('employees', 'salaries.employee_id', '=', 'employees.id')
            ->where('salaries.company_id', $companyId)
            ->select('salaries.*', 'employees.name as employee_name', 'employees.employee_number');

        if ($request->filled('month')) {
            $query->where('salaries.month', $request->month);
        }
        if ($request->filled('status')) {
            $query->where('salaries.status', $request->status);
        }

        $total = $query->count();
        $salaries = $query->orderByDesc('salaries.month')->paginate(20);

        return response()->json([
            'data' => $salaries->items(),
            'meta' => ['total' => $total, 'current_page' => $salaries->currentPage(), 'last_page' => $salaries->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('salaries')) {
            return response()->json(['message' => 'Salaries module not enabled'], 503);
        }

        $validated = $request->validate([
            'employee_id'   => 'required|integer',
            'month'         => 'required|string|regex:/^\d{4}-\d{2}$/',
            'base_salary'   => 'required|numeric|min:0',
            'allowances'    => 'nullable|numeric|min:0',
            'deductions'    => 'nullable|numeric|min:0',
            'commissions'   => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string|max:1000',
        ]);

        // Check for duplicate
        $exists = DB::table('salaries')
            ->where('company_id', $companyId)
            ->where('employee_id', $validated['employee_id'])
            ->where('month', $validated['month'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Salary record already exists for this month'], 422);
        }

        $id = DB::table('salaries')->insertGetId([
            'uuid'         => (string) \Illuminate\Support\Str::uuid(),
            'company_id'   => $companyId,
            'employee_id'  => $validated['employee_id'],
            'month'        => $validated['month'],
            'base_salary'  => $validated['base_salary'],
            'allowances'   => $validated['allowances'] ?? 0,
            'deductions'   => $validated['deductions'] ?? 0,
            'commissions'  => $validated['commissions'] ?? 0,
            'status'       => 'draft',
            'notes'        => $validated['notes'] ?? null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $salary = DB::table('salaries')->find($id);
        $this->approvalWorkflowService->ensurePendingWorkflow(
            $companyId,
            'salary',
            $id,
            (int) $request->user()->id,
            null,
            'salary.approval',
            'Salary draft created',
            ['module' => 'salaries'],
            1
        );
        return response()->json(['data' => $salary, 'message' => 'Salary record created'], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('salaries')) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $affected = DB::table('salaries')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'updated_at' => now()]);

        if ($affected === 0) {
            return response()->json([
                'message' => 'Salary status transition current -> approved is not allowed.',
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        try {
            $this->approvalWorkflowService->transitionBySubject(
                $companyId,
                'salary',
                $id,
                'approved',
                (int) $request->user()->id,
                (string) $request->input('note', '')
            );
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        return response()->json(['message' => 'Salary approved', 'trace_id' => app('trace_id')]);
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        if (!DB::getSchemaBuilder()->hasTable('salaries')) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('salaries')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update(['status' => 'paid', 'paid_at' => now()->toDateString(), 'updated_at' => now()]);

        return response()->json(['message' => 'Salary marked as paid']);
    }

    public function summary(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $month = $request->input('month', now()->format('Y-m'));

        if (!DB::getSchemaBuilder()->hasTable('salaries')) {
            return response()->json(['data' => ['total_net' => 0, 'total_employees' => 0, 'paid' => 0, 'pending' => 0]]);
        }

        $data = DB::table('salaries')
            ->where('company_id', $companyId)
            ->where('month', $month)
            ->selectRaw('COUNT(*) as total_employees, SUM(base_salary + allowances + commissions - deductions) as total_net, SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) as paid, SUM(CASE WHEN status != \'paid\' THEN 1 ELSE 0 END) as pending')
            ->first();

        return response()->json(['data' => $data]);
    }
}
