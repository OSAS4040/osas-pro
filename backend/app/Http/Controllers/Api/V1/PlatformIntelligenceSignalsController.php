<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PlatformIntelligence\Scoring\PlatformIntelligenceScoringRulesVersion;
use App\Support\PlatformIntelligence\SignalEngine\PlatformSignalEngine;
use App\Support\PlatformIntelligence\SignalEngine\Serialization\PlatformSignalContractSerializer;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use Illuminate\Http\JsonResponse;
final class PlatformIntelligenceSignalsController extends Controller
{
    public function index(PlatformSignalEngine $engine): JsonResponse
    {
        $trace = new NullPlatformIntelligenceTraceRecorder();
        $signals = $engine->build($trace);

        return response()->json([
            'data' => array_map(
                static fn ($s) => PlatformSignalContractSerializer::toArray($s),
                $signals,
            ),
            'meta' => [
                'scoring_rules_version' => PlatformIntelligenceScoringRulesVersion::VERSION,
                'scoring_rules_release_date' => PlatformIntelligenceScoringRulesVersion::RELEASE_DATE,
                'scoring_rules_changelog' => PlatformIntelligenceScoringRulesVersion::CHANGELOG,
                'overview_snapshot_ttl_seconds' => PlatformIntelligenceScoringRulesVersion::overviewSnapshotTtlSeconds(),
                'signal_order' => PlatformIntelligenceScoringRulesVersion::signalListOrderDescription(),
            ],
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }
}
