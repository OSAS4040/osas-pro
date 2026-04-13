<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Task;
use App\Services\AttendanceService;
use App\Services\AuditLogger;
use App\Services\CommissionService;
use App\Services\TaskEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkshopController extends Controller
{
    // ────────────────────────────────────────────────────────
    // EMPLOYEES
    // ────────────────────────────────────────────────────────

    public function employeeStats(Request $request): JsonResponse
    {
        $cid = $request->user()->company_id;
        $q   = Employee::where('company_id', $cid);

        $total      = (clone $q)->count();
        $active     = (clone $q)->where('status', 'active')->count();
        $inactive   = (clone $q)->where('status', 'inactive')->count();
        $suspended  = (clone $q)->where('status', 'suspended')->count();
        $missingId = (clone $q)->where(function ($q2) {
            $q2->whereNull('national_id')->orWhere('national_id', '');
        })->count();
        $needsHrSync = 0;
        foreach ((clone $q)->get(['hr_integrations', 'national_id']) as $e) {
            $h = $e->hr_integrations ?? [];
            $gosiOk  = ! empty($h['gosi']['subscription_number'] ?? null);
            $qiwaOk  = ! empty($h['qiwa']['employee_ref'] ?? null);
            $econtOk = ! empty($h['e_contract']['contract_id'] ?? null);
            if (! $gosiOk || ! $qiwaOk || ! $econtOk || empty($e->national_id)) {
                $needsHrSync++;
            }
        }

        return response()->json([
            'data' => [
                'total'            => $total,
                'active'           => $active,
                'inactive'         => $inactive,
                'suspended'        => $suspended,
                'missing_national_id' => $missingId,
                'needs_hr_sync'    => $needsHrSync,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function listEmployees(Request $request): JsonResponse
    {
        $user = $request->user();
        $per  = min(100, max(10, (int) $request->query('per_page', 50)));

        $employees = Employee::where('company_id', $user->company_id)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->query('search'), fn ($q, $v) => $q->where(fn ($q2) =>
                $q2->where('name', 'ilike', "%{$v}%")->orWhere('employee_number', 'ilike', "%{$v}%")
                    ->orWhere('national_id', 'ilike', "%{$v}%")
            ))
            ->with('user:id,email')
            ->orderByDesc('id')
            ->paginate($per);

        return response()->json($employees);
    }

    public function showEmployee(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $emp  = Employee::where('company_id', $user->company_id)->with('user:id,email')->findOrFail($id);
        return response()->json(['data' => $emp]);
    }

    public function storeEmployee(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name'             => 'required|string|max:120',
            'phone'            => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:120',
            'national_id'      => 'nullable|string|max:30',
            'position'         => 'nullable|string|max:80',
            'department'       => 'nullable|string|max:80',
            'hire_date'        => 'nullable|date',
            'termination_date' => 'nullable|date',
            'base_salary'      => 'nullable|numeric|min:0',
            'skills'           => 'nullable|array',
            'branch_id'        => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($q) => $q->where('company_id', $user->company_id)->whereNull('deleted_at')
                ),
            ],
            'hr_integrations'  => 'nullable|array',
            'internal_notes'   => 'nullable|string|max:5000',
            'status'             => 'nullable|string|in:active,inactive,suspended',
        ]);

        $emp = Employee::create(array_merge($data, [
            'company_id' => $user->company_id,
            'branch_id'  => $data['branch_id'] ?? $user->branch_id,
            'status'     => $data['status'] ?? 'active',
        ]));

        app(AuditLogger::class)->log('employee.created', Employee::class, $emp->id, [], $emp->toArray());

        return response()->json(['data' => $emp, 'message' => 'تم إنشاء الموظف.'], 201);
    }

    public function updateEmployee(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $emp  = Employee::where('company_id', $user->company_id)->findOrFail($id);
        $before = $emp->toArray();

        $data = $request->validate([
            'name'             => 'sometimes|string|max:120',
            'phone'            => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:120',
            'national_id'      => 'nullable|string|max:30',
            'position'         => 'nullable|string|max:80',
            'department'       => 'nullable|string|max:80',
            'hire_date'        => 'nullable|date',
            'termination_date' => 'nullable|date',
            'base_salary'      => 'nullable|numeric|min:0',
            'skills'           => 'nullable|array',
            'branch_id'        => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($q) => $q->where('company_id', $user->company_id)->whereNull('deleted_at')
                ),
            ],
            'status'           => 'nullable|string|in:active,inactive,suspended',
            'hr_integrations'  => 'nullable|array',
            'internal_notes'   => 'nullable|string|max:5000',
        ]);

        $emp->update($data);
        app(AuditLogger::class)->change($emp, 'employee.updated', $before, $emp->fresh()->toArray());

        return response()->json(['data' => $emp->fresh(), 'message' => 'تم التحديث.']);
    }

    // ────────────────────────────────────────────────────────
    // ATTENDANCE
    // ────────────────────────────────────────────────────────

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'device_id'   => 'nullable|string|max:120',
        ]);

        $emp = Employee::where('company_id', $user->company_id)->findOrFail($data['employee_id']);
        $log = app(AttendanceService::class)->checkIn(
            $emp->id, $user->company_id, $user->branch_id,
            $data['latitude'] ?? null, $data['longitude'] ?? null, $data['device_id'] ?? null
        );

        return response()->json(['data' => $log, 'message' => 'تم تسجيل الحضور.'], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        $emp = Employee::where('company_id', $user->company_id)->findOrFail($data['employee_id']);
        $log = app(AttendanceService::class)->checkOut(
            $emp->id, $user->company_id, $user->branch_id,
            $data['latitude'] ?? null, $data['longitude'] ?? null
        );

        return response()->json(['data' => $log, 'message' => 'تم تسجيل الانصراف.'], 201);
    }

    public function attendanceTodayAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $logs = \Illuminate\Support\Facades\DB::table('attendance_logs')
            ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
            ->where('attendance_logs.company_id', $user->company_id)
            ->whereDate('attendance_logs.logged_at', now()->toDateString())
            ->select('attendance_logs.*', 'employees.name as employee_name', 'employees.employee_number')
            ->orderByDesc('attendance_logs.logged_at')
            ->get();
        return response()->json(['data' => $logs, 'date' => now()->toDateString()]);
    }

    public function attendanceToday(Request $request, int $employeeId): JsonResponse
    {
        $user = $request->user();
        Employee::where('company_id', $user->company_id)->findOrFail($employeeId);
        $summary = app(AttendanceService::class)->todayLog($employeeId);
        return response()->json(['data' => $summary]);
    }

    public function attendanceMonth(Request $request, int $employeeId): JsonResponse
    {
        $user  = $request->user();
        Employee::where('company_id', $user->company_id)->findOrFail($employeeId);
        $year  = $request->query('year',  now()->year);
        $month = $request->query('month', now()->month);
        $summary = app(AttendanceService::class)->monthSummary($employeeId, $year, $month);
        return response()->json(['data' => $summary]);
    }

    /** سجل حضور/انصراف تفصيلي لشهر (للعرض في الجدول) */
    public function attendanceLogs(Request $request, int $employeeId): JsonResponse
    {
        $user  = $request->user();
        Employee::where('company_id', $user->company_id)->findOrFail($employeeId);
        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $logs  = AttendanceLog::query()
            ->where('attendance_logs.company_id', $user->company_id)
            ->where('attendance_logs.employee_id', $employeeId)
            ->whereYear('attendance_logs.logged_at', $year)
            ->whereMonth('attendance_logs.logged_at', $month)
            ->join('employees', 'employees.id', '=', 'attendance_logs.employee_id')
            ->select('attendance_logs.*', 'employees.name as employee_name', 'employees.employee_number')
            ->orderByDesc('attendance_logs.logged_at')
            ->get();

        return response()->json(['data' => $logs]);
    }

    /** كل سجلات الحضور للشركة في شهر (عند اختيار «كل الموظفين») */
    public function attendanceMonthAll(Request $request): JsonResponse
    {
        $user  = $request->user();
        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $logs  = \Illuminate\Support\Facades\DB::table('attendance_logs')
            ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
            ->where('attendance_logs.company_id', $user->company_id)
            ->whereYear('attendance_logs.logged_at', $year)
            ->whereMonth('attendance_logs.logged_at', $month)
            ->select('attendance_logs.*', 'employees.name as employee_name', 'employees.employee_number')
            ->orderByDesc('attendance_logs.logged_at')
            ->get();

        return response()->json(['data' => $logs, 'year' => $year, 'month' => $month]);
    }

    // ────────────────────────────────────────────────────────
    // TASKS
    // ────────────────────────────────────────────────────────

    public function listTasks(Request $request): JsonResponse
    {
        $user  = $request->user();
        $tasks = Task::where('company_id', $user->company_id)
            ->when($request->query('status'),      fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('assigned_to'), fn ($q, $v) => $q->where('assigned_to', $v))
            ->when($request->query('priority'),    fn ($q, $v) => $q->where('priority', $v))
            ->with(['employee:id,name', 'workOrder:id,order_number'])
            ->latest()
            ->paginate(25);

        return response()->json($tasks);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'title'             => 'required|string|max:200',
            'description'       => 'nullable|string',
            'type'              => 'nullable|string|in:service,inspection,admin',
            'priority'          => 'nullable|string|in:low,normal,high,urgent',
            'work_order_id'     => 'nullable|integer',
            'assigned_to'       => 'nullable|integer',
            'due_at'            => 'nullable|date',
            'estimated_minutes' => 'nullable|integer',
            'auto_assign'       => 'boolean',
            'skill'             => 'nullable|string',
        ]);

        if (!empty($data['auto_assign'])) {
            $task = app(TaskEngine::class)->autoAssign(
                $user->company_id,
                $user->branch_id,
                $data['skill'] ?? '',
                array_merge($data, ['company_id' => $user->company_id, 'branch_id' => $user->branch_id])
            );
        } else {
            $task = Task::create(array_merge($data, [
                'company_id'  => $user->company_id,
                'branch_id'   => $user->branch_id,
                'assigned_by' => $user->id,
                'status'      => $data['assigned_to'] ? 'assigned' : 'pending',
            ]));
        }

        return response()->json(['data' => $task, 'message' => 'تم إنشاء المهمة.'], 201);
    }

    public function updateTaskStatus(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $task   = Task::where('company_id', $user->company_id)->findOrFail($id);
        $engine = app(TaskEngine::class);

        $request->validate([
            'action'         => 'nullable|string|in:start,complete,assign',
            'status'         => 'nullable|string|in:pending,assigned,in_progress,completed,cancelled,review',
            'employee_id'    => 'nullable|integer',
            'notes'          => 'nullable|string',
            'actual_minutes' => 'nullable|integer|min:0',
        ]);

        if (! $request->filled('action') && ! $request->filled('status')) {
            return response()->json([
                'message'  => 'Provide action (start, complete, assign) or status for task transition.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        if ($request->filled('action') && $request->filled('status')) {
            return response()->json([
                'message'  => 'Send either action or status, not both.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $current = (string) $task->status;
        $directTransitions = [
            'pending'     => ['pending', 'assigned', 'in_progress', 'cancelled'],
            'assigned'    => ['assigned', 'pending', 'in_progress', 'cancelled'],
            'in_progress' => ['in_progress', 'completed', 'cancelled', 'review'],
            'review'      => ['review', 'completed', 'in_progress'],
            'completed'   => ['completed'],
            'cancelled'   => ['cancelled'],
        ];

        if ($request->filled('status')) {
            $target = (string) $request->input('status');
            $allowed = $directTransitions[$current] ?? [];
            if ($target !== $current && ! in_array($target, $allowed, true)) {
                return response()->json([
                    'message'  => "Task status transition {$current} -> {$target} is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
            $task->update(['status' => $target]);

            return response()->json([
                'data' => $task->fresh(),
                'message' => 'تم تحديث المهمة.',
                'trace_id' => app('trace_id'),
            ]);
        }

        $action = (string) $request->input('action');

        if ($action === 'start') {
            if (! in_array($current, ['pending', 'assigned'], true)) {
                return response()->json([
                    'message'  => "Task status transition {$current} -> start is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
            $result = $engine->start($task);
        } elseif ($action === 'complete') {
            if (! in_array($current, ['in_progress', 'review'], true)) {
                return response()->json([
                    'message'  => "Task status transition {$current} -> complete is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
            $result = $engine->complete(
                $task,
                (string) $request->input('notes', ''),
                $request->filled('actual_minutes') ? (int) $request->input('actual_minutes') : null
            );
        } else {
            $employeeId = (int) $request->input('employee_id', 0);
            if ($employeeId < 1) {
                return response()->json([
                    'message'  => 'employee_id is required for assign action.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
            Employee::where('company_id', $user->company_id)->findOrFail($employeeId);
            if (! in_array($current, ['pending', 'assigned', 'in_progress'], true)) {
                return response()->json([
                    'message'  => "Task status transition {$current} -> assign is not allowed.",
                    'code'     => 'TRANSITION_NOT_ALLOWED',
                    'status'   => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
            $result = $engine->assign($task, $employeeId);
        }

        return response()->json([
            'data' => $result,
            'message' => 'تم تحديث المهمة.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function taskStats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $stats = app(TaskEngine::class)->stats($user->company_id, $user->branch_id);
        return response()->json(['data' => $stats]);
    }

    public function smartTaskSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = app(TaskEngine::class)->smartSummary($user->company_id, $user->branch_id);
        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function suggestTaskAssignees(Request $request): JsonResponse
    {
        $user = $request->user();
        $skill = (string) $request->query('skill', '');
        $data = app(TaskEngine::class)->suggestedAssignees($user->company_id, $user->branch_id, $skill);
        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    // ────────────────────────────────────────────────────────
    // COMMISSIONS
    // ────────────────────────────────────────────────────────

    public function listCommissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $commissions = Commission::where('company_id', $user->company_id)
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('status'),      fn ($q, $v) => $q->where('status', $v))
            ->with('employee:id,name')
            ->latest()
            ->paginate(25);

        return response()->json($commissions);
    }

    public function listCommissionRules(Request $request): JsonResponse
    {
        $user = $request->user();
        $rules = CommissionRule::where('company_id', $user->company_id)
            ->with([
                'employee:id,name',
                'customer:id,name',
            ])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $rules]);
    }

    public function storeCommissionRule(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name'                    => 'nullable|string|max:160',
            'employee_id'             => 'nullable|integer',
            'customer_id'             => 'nullable|integer',
            'applies_to'              => 'required|string|in:invoice,work_order,service',
            'rate'                    => 'required|numeric|min:0|max:100',
            'min_amount'              => 'nullable|numeric|min:0',
            'max_commission_amount'   => 'nullable|numeric|min:0',
            'priority'                => 'nullable|integer|min:0|max:65535',
            'attendance_multiplier'   => 'nullable|numeric|min:0|max:10',
            'is_active'               => 'boolean',
            'meta'                    => 'nullable|array',
        ]);

        if (! empty($data['employee_id'])) {
            Employee::where('company_id', $user->company_id)->findOrFail($data['employee_id']);
        }
        if (! empty($data['customer_id'])) {
            Customer::where('company_id', $user->company_id)->findOrFail($data['customer_id']);
        }

        $rule = CommissionRule::create(array_merge($data, [
            'company_id' => $user->company_id,
            'is_active'  => $data['is_active'] ?? true,
            'priority'   => $data['priority'] ?? 0,
            'attendance_multiplier' => $data['attendance_multiplier'] ?? 1,
        ]));

        return response()->json([
            'data'    => $rule->load(['employee:id,name', 'customer:id,name']),
            'message' => 'تم إنشاء قاعدة العمولة.',
        ], 201);
    }

    public function updateCommissionRule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $rule = CommissionRule::where('company_id', $user->company_id)->findOrFail($id);
        $data = $request->validate([
            'name'                    => 'nullable|string|max:160',
            'employee_id'             => 'nullable|integer',
            'customer_id'             => 'nullable|integer',
            'applies_to'              => 'sometimes|string|in:invoice,work_order,service',
            'rate'                    => 'sometimes|numeric|min:0|max:100',
            'min_amount'              => 'nullable|numeric|min:0',
            'max_commission_amount'   => 'nullable|numeric|min:0',
            'priority'                => 'nullable|integer|min:0|max:65535',
            'attendance_multiplier'   => 'nullable|numeric|min:0|max:10',
            'is_active'               => 'boolean',
            'meta'                    => 'nullable|array',
        ]);

        if (array_key_exists('employee_id', $data) && $data['employee_id'] !== null) {
            Employee::where('company_id', $user->company_id)->findOrFail($data['employee_id']);
        }
        if (array_key_exists('customer_id', $data) && $data['customer_id'] !== null) {
            Customer::where('company_id', $user->company_id)->findOrFail($data['customer_id']);
        }

        $rule->update($data);

        return response()->json([
            'data'    => $rule->fresh()->load(['employee:id,name', 'customer:id,name']),
            'message' => 'تم تحديث قاعدة العمولة.',
        ]);
    }

    public function deleteCommissionRule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $rule = CommissionRule::where('company_id', $user->company_id)->findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'تم حذف قاعدة العمولة.']);
    }

    public function payCommission(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $c    = app(CommissionService::class)->markPaid($id, $user->id);
        app(AuditLogger::class)->log('commission.paid', Commission::class, $id, ['status' => 'pending'], ['status' => 'paid']);
        return response()->json(['data' => $c, 'message' => 'تم صرف العمولة.']);
    }
}
