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
        ];
    }
}
