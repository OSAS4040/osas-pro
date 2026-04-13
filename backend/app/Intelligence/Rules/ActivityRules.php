<?php

declare(strict_types=1);

namespace App\Intelligence\Rules;

use Carbon\CarbonImmutable;

/**
 * Unified activity_level and engagement_level (rule-based).
 */
final class ActivityRules
{
    private const INACTIVE_DAYS = 45;

    /**
     * Customer window: work orders + invoices touch count in engagement window.
     *
     * @return array{activity_level: string, engagement_level: string, inactivity_flag: bool}
     */
    public static function customerWindow(
        int $workOrdersInWindow,
        int $invoicesInWindow,
        ?CarbonImmutable $lastActivityAt,
    ): array {
        $touch = $workOrdersInWindow + $invoicesInWindow;

        $activityLevel = 'low';
        if ($touch >= 12) {
            $activityLevel = 'high';
        } elseif ($touch >= 4) {
            $activityLevel = 'medium';
        } elseif ($touch === 0) {
            $last = $lastActivityAt;
            if ($last === null || $last->lessThan(CarbonImmutable::now()->subDays(self::INACTIVE_DAYS))) {
                $activityLevel = 'none';
            }
        }

        $engagement = 'disengaged';
        if ($workOrdersInWindow >= 5 || $invoicesInWindow >= 3) {
            $engagement = 'engaged';
        } elseif ($touch >= 2) {
            $engagement = 'neutral';
        } elseif ($touch >= 1) {
            $engagement = 'neutral';
        }

        $inactivity = $lastActivityAt === null || $lastActivityAt->lessThan(CarbonImmutable::now()->subDays(self::INACTIVE_DAYS));

        return [
            'activity_level' => $activityLevel,
            'engagement_level' => $engagement,
            'inactivity_flag' => $inactivity,
        ];
    }

    /**
     * Company profile window (30d) — lightweight touch proxy.
     *
     * @return array{activity_level: string, engagement_level: string}
     */
    public static function companyWindow(int $workOrdersInPeriod, int $customersCount): array
    {
        $touch = $workOrdersInPeriod;
        $activityLevel = 'low';
        if ($touch >= 20) {
            $activityLevel = 'high';
        } elseif ($touch >= 6) {
            $activityLevel = 'medium';
        } elseif ($touch === 0 && $customersCount === 0) {
            $activityLevel = 'none';
        } elseif ($touch === 0) {
            $activityLevel = 'low';
        }

        $engagement = 'disengaged';
        if ($touch >= 15) {
            $engagement = 'engaged';
        } elseif ($touch >= 3) {
            $engagement = 'neutral';
        }

        return [
            'activity_level' => $activityLevel,
            'engagement_level' => $engagement,
        ];
    }
}
