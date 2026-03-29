<?php

namespace App\Services;

use App\Models\AlertNotification;
use App\Models\AlertRule;

class AlertService
{
    /**
     * Fire an alert — checks if rule exists & active, then creates notification.
     */
    public function fire(
        int $companyId,
        string $code,
        string $message,
        string $severity = 'info',
        string|null $subjectType = null,
        int|null $subjectId = null,
        array $meta = [],
        int|null $userId = null,
    ): AlertNotification {
        AlertNotification::create([
            'company_id'   => $companyId,
            'code'         => $code,
            'severity'     => $severity,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'message'      => $message,
            'meta'         => $meta ?: null,
            'is_read'      => false,
            'user_id'      => $userId,
        ]);

        // Also fire to additional recipients from rule if configured
        $rule = AlertRule::where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if ($rule && !empty($rule->recipients)) {
            foreach ($rule->recipients as $recipientId) {
                if ($recipientId == $userId) continue;
                AlertNotification::create([
                    'company_id'   => $companyId,
                    'code'         => $code,
                    'severity'     => $severity,
                    'subject_type' => $subjectType,
                    'subject_id'   => $subjectId,
                    'message'      => $message,
                    'meta'         => $meta ?: null,
                    'is_read'      => false,
                    'user_id'      => $recipientId,
                ]);
            }
        }

        return AlertNotification::where('company_id', $companyId)
            ->where('code', $code)
            ->latest()
            ->first();
    }

    public function unreadCount(int $companyId, int $userId): int
    {
        return AlertNotification::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function markRead(int $companyId, int $userId, array $ids = []): int
    {
        $q = AlertNotification::where('company_id', $companyId)->where('user_id', $userId);
        if ($ids) $q->whereIn('id', $ids);
        return $q->update(['is_read' => true, 'read_at' => now()]);
    }
}
