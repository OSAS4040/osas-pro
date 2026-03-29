<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowService
{
    public function request(
        int $companyId,
        string $subjectType,
        int $subjectId,
        int $requestedBy,
        string $policyCode = '',
        string $note = '',
        array $meta = [],
        int|null $assignedApprover = null,
    ): ApprovalWorkflow {
        return DB::transaction(function () use (
            $companyId, $subjectType, $subjectId, $requestedBy,
            $policyCode, $note, $meta, $assignedApprover
        ) {
            // Cancel any existing pending workflow for same subject
            ApprovalWorkflow::where('company_id', $companyId)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            return ApprovalWorkflow::create([
                'company_id'       => $companyId,
                'subject_type'     => $subjectType,
                'subject_id'       => $subjectId,
                'policy_code'      => $policyCode,
                'status'           => 'pending',
                'requested_by'     => $requestedBy,
                'assigned_approver'=> $assignedApprover,
                'requester_note'   => $note,
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

    private function resolve(int $id, int $resolvedBy, string $status, string $note): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::findOrFail($id);

        if ($workflow->status !== 'pending') {
            throw new \DomainException("Workflow #{$id} is already {$workflow->status}.");
        }

        $workflow->update([
            'status'        => $status,
            'resolved_by'   => $resolvedBy,
            'resolved_at'   => now(),
            'resolver_note' => $note,
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
