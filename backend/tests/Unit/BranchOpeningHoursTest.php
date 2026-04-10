<?php

namespace Tests\Unit;

use App\Support\BranchOpeningHours;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BranchOpeningHoursTest extends TestCase
{
    public function test_null_schedule_allows_any_slot(): void
    {
        $start = Carbon::parse('2026-04-06 22:00:00');
        $end   = Carbon::parse('2026-04-06 23:00:00');
        $this->assertTrue(BranchOpeningHours::slotAllowed(null, $start, $end));
    }

    public function test_empty_schedule_allows_any_slot(): void
    {
        $start = Carbon::parse('2026-04-06 10:00:00');
        $end   = Carbon::parse('2026-04-06 11:00:00');
        $this->assertTrue(BranchOpeningHours::slotAllowed([], $start, $end));
    }

    public function test_monday_window_allows_inside(): void
    {
        $schedule = ['mon' => [['08:00', '18:00']]];
        $start = Carbon::parse('2026-01-05 10:00:00'); // Monday
        $end   = Carbon::parse('2026-01-05 11:00:00');
        $this->assertTrue(BranchOpeningHours::slotAllowed($schedule, $start, $end));
    }

    public function test_monday_window_rejects_outside(): void
    {
        $schedule = ['mon' => [['08:00', '18:00']]];
        $start = Carbon::parse('2026-01-05 19:00:00');
        $end   = Carbon::parse('2026-01-05 20:00:00');
        $this->assertFalse(BranchOpeningHours::slotAllowed($schedule, $start, $end));
    }

    public function test_closed_day_rejects(): void
    {
        $schedule = ['mon' => [['08:00', '18:00']]];
        $start = Carbon::parse('2026-01-06 10:00:00'); // Tuesday
        $end   = Carbon::parse('2026-01-06 11:00:00');
        $this->assertFalse(BranchOpeningHours::slotAllowed($schedule, $start, $end));
    }
}
