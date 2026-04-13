<?php

declare(strict_types=1);

namespace App\Relationships\Operational;

use App\Models\User;

/**
 * Permission-aware relationship payloads for company/customer hubs (read-only).
 */
final class RelationshipMapAssembler
{
    /**
     * @param  array<string, mixed>  $summary  Company profile summary (includes counts).
     * @param  array{top_customers: list<array<string, mixed>>, top_users: list<array<string, mixed>>, branches_summary: list<array<string, mixed>>}  $slice
     * @return array<string, mixed>
     */
    public function forCompanyProfile(User $actor, int $companyId, array $summary, array $slice): array
    {
        $topCustomers = $actor->hasPermission('customers.view') ? $slice['top_customers'] : [];
        $topUsers = $actor->hasPermission('users.view') ? $slice['top_users'] : [];
        $branchVisible = $actor->hasPermission('branches.view')
            || $actor->hasPermission('work_orders.view')
            || $actor->hasPermission('reports.view');
        $branchesSummary = $branchVisible ? $slice['branches_summary'] : [];

        return [
            'top_customers' => $topCustomers,
            'top_users' => $topUsers,
            'branches_summary' => $branchesSummary,
            'operational_map' => [
                'version' => 1,
                'scope' => 'company',
                'company_id' => $companyId,
                'counts' => [
                    'customers' => (int) $summary['customers_count'],
                    'users' => (int) $summary['users_count'],
                    'branches' => (int) $summary['branches_count'],
                ],
                'visibility' => [
                    'customer_profiles' => $actor->hasPermission('customers.view'),
                    'user_directory' => $actor->hasPermission('users.view'),
                    'branch_directory' => $branchVisible,
                    'branch_settings' => $actor->hasPermission('branches.view'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerOperationalMap(User $actor, int $customerId, int $vehiclesCount, int $assignedUsersCount): array
    {
        return [
            'version' => 1,
            'scope' => 'customer',
            'customer_id' => $customerId,
            'counts' => [
                'vehicles' => $vehiclesCount,
                'assigned_users' => $assignedUsersCount,
            ],
            'visibility' => [
                'vehicle_assets' => $actor->hasPermission('vehicles.view'),
                'user_directory' => $actor->hasPermission('users.view'),
            ],
        ];
    }
}
