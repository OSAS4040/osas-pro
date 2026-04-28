<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Modules\SubscriptionsV2\Models\AuditLog;

final class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $context
     */
    public function log(
        ?int $actorId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?array $context = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id'     => $actorId,
            'action'       => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'before_json'  => $before,
            'after_json'   => $after,
            'context_json' => $context,
        ]);
    }
}
