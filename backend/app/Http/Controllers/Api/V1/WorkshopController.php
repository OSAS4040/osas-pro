<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Task;
use App\Services\AttendanceService;
use App\Services\AuditLogger;
use App\Services\CommissionService;
use App\Services\TaskEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    // ────────────────────────────────────────────────────────
    // EMPLOYEES
    // ────────────────────────────────────────────────────────

    public function listEmployees(Request $request): JsonResponse
    {
        $user = $request->user();
        $employees = Employee::where('company_id', $user->company_id)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->query('search'), fn ($q, $v) => $q->where(fn ($q2) =>
                $q2->where('name', 'ilike', "%{$v}%")->orWhere('employee_number', 'ilike', "%{$v}%")
            ))
            ->with('user:id,email')
            ->paginate(20);

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
            'name'       => 'required|string|max:120',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:120',
            'position'   => 'nullable|string|max:80',
            'department' => 'nullable|string|max:80',
            'hire_date'  => 'nullable|date',
            'base_salary'=> 'nullable|numeric|min:0',
            'skills'     => 'nullable|array',
            'branch_id'  => 'nullable|integer',
        ]);

        $emp = Employee::create(array_merge($data, [
            'company_id' => $user->company_id,
            'branch_id'  => $data['branch_id'] ?? $user->branch_id,
            'status'     => 'active',
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
            'name'       => 'sometimes|string|max:120',
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:80',
            'department' => 'nullable|string|max:80',
            'base_salary'=> 'nullable|numeric|min:0',
            'skills'     => 'nullable|array',
            'status'     => 'nullable|string|in:active,inactive,suspended',
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

        $action = $request->input('action'); // start | complete | assign
        $result = match ($action) {
            'start'    => $engine->start($task),
            'complete' => $engine->complete($task, $request->input('notes', ''), $request->input('actual_minutes')),
            'assign'   => $engine->assign($task, $request->integer('employee_id')),
            default    => $task->update(['status' => $request->input('status', $task->status)]) && $task->fresh(),
        };

        return response()->json(['data' => $result, 'message' => 'تم تحديث المهمة.']);
    }

    public function taskStats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $stats = app(TaskEngine::class)->stats($user->company_id, $user->branch_id);
        return response()->json(['data' => $stats]);
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

    public function storeCommissionRule(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => 'nullable|integer',
            'applies_to'  => 'required|string|in:invoice,work_order,service',
            'rate'        => 'required|numeric|min:0|max:100',
            'min_amount'  => 'nullable|numeric|min:0',
            'is_active'   => 'boolean',
        ]);

        $rule = CommissionRule::updateOrCreate(
            ['company_id' => $user->company_id, 'employee_id' => $data['employee_id'] ?? null, 'applies_to' => $data['applies_to']],
            array_merge($data, ['company_id' => $user->company_id])
        );

        return response()->json(['data' => $rule, 'message' => 'تم حفظ قاعدة العمولة.'], 201);
    }

    public function payCommission(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $c    = app(CommissionService::class)->markPaid($id, $user->id);
        app(AuditLogger::class)->log('commission.paid', Commission::class, $id, ['status' => 'pending'], ['status' => 'paid']);
        return response()->json(['data' => $c, 'message' => 'تم صرف العمولة.']);
    }
}
