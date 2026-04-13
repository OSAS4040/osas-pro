<?php

declare(strict_types=1);

namespace App\Relationships\Operational;

use App\Reporting\ReportingContext;
use Illuminate\Support\Facades\DB;

/**
 * Read-only relationship slices for operational hubs (no graph engine).
 */
final class RelationshipQuery
{
    /**
     * Latest vehicles linked to a customer (tenant + optional branch scope).
     *
     * @param  list<int>|null  $branchIds
     * @return list<array{vehicle_id: int, plate_number: string, make: string|null, model: string|null, year: int|null}>
     */
    public function topVehiclesForCustomer(
        ReportingContext $context,
        int $companyId,
        int $customerId,
        int $limit = 5,
    ): array {
        $branchIds = $context->branchIds;
        $q = DB::table('vehicles as v')
            ->where('v.company_id', $companyId)
            ->where('v.customer_id', $customerId)
            ->whereNull('v.deleted_at')
            ->orderByDesc('v.updated_at')
            ->orderByDesc('v.id')
            ->limit($limit);
        if ($branchIds !== null) {
            $q->whereIn('v.branch_id', $branchIds);
        }

        return $q->get(['v.id', 'v.plate_number', 'v.make', 'v.model', 'v.year'])
            ->map(static function ($r): array {
                return [
                    'vehicle_id' => (int) $r->id,
                    'plate_number' => (string) ($r->plate_number ?? ''),
                    'make' => $r->make !== null ? (string) $r->make : null,
                    'model' => $r->model !== null ? (string) $r->model : null,
                    'year' => $r->year !== null ? (int) $r->year : null,
                ];
            })->all();
    }
}
