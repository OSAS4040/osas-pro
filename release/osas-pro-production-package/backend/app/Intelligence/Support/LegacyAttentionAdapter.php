<?php

declare(strict_types=1);

namespace App\Intelligence\Support;

/**
 * Maps unified attention items to legacy profile panel rows (read-only UI bridge).
 */
final class LegacyAttentionAdapter
{
    /** @var array<string, string> */
    private const SEVERITY_MAP = [
        'low' => 'watch',
        'medium' => 'important',
        'high' => 'critical',
    ];

    /** @var array<string, string> */
    private const MESSAGE_FALLBACK = [
        'intelligence.attention.company_not_fully_active' => 'Company is inactive or not in active status.',
        'intelligence.attention.open_tickets_overdue' => 'There are overdue support tickets.',
        'intelligence.attention.open_support_tickets' => 'There are open support tickets.',
        'intelligence.attention.no_recent_activity' => 'No recent operational activity detected.',
        'intelligence.attention.low_operational_pulse' => 'Activity is lower than usual for the selected window.',
        'intelligence.attention.customer_open_tickets' => 'Customer has open support tickets.',
        'intelligence.attention.overdue_invoices' => 'Customer has overdue unpaid invoices.',
        'intelligence.attention.customer_inactive' => 'Little or no recent activity for this customer.',
        'intelligence.attention.feed_high_attention' => 'Many feed items are flagged as important or critical.',
        'intelligence.attention.feed_attention_mix' => 'Some feed items need attention.',
        'intelligence.attention.feed_empty_window' => 'No operational items in this window.',
        'intelligence.attention.feed_invoices_without_payments' => 'Invoices in window with no matching payments.',
        'intelligence.attention.feed_watch' => 'Operational feed is in a watch state.',
        'intelligence.attention.work_orders_empty_window' => 'No work orders in this period.',
        'intelligence.attention.work_orders_many_on_hold' => 'Many work orders are on hold.',
        'intelligence.attention.work_orders_watch' => 'Work order mix suggests a watch state.',
    ];

    /**
     * @param  list<array<string, mixed>>  $unified
     * @return list<array{code: string, severity: string, message: string}>
     */
    public static function toLegacy(array $unified): array
    {
        $out = [];
        foreach ($unified as $row) {
            $sev = (string) ($row['severity'] ?? 'low');
            $out[] = [
                'code' => (string) ($row['type'] ?? 'unknown'),
                'severity' => self::SEVERITY_MAP[$sev] ?? 'watch',
                'message' => self::MESSAGE_FALLBACK[(string) ($row['message_key'] ?? '')] ?? (string) ($row['message_key'] ?? ''),
            ];
        }

        return $out;
    }
}
