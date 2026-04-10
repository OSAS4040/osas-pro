<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Validates booking slots against optional weekly JSON on Branch.opening_hours.
 */
final class BranchOpeningHours
{
    /**
     * @param  array<string, mixed>|null  $schedule
     */
    public static function slotAllowed(?array $schedule, Carbon $start, Carbon $end): bool
    {
        if ($schedule === null || $schedule === []) {
            return true;
        }

        if (! self::scheduleHasIntervals($schedule)) {
            return true;
        }

        if ($start->toDateString() !== $end->toDateString()) {
            return false;
        }

        $dayKey = self::dayKey($start);
        $daySlots = $schedule[$dayKey] ?? null;
        if (! is_array($daySlots) || $daySlots === []) {
            return false;
        }

        $startMin = $start->hour * 60 + $start->minute;
        $endMin = $end->hour * 60 + $end->minute;

        foreach ($daySlots as $pair) {
            if (! is_array($pair) || count($pair) < 2) {
                continue;
            }
            $open = self::toMinutes((string) $pair[0]);
            $close = self::toMinutes((string) $pair[1]);
            if ($open === null || $close === null || $close <= $open) {
                continue;
            }
            if ($startMin >= $open && $endMin <= $close) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private static function scheduleHasIntervals(array $schedule): bool
    {
        foreach ($schedule as $v) {
            if (is_array($v) && $v !== []) {
                return true;
            }
        }

        return false;
    }

    private static function dayKey(Carbon $dt): string
    {
        return match ((int) $dt->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => 'mon',
        };
    }

    private static function toMinutes(string $hhmm): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
            return null;
        }

        return $h * 60 + $min;
    }
}
