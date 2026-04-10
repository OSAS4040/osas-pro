<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Task;
use Illuminate\Support\Collection;

class TaskEngine
{
    /**
     * Auto-assign a task to the best available employee.
     * Priority: skill match → least active tasks → earliest available.
     */
    public function autoAssign(int $companyId, int $branchId, string $skill = '', array $taskData = []): Task
    {
        $employee = $this->findBestEmployee($companyId, $branchId, $skill);

        $task = Task::create(array_merge($taskData, [
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'assigned_to' => $employee?->id,
            'assigned_by' => auth()->id(),
            'status'      => $employee ? 'assigned' : 'pending',
        ]));

        return $task;
    }

    public function assign(Task $task, int $employeeId): Task
    {
        $task->update([
            'assigned_to' => $employeeId,
            'status'      => 'assigned',
        ]);
        return $task->fresh();
    }

    public function start(Task $task): Task
    {
        $task->update(['status' => 'in_progress', 'started_at' => now()]);
        return $task->fresh();
    }

    public function complete(Task $task, string $notes = '', int $actualMinutes = null): Task
    {
        $task->update([
            'status'           => 'completed',
            'completed_at'     => now(),
            'completion_notes' => $notes,
            'actual_minutes'   => $actualMinutes,
        ]);
        return $task->fresh();
    }

    private function findBestEmployee(int $companyId, int $branchId, string $skill): ?Employee
    {
        $employees = Employee::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->get();

        if ($employees->isEmpty()) return null;

        // Filter by skill if specified
        if ($skill) {
            $skilled = $employees->filter(fn ($e) => in_array($skill, $e->skills ?? []));
            if ($skilled->isNotEmpty()) $employees = $skilled;
        }

        // Pick the one with fewest active tasks
        return $employees->sortBy(function ($emp) {
            return Task::where('assigned_to', $emp->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count();
        })->first();
    }

    public function stats(int $companyId, int $branchId = null): array
    {
        $q = Task::where('company_id', $companyId);
        if ($branchId) $q->where('branch_id', $branchId);

        return [
            'pending'     => (clone $q)->where('status', 'pending')->count(),
            'assigned'    => (clone $q)->where('status', 'assigned')->count(),
            'in_progress' => (clone $q)->where('status', 'in_progress')->count(),
            'completed'   => (clone $q)->where('status', 'completed')->count(),
            'overdue'     => (clone $q)->whereIn('status', ['pending','assigned','in_progress'])
                                ->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'at_risk_sla' => (clone $q)->whereIn('status', ['pending', 'assigned', 'in_progress'])
                ->whereNotNull('due_at')
                ->whereBetween('due_at', [now(), now()->copy()->addHours(24)])
                ->count(),
        ];
    }

    public function smartSummary(int $companyId, ?int $branchId = null): array
    {
        $base = Task::query()->where('company_id', $companyId);
        if ($branchId) {
            $base->where('branch_id', $branchId);
        }

        $openStatuses = ['pending', 'assigned', 'in_progress', 'review'];
        $openCount = (clone $base)->whereIn('status', $openStatuses)->count();
        $overdueCount = (clone $base)->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $slaRiskCount = (clone $base)->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->copy()->addHours(24)])
            ->count();

        $workloadRows = (clone $base)->whereIn('status', $openStatuses)
            ->selectRaw('assigned_to, COUNT(*) as open_tasks, COALESCE(SUM(COALESCE(estimated_minutes, 30)), 0) as load_minutes')
            ->groupBy('assigned_to')
            ->orderByDesc('open_tasks')
            ->get();

        $employeeIds = $workloadRows->pluck('assigned_to')->filter()->values();
        $employeeMap = Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $employeeIds)
            ->pluck('name', 'id');

        $workload = $workloadRows->map(fn ($r) => [
            'employee_id' => $r->assigned_to ? (int) $r->assigned_to : null,
            'employee_name' => $r->assigned_to ? (string) ($employeeMap[$r->assigned_to] ?? 'غير محدد') : 'غير محدد',
            'open_tasks' => (int) $r->open_tasks,
            'load_minutes' => (int) $r->load_minutes,
        ])->values()->all();

        $mostLoaded = collect($workload)->sortByDesc('open_tasks')->first();
        $leastLoaded = collect($workload)->filter(fn ($w) => $w['employee_id'] !== null)->sortBy('open_tasks')->first();

        $recommendations = [];
        if ($overdueCount > 0 && $mostLoaded && $leastLoaded && $mostLoaded['employee_id'] !== $leastLoaded['employee_id']) {
            $recommendations[] = [
                'type' => 'rebalance',
                'severity' => 'high',
                'message' => "إعادة توزيع جزء من المهام من {$mostLoaded['employee_name']} إلى {$leastLoaded['employee_name']} لتقليل التأخير.",
            ];
        }
        if ($slaRiskCount >= 3) {
            $recommendations[] = [
                'type' => 'sla_risk',
                'severity' => 'medium',
                'message' => 'هناك مهام كثيرة مهددة بتجاوز SLA خلال 24 ساعة، يوصى بالتصعيد الفوري.',
            ];
        }
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'stable',
                'severity' => 'info',
                'message' => 'حالة المهام مستقرة حالياً، استمر بالمراقبة اليومية.',
            ];
        }

        return [
            'summary' => [
                'open_tasks' => $openCount,
                'overdue_tasks' => $overdueCount,
                'sla_risk_24h' => $slaRiskCount,
            ],
            'workload' => $workload,
            'recommendations' => $recommendations,
        ];
    }

    public function suggestedAssignees(int $companyId, ?int $branchId = null, string $skill = ''): array
    {
        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->get();

        if ($skill !== '') {
            $matched = $employees->filter(fn ($e) => in_array($skill, $e->skills ?? [], true));
            if ($matched->isNotEmpty()) {
                $employees = $matched->values();
            }
        }

        $openLoadByEmployee = Task::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
            ->selectRaw('assigned_to, COUNT(*) as open_count, COALESCE(SUM(COALESCE(estimated_minutes, 30)), 0) as load_minutes')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        return $employees
            ->map(function ($emp) use ($openLoadByEmployee, $skill) {
                $load = $openLoadByEmployee->get($emp->id);
                $openCount = (int) ($load->open_count ?? 0);
                $loadMinutes = (int) ($load->load_minutes ?? 0);
                $skillBonus = $skill !== '' && in_array($skill, $emp->skills ?? [], true) ? 15 : 0;
                // Lower score is better. Normalize around workload with slight bonus for skill match.
                $score = max(0, (100 - min(90, $openCount * 10 + (int) floor($loadMinutes / 60) * 4)) + $skillBonus);

                return [
                    'employee_id' => $emp->id,
                    'employee_name' => $emp->name,
                    'open_tasks' => $openCount,
                    'load_minutes' => $loadMinutes,
                    'score' => $score,
                    'skills' => $emp->skills ?? [],
                ];
            })
            ->sortByDesc('score')
            ->take(10)
            ->values()
            ->all();
    }
}
