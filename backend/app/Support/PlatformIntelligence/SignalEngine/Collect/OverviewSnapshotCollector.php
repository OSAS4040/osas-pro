<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Collect;

use App\Services\Platform\PlatformAdminOverviewService;

/**
 * Read-only snapshot from the executive overview aggregator (same source as /admin/overview).
 */
final class OverviewSnapshotCollector
{
    public function __construct(
        private readonly PlatformAdminOverviewService $overviewService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return $this->overviewService->build();
    }
}
