<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Reporting\ReportingContext;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Builds tenant-safe reporting scope from the authenticated user and validated filters.
 */
final class ResolveReportingContextAction
{
    public function __invoke(User $actor, array $filters): ReportingContext
    {
        $companyId = (int) $actor->company_id;
        if ($companyId < 1) {
            throw new HttpResponseException(response()->json([
                'message'  => 'Reporting requires a tenant company context.',
                'trace_id' => app('trace_id'),
            ], 403));
        }

        $hasCrossBranch = $actor->hasPermission('cross_branch_access');

        $requestedBranchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        if ($requestedBranchId !== null && $requestedBranchId < 1) {
            $requestedBranchId = null;
        }

        if ($requestedBranchId !== null) {
            $branchOk = Branch::query()
                ->where('company_id', $companyId)
                ->whereKey($requestedBranchId)
                ->whereNull('deleted_at')
                ->exists();
            if (! $branchOk) {
                throw new HttpResponseException(response()->json([
                    'message'  => 'Invalid branch for this company.',
                    'trace_id' => app('trace_id'),
                ], 422));
            }
            if (! $hasCrossBranch && (int) $actor->branch_id !== $requestedBranchId) {
                throw new HttpResponseException(response()->json([
                    'message'  => 'Cross-branch access is not permitted for your account.',
                    'trace_id' => app('trace_id'),
                ], 403));
            }
        }

        /** @var list<int>|null $branchIds */
        $branchIds = null;
        if ($requestedBranchId !== null) {
            $branchIds = [$requestedBranchId];
        } elseif (! $hasCrossBranch && $actor->branch_id) {
            $branchIds = [(int) $actor->branch_id];
        }

        $customerId = isset($filters['customer_id']) ? (int) $filters['customer_id'] : null;
        if ($customerId !== null && $customerId > 0) {
            $customerOk = Customer::query()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereKey($customerId)
                ->exists();
            if (! $customerOk) {
                throw new HttpResponseException(response()->json([
                    'message'  => 'Invalid customer for this company.',
                    'trace_id' => app('trace_id'),
                ], 422));
            }
        } else {
            $customerId = null;
        }

        $subjectUserId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;
        if ($subjectUserId !== null && $subjectUserId > 0) {
            $userOk = User::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereKey($subjectUserId)
                ->exists();
            if (! $userOk) {
                throw new HttpResponseException(response()->json([
                    'message'  => 'Invalid user for this company.',
                    'trace_id' => app('trace_id'),
                ], 422));
            }
        } else {
            $subjectUserId = null;
        }

        return new ReportingContext(
            actor: $actor,
            companyId: $companyId,
            branchIds: $branchIds,
            customerId: $customerId,
            subjectUserId: $subjectUserId,
        );
    }
}
