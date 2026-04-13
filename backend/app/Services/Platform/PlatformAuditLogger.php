<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class PlatformAuditLogger
{
    public function record(
        ?User $user,
        string $action,
        Request $request,
        array $metadata = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): void {
        if (! Schema::hasTable('platform_audit_logs')) {
            return;
        }

        try {
            PlatformAuditLog::query()->create([
                'user_id'      => $user?->id,
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'ip_address'   => $request->ip(),
                'user_agent'   => mb_substr((string) $request->userAgent(), 0, 512),
                'metadata'     => $metadata === [] ? null : $metadata,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
