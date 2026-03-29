<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(
        string $action,
        string $subjectType,
        int|null $subjectId = null,
        array $before = [],
        array $after  = [],
        int|null $companyId = null,
        int|null $branchId  = null,
        int|null $userId    = null,
    ): AuditLog {
        $user      = Auth::user();
        $companyId = $companyId ?? ($user?->company_id ?? 0);
        $branchId  = $branchId  ?? ($user?->branch_id  ?? null);
        $userId    = $userId    ?? ($user?->id          ?? null);

        return AuditLog::create([
            'company_id'   => $companyId,
            'branch_id'    => $branchId,
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'before'       => $before ?: null,
            'after'        => $after  ?: null,
            'ip_address'   => Request::ip(),
            'user_agent'   => substr(Request::userAgent() ?? '', 0, 300),
            'trace_id'     => app()->has('trace_id') ? app('trace_id') : null,
        ]);
    }

    /**
     * Shorthand for model change logging.
     */
    public function change(object $model, string $action, array $before, array $after): AuditLog
    {
        return $this->log(
            action:      $action,
            subjectType: get_class($model),
            subjectId:   $model->id ?? null,
            before:      $before,
            after:       $after,
        );
    }
}
