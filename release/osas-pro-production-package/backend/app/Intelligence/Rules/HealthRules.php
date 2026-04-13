<?php

declare(strict_types=1);

namespace App\Intelligence\Rules;

use App\Intelligence\DTO\EntityHealthDto;
use Carbon\CarbonImmutable;

/**
 * Rule-based health_status (healthy | watch | at_risk | inactive).
 */
final class HealthRules
{
    private const INACTIVE_DAYS = 45;

    private const WATCH_DAYS = 14;

    /**
     * @param  array<string, mixed>  $facts
     */
    public static function companyHealth(
        bool $companyOperational,
        ?CarbonImmutable $lastActivityAt,
        int $openTickets,
        int $overdueTickets,
        int $workOrdersInPeriod,
        int $customersCount,
    ): EntityHealthDto {
        $issues = $openTickets > 0 || $overdueTickets > 0 || ! $companyOperational;

        $inactive = $lastActivityAt === null || $lastActivityAt->lessThan(CarbonImmutable::now()->subDays(self::INACTIVE_DAYS));
        $lowActivity = $lastActivityAt !== null
            && $lastActivityAt->lessThan(CarbonImmutable::now()->subDays(self::WATCH_DAYS))
            && ! $inactive;

        $status = 'healthy';
        if ($issues) {
            $status = 'at_risk';
        } elseif ($inactive) {
            $status = 'inactive';
        } elseif ($lowActivity || ($customersCount > 0 && $workOrdersInPeriod < 2)) {
            $status = 'watch';
        }

        return new EntityHealthDto($status);
    }

    /**
     * Customer hub: tickets + payment stress + inactivity.
     */
    public static function customerHealth(
        string $paymentBehavior,
        int $ticketsOpen,
        bool $inactivityFlag,
        string $activityLevel,
    ): EntityHealthDto {
        if ($paymentBehavior === 'risky') {
            return new EntityHealthDto('at_risk');
        }
        if ($ticketsOpen > 0 || $paymentBehavior === 'delayed') {
            return new EntityHealthDto('watch');
        }
        if ($inactivityFlag || $activityLevel === 'none') {
            return new EntityHealthDto('inactive');
        }
        if ($activityLevel === 'low') {
            return new EntityHealthDto('watch');
        }

        return new EntityHealthDto('healthy');
    }

    /**
     * Operations feed window (summary-only, no entity drill-down).
     *
     * @param  array<string, int>  $summary
     */
    public static function operationsFeedHealth(array $summary): EntityHealthDto
    {
        $attention = (int) ($summary['attention_count'] ?? 0);
        $total = (int) ($summary['total_items_in_window'] ?? 0);
        if ($attention > 0 && $total > 0 && ($attention / max(1, $total)) >= 0.25) {
            return new EntityHealthDto('at_risk');
        }
        if ($attention > 0) {
            return new EntityHealthDto('watch');
        }
        if ($total === 0) {
            return new EntityHealthDto('inactive');
        }

        return new EntityHealthDto('healthy');
    }

    /**
     * @param  list<array<string, mixed>>  $rows  work order summary rows {status, count}
     */
    public static function workOrderSummaryHealth(array $rows, int $totalWorkOrders): EntityHealthDto
    {
        if ($totalWorkOrders === 0) {
            return new EntityHealthDto('inactive');
        }
        $blocked = 0;
        foreach ($rows as $r) {
            $st = strtolower((string) ($r['status'] ?? ''));
            if (str_contains($st, 'cancel') || str_contains($st, 'hold')) {
                $blocked += (int) ($r['count'] ?? 0);
            }
        }
        if ($blocked > 0 && ($blocked / max(1, $totalWorkOrders)) > 0.5) {
            return new EntityHealthDto('watch');
        }

        return new EntityHealthDto('healthy');
    }
}
