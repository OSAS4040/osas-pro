<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\ControlledActions;

use App\Support\PlatformIntelligence\ControlledActions\ControlledActionAllowlist;
use PHPUnit\Framework\TestCase;

final class ControlledActionAllowlistTest extends TestCase
{
    public function test_allowlist_contains_exactly_eight_operations(): void
    {
        $all = ControlledActionAllowlist::allOperations();
        $this->assertCount(8, $all);
        $this->assertContains(ControlledActionAllowlist::CREATE_FOLLOW_UP, $all);
        $this->assertContains(ControlledActionAllowlist::MARK_FOLLOW_UP_COMPLETED, $all);
    }

    public function test_unknown_operation_rejected(): void
    {
        $this->assertFalse(ControlledActionAllowlist::isAllowedOperation('execute_arbitrary'));
        $this->assertFalse(ControlledActionAllowlist::isAllowedOperation('adjust_ledger'));
    }
}
