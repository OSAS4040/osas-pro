<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PlatformIntelligence\CandidateEngine\PlatformIncidentCandidateEngine;
use App\Support\PlatformIntelligence\CandidateEngine\PlatformIncidentCandidateRulesVersion;
use App\Support\PlatformIntelligence\CandidateSerialization\PlatformIncidentCandidateContractSerializer;
use App\Support\PlatformIntelligence\SignalEngine\PlatformSignalEngine;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use Illuminate\Http\JsonResponse;

/**
 * Read-only incident candidates derived from the official signal engine output only.
 */
final class PlatformIntelligenceIncidentCandidatesController extends Controller
{
    public function index(PlatformSignalEngine $signalEngine, PlatformIncidentCandidateEngine $candidateEngine): JsonResponse
    {
        $trace = new NullPlatformIntelligenceTraceRecorder();
        $signals = $signalEngine->build($trace);
        $candidates = $candidateEngine->buildFromSignals($signals, $trace);

        return response()->json([
            'data' => array_map(
                static fn ($c) => PlatformIncidentCandidateContractSerializer::toArray($c),
                $candidates,
            ),
            'meta' => [
                'candidate_rules_version' => PlatformIncidentCandidateRulesVersion::VERSION,
                'candidate_rules_release_date' => PlatformIncidentCandidateRulesVersion::RELEASE_DATE,
                'candidate_rules_changelog' => PlatformIncidentCandidateRulesVersion::CHANGELOG,
                'candidate_order' => PlatformIncidentCandidateRulesVersion::candidateListOrderDescription(),
            ],
            'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }
}
