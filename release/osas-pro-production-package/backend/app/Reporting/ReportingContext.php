<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\User;

/**
 * Permission-resolved scope for read-only reporting (never trust raw request IDs alone).
 */
final class ReportingContext
{
    /**
     * @param  list<int>|null  $branchIds  null = all branches within the tenant the caller may aggregate.
     */
    public function __construct(
        public readonly User $actor,
        public readonly int $companyId,
        public readonly ?array $branchIds,
        public readonly ?int $customerId,
        public readonly ?int $subjectUserId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toFilterSnapshot(): array
    {
        return [
            'company_id'  => $this->companyId,
            'branch_ids'  => $this->branchIds,
            'customer_id' => $this->customerId,
            'user_id'     => $this->subjectUserId,
            'actor_id'    => $this->actor->id,
        ];
    }
}
