<?php

declare(strict_types=1);

namespace App\Customers\Profile;

use App\Actions\Reporting\ResolveReportingContextAction;
use App\Intelligence\Assemblers\CustomerIntelligenceAssembler;
use App\Models\Customer;
use App\Models\User;
use App\Relationships\Operational\RelationshipMapAssembler;
use App\Relationships\Operational\RelationshipQuery;
use Carbon\CarbonImmutable;

/**
 * Customer operational hub (read-only) — intelligence from unified layer.
 */
final class CustomerProfileAssembler
{
    public function __construct(
        private readonly ResolveReportingContextAction $resolveContext,
        private readonly CustomerProfileQuery $query,
        private readonly RelationshipQuery $relationshipQuery,
        private readonly RelationshipMapAssembler $relationshipMapAssembler,
        private readonly CustomerIntelligenceAssembler $customerIntelligenceAssembler,
    ) {}

    public function assemble(Customer $customer, User $actor, bool $includeFinancial): CustomerProfileDto
    {
        $context = ($this->resolveContext)($actor, []);
        $companyId = (int) $customer->company_id;
        $customerId = (int) $customer->id;
        $now = CarbonImmutable::now();
        $windowStart = $now->subDays(90)->startOfDay();

        $raw = $this->query->fetch($context, $companyId, $customerId, $windowStart, $now->endOfDay(), $includeFinancial);

        $customerBlock = [
            'id' => $customerId,
            'name' => (string) $customer->name,
            'type' => (string) $customer->type,
            'created_at' => $customer->created_at?->toIso8601String(),
        ];

        $summary = [
            'work_orders_count' => (int) $raw['work_orders_count'],
            'invoices_count' => $raw['invoices_count'],
            'payments_count' => $raw['payments_count'],
            'tickets_open' => (int) $raw['tickets_open'],
            'last_activity_at' => $raw['last_activity_at'],
        ];

        $activitySnapshot = [
            'last_work_order' => $raw['last_work_order'],
            'last_invoice' => $raw['last_invoice'],
            'last_payment' => $raw['last_payment'],
            'last_ticket' => $raw['last_ticket'],
        ];

        $intel = $this->customerIntelligenceAssembler->assemble($raw, $summary, $includeFinancial);

        /** @var list<array<string, mixed>> $assignedUsers */
        $assignedUsers = $raw['assigned_users'];
        $assignedUsersCount = count($assignedUsers);
        $assignedUsersOut = $actor->hasPermission('users.view') ? $assignedUsers : [];

        $topVehicles = $this->relationshipQuery->topVehiclesForCustomer($context, $companyId, $customerId);
        if (! $actor->hasPermission('vehicles.view')) {
            $topVehicles = [];
        }

        $relationships = [
            'vehicles_count' => (int) $raw['vehicles_count'],
            'branches' => $raw['branches'],
            'assigned_users' => $assignedUsersOut,
            'top_vehicles' => $topVehicles,
            'operational_map' => $this->relationshipMapAssembler->customerOperationalMap(
                $actor,
                $customerId,
                (int) $raw['vehicles_count'],
                $assignedUsersCount,
            ),
        ];

        return new CustomerProfileDto(
            customer: $customerBlock,
            summary: $summary,
            activitySnapshot: $activitySnapshot,
            behaviorIndicators: $intel['behavior_indicators'],
            relationships: $relationships,
            attentionItems: $intel['attention_items_legacy'],
            intelligence: $intel['intelligence'],
        );
    }
}
