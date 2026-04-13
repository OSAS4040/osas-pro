<?php

declare(strict_types=1);

namespace App\Reporting\Queries;

use App\Models\WorkOrder;
use App\Reporting\ReportingContext;
use App\Reporting\ReportingDateRange;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregate over work_orders — no detail row fan-out (query safety).
 */
final class WorkOrderOperationalSummaryQuery
{
    /**
     * @return list<array{status: string, count: int}>
     */
    public function execute(ReportingContext $context, ReportingDateRange $range): array
    {
        $table = (new WorkOrder)->getTable();

        $base = WorkOrder::query()
            ->withoutGlobalScope('tenant')
            ->where($table.'.company_id', $context->companyId)
            ->whereBetween($table.'.created_at', [$range->startsAt, $range->endsAt]);

        if ($context->branchIds !== null) {
            $base->whereIn($table.'.branch_id', $context->branchIds);
        }
        if ($context->customerId !== null) {
            $base->where($table.'.customer_id', $context->customerId);
        }
        if ($context->subjectUserId !== null) {
            $base->where($table.'.created_by_user_id', $context->subjectUserId);
        }

        $rows = $base
            ->select([$table.'.status', DB::raw('COUNT(*) as cnt')])
            ->groupBy($table.'.status')
            ->orderBy($table.'.status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $status = $row->status;
            $out[] = [
                'status' => $status instanceof \BackedEnum ? $status->value : (string) $status,
                'count'  => (int) $row->cnt,
            ];
        }

        return $out;
    }
}
