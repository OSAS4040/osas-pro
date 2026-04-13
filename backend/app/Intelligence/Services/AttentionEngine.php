<?php

declare(strict_types=1);

namespace App\Intelligence\Services;

use App\Intelligence\DTO\AttentionItemDto;
use App\Models\Company;
use Carbon\CarbonImmutable;

/**
 * Builds unified attention items (rule-based).
 */
final class AttentionEngine
{
    private function nowIso(): string
    {
        return CarbonImmutable::now()->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $raw  CompanyProfileQuery row set
     * @return list<AttentionItemDto>
     */
    public function forCompany(Company $company, array $raw, string $healthStatus): array
    {
        $items = [];
        $ts = $this->nowIso();

        if (! $company->is_active || ($company->status instanceof \BackedEnum && $company->status->value !== 'active')) {
            $items[] = new AttentionItemDto(
                type: 'company_inactive',
                severity: 'high',
                messageKey: 'intelligence.attention.company_not_fully_active',
                relatedEntity: 'company',
                createdAt: $ts,
            );
        }
        if ((int) $raw['tickets_overdue'] > 0) {
            $items[] = new AttentionItemDto(
                type: 'open_tickets_overdue',
                severity: 'high',
                messageKey: 'intelligence.attention.open_tickets_overdue',
                relatedEntity: 'company',
                createdAt: $ts,
            );
        } elseif ((int) $raw['open_tickets'] > 0) {
            $items[] = new AttentionItemDto(
                type: 'open_support_tickets',
                severity: 'medium',
                messageKey: 'intelligence.attention.open_support_tickets',
                relatedEntity: 'company',
                createdAt: $ts,
            );
        }
        if ($healthStatus === 'inactive') {
            $items[] = new AttentionItemDto(
                type: 'no_recent_activity',
                severity: 'medium',
                messageKey: 'intelligence.attention.no_recent_activity',
                relatedEntity: 'company',
                createdAt: $ts,
            );
        } elseif ($healthStatus === 'watch') {
            $items[] = new AttentionItemDto(
                type: 'inactivity_over_threshold',
                severity: 'low',
                messageKey: 'intelligence.attention.low_operational_pulse',
                relatedEntity: 'company',
                createdAt: $ts,
            );
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array{activity_level?: string, engagement_level?: string, payment_behavior?: string}  $indicators
     * @return list<AttentionItemDto>
     */
    public function forCustomer(array $raw, array $indicators, bool $inactivityFlag, bool $includeFinancial): array
    {
        $items = [];
        $ts = $this->nowIso();

        if ((int) $raw['tickets_open'] > 0) {
            $items[] = new AttentionItemDto(
                type: 'open_support_tickets',
                severity: 'medium',
                messageKey: 'intelligence.attention.customer_open_tickets',
                relatedEntity: 'customer',
                createdAt: $ts,
            );
        }
        if ($includeFinancial && (int) $raw['overdue_invoices'] > 0) {
            $items[] = new AttentionItemDto(
                type: 'payment_delinquency',
                severity: 'high',
                messageKey: 'intelligence.attention.overdue_invoices',
                relatedEntity: 'customer',
                createdAt: $ts,
            );
        }
        if (($indicators['activity_level'] ?? '') === 'none' || $inactivityFlag) {
            $items[] = new AttentionItemDto(
                type: 'no_recent_activity',
                severity: 'medium',
                messageKey: 'intelligence.attention.customer_inactive',
                relatedEntity: 'customer',
                createdAt: $ts,
            );
        }

        return $items;
    }

    /**
     * @param  array<string, int>  $summary  Global feed summary counts
     * @return list<AttentionItemDto>
     */
    public function forOperationsFeed(array $summary, string $healthStatus, bool $financialIncluded): array
    {
        $items = [];
        $ts = $this->nowIso();
        $attention = (int) ($summary['attention_count'] ?? 0);
        $total = (int) ($summary['total_items_in_window'] ?? 0);

        if ($attention >= 5) {
            $items[] = new AttentionItemDto(
                type: 'high_unresolved_operations',
                severity: 'high',
                messageKey: 'intelligence.attention.feed_high_attention',
                relatedEntity: 'operations',
                createdAt: $ts,
            );
        } elseif ($attention > 0) {
            $items[] = new AttentionItemDto(
                type: 'high_unresolved_operations',
                severity: 'medium',
                messageKey: 'intelligence.attention.feed_attention_mix',
                relatedEntity: 'operations',
                createdAt: $ts,
            );
        }
        if ($total === 0) {
            $items[] = new AttentionItemDto(
                type: 'no_recent_activity',
                severity: 'low',
                messageKey: 'intelligence.attention.feed_empty_window',
                relatedEntity: 'operations',
                createdAt: $ts,
            );
        }
        if ($financialIncluded && (int) ($summary['payments_count'] ?? 0) === 0 && (int) ($summary['invoices_count'] ?? 0) > 5) {
            $items[] = new AttentionItemDto(
                type: 'multiple_failed_payments',
                severity: 'low',
                messageKey: 'intelligence.attention.feed_invoices_without_payments',
                relatedEntity: 'operations',
                createdAt: $ts,
            );
        }

        if ($items === [] && $healthStatus === 'watch') {
            $items[] = new AttentionItemDto(
                type: 'inactivity_over_threshold',
                severity: 'low',
                messageKey: 'intelligence.attention.feed_watch',
                relatedEntity: 'operations',
                createdAt: $ts,
            );
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<AttentionItemDto>
     */
    public function forWorkOrderSummary(array $rows, int $totalWorkOrders, string $healthStatus): array
    {
        $items = [];
        $ts = $this->nowIso();
        if ($totalWorkOrders === 0) {
            $items[] = new AttentionItemDto(
                type: 'no_recent_activity',
                severity: 'low',
                messageKey: 'intelligence.attention.work_orders_empty_window',
                relatedEntity: 'report',
                createdAt: $ts,
            );

            return $items;
        }
        $hold = 0;
        foreach ($rows as $r) {
            $st = strtolower((string) ($r['status'] ?? ''));
            if (str_contains($st, 'hold')) {
                $hold += (int) ($r['count'] ?? 0);
            }
        }
        if ($hold > 0 && $hold >= max(1, (int) ($totalWorkOrders / 3))) {
            $items[] = new AttentionItemDto(
                type: 'high_unresolved_operations',
                severity: 'medium',
                messageKey: 'intelligence.attention.work_orders_many_on_hold',
                relatedEntity: 'report',
                createdAt: $ts,
            );
        } elseif ($healthStatus === 'watch') {
            $items[] = new AttentionItemDto(
                type: 'inactivity_over_threshold',
                severity: 'low',
                messageKey: 'intelligence.attention.work_orders_watch',
                relatedEntity: 'report',
                createdAt: $ts,
            );
        }

        return $items;
    }
}
