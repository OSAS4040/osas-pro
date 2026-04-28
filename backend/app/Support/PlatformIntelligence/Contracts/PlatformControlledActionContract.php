<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Contracts;

/**
 * Official read contract for a persisted controlled action (no parallel DTO shapes).
 */
final readonly class PlatformControlledActionContract
{
    /**
     * @param  non-empty-string  $action_id
     * @param  non-empty-string  $incident_key
     * @param  non-empty-string  $action_type
     */
    public function __construct(
        public string $action_id,
        public string $incident_key,
        public string $action_type,
        public string $action_summary,
        public string $actor,
        public string $created_at,
        public string $status,
        public ?string $assigned_owner,
        public bool $follow_up_required,
        public ?string $scheduled_for,
        public ?string $linked_decision_id,
        public ?string $linked_notes,
        public ?string $external_reference,
        public ?string $completion_reason,
        public ?string $canceled_reason,
    ) {}
}
