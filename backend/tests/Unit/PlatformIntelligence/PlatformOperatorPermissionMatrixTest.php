<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use App\Support\PlatformIntelligence\PlatformIntelligenceCapability;
use App\Support\PlatformIntelligence\PlatformOperatorPermissionMatrix;
use PHPUnit\Framework\TestCase;

final class PlatformOperatorPermissionMatrixTest extends TestCase
{
    public function test_each_capability_maps_to_distinct_permission_key(): void
    {
        $keys = [];
        foreach (PlatformIntelligenceCapability::all() as $cap) {
            $keys[] = PlatformOperatorPermissionMatrix::permissionFor($cap);
        }
        $unique = array_unique($keys);
        $this->assertCount(count($keys), $unique);
        foreach ($keys as $k) {
            $this->assertStringStartsWith('platform.intelligence.', $k);
        }
    }
}
