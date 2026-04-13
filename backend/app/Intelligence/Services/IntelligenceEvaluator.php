<?php

declare(strict_types=1);

namespace App\Intelligence\Services;

use App\Intelligence\Rules\ActivityRules;
use App\Intelligence\Rules\HealthRules;
use App\Intelligence\Rules\PaymentBehaviorRules;
use Carbon\CarbonImmutable;

/**
 * Orchestrates rule modules into a single indicator bundle (no AI).
 */
final class IntelligenceEvaluator
{
    /**
     * @param  array<string, mixed>  $raw  Company profile query row
     * @param  array<string, mixed>  $summary
     * @return array{health_status: string, indicators: array<string, mixed>}
     */
    public function evaluateCompany(
        bool $companyOperational,
        array $raw,
        array $summary,
        bool $includeFinancial,
    ): array {
        $lastAt = isset($summary['last_activity_at']) && is_string($summary['last_activity_at'])
            ? CarbonImmutable::parse($summary['last_activity_at'])
            : null;

        $health = HealthRules::companyHealth(
            $companyOperational,
            $lastAt,
            (int) $raw['open_tickets'],
            (int) $raw['tickets_overdue'],
            (int) $raw['work_orders_in_period'],
            (int) $raw['customers_count'],
        );

        $window = ActivityRules::companyWindow(
            (int) $raw['work_orders_in_period'],
            (int) $raw['customers_count'],
        );

        $payment = 'unknown';
        if ($includeFinancial) {
            $inv = (int) $raw['invoices_in_period'];
            if ($inv === 0) {
                $payment = 'unknown';
            } else {
                $payment = 'good';
            }
        }

        return [
            'health_status' => $health->healthStatus,
            'indicators' => [
                'activity_level' => $window['activity_level'],
                'engagement_level' => $window['engagement_level'],
                'payment_behavior' => $payment,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{health_status: string, indicators: array<string, mixed>}
     */
    public function evaluateCustomer(array $raw, bool $includeFinancial, ?string $lastActivityIso): array
    {
        $lastAt = $lastActivityIso !== null && $lastActivityIso !== ''
            ? CarbonImmutable::parse($lastActivityIso)
            : null;

        $act = ActivityRules::customerWindow(
            (int) $raw['work_orders_in_window'],
            (int) $raw['invoices_in_window'],
            $lastAt,
        );

        $payment = PaymentBehaviorRules::fromInvoiceSignals(
            (int) $raw['overdue_invoices'],
            (int) $raw['stale_unpaid_invoices'],
            $includeFinancial,
        );

        $health = HealthRules::customerHealth(
            $payment,
            (int) $raw['tickets_open'],
            $act['inactivity_flag'],
            $act['activity_level'],
        );

        return [
            'health_status' => $health->healthStatus,
            'indicators' => [
                'activity_level' => $act['activity_level'],
                'engagement_level' => $act['engagement_level'],
                'payment_behavior' => $payment,
            ],
            'inactivity_flag' => $act['inactivity_flag'],
        ];
    }
}
