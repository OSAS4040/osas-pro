<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowService
{
    private array $transitionMap = [
        'pending' => ['approved', 'rejected', 'cancelled'],
        'approved' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public function ensurePendingWorkflow(
        int $companyId,
        string $subjectType,
        int $subjectId,
        int $requestedBy,
        ?int $assignedApprover = null,
        string $policyCode = '',
        string $note = '',
        array $meta = [],
        int $totalSteps = 1
    ): ApprovalWorkflow {
        $existing = ApprovalWorkflow::where('company_id', $companyId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->request(
            $companyId,
            $subjectType,
            $subjectId,
            $requestedBy,
            $policyCode,
            $note,
            $meta,
            $assignedApprover,
            $totalSteps
        );
    }

    public function request(
        int $companyId,
        string $subjectType,
        int $subjectId,
        int $requestedBy,
        string $policyCode = '',
        string $note = '',
        array $meta = [],
        int|null $assignedApprover = null,
        int $totalSteps = 1,
    ): ApprovalWorkflow {
        return DB::transaction(function () use (
            $companyId, $subjectType, $subjectId, $requestedBy,
            $policyCode, $note, $meta, $assignedApprover, $totalSteps
        ) {
            // Cancel any existing pending workflow for same subject
            ApprovalWorkflow::where('company_id', $companyId)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $traceId = app()->bound('trace_id') ? (string) app('trace_id') : (string) Str::uuid();

            return ApprovalWorkflow::create([
                'company_id'       => $companyId,
                'subject_type'     => $subjectType,
                'subject_id'       => $subjectId,
                'policy_code'      => $policyCode,
                'status'           => 'pending',
                'current_step'     => 1,
                'total_steps'      => max(1, $totalSteps),
                'requested_by'     => $requestedBy,
                'assigned_approver'=> $assignedApprover,
                'requester_note'   => $note,
                'trace_id'         => $traceId,
                'meta'             => $meta ?: null,
            ]);
        });
    }

    public function approve(int $workflowId, int $resolvedBy, string $note = ''): ApprovalWorkflow
    {
        return $this->resolve($workflowId, $resolvedBy, 'approved', $note);
    }

    public function reject(int $workflowId, int $resolvedBy, string $note = ''): ApprovalWorkflow
    {
        return $this->resolve($workflowId, $resolvedBy, 'rejected', $note);
    }

    public function transitionBySubject(
        int $companyId,
        string $subjectType,
        int $subjectId,
        string $targetStatus,
        int $actedBy,
        string $note = ''
    ): ApprovalWorkflow {
        $workflow = ApprovalWorkflow::where('company_id', $companyId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest('id')
            ->first();

        if (! $workflow) {
            throw new \DomainException("Approval workflow for {$subjectType}#{$subjectId} was not found.");
        }

        return $this->resolve((int) $workflow->id, $actedBy, $targetStatus, $note);
    }

    private function resolve(int $id, int $resolvedBy, string $status, string $note): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::findOrFail($id);

        if (! in_array($status, $this->transitionMap[$workflow->status] ?? [], true)) {
            throw new \DomainException("Workflow status transition {$workflow->status} -> {$status} is not allowed.");
        }

        $oldStatus = (string) $workflow->status;
        $actedAt = now();
        $traceId = app()->bound('trace_id') ? (string) app('trace_id') : (string) Str::uuid();

        $workflow->update([
            'status'        => $status,
            'resolved_by'   => $resolvedBy,
            'resolved_at'   => $actedAt,
            'acted_at'      => $actedAt,
            'resolver_note' => $note,
            'trace_id'      => $traceId,
            'current_step'  => min((int) $workflow->total_steps, (int) $workflow->current_step + 1),
        ]);

        DB::table('approval_workflow_actions')->insert([
            'workflow_id' => $workflow->id,
            'company_id' => $workflow->company_id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'approval_step' => (int) $workflow->current_step,
            'acted_by' => $resolvedBy,
            'approval_note' => $note ?: null,
            'acted_at' => $actedAt,
            'trace_id' => $traceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workflow->fresh();
    }

    public function pendingFor(int $companyId, string $subjectType = null): \Illuminate\Database\Eloquent\Collection
    {
        $q = ApprovalWorkflow::where('company_id', $companyId)->where('status', 'pending');
        if ($subjectType) $q->where('subject_type', $subjectType);
        return $q->with('requester:id,name,email')->latest()->get();
    }
}
