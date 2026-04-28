<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\SignalEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use App\Support\PlatformIntelligence\Scoring\ConfidenceScorer;
use App\Support\PlatformIntelligence\Scoring\SeverityScorer;
use App\Support\PlatformIntelligence\SignalEngine\Dedupe\SignalDedupeService;
use App\Support\PlatformIntelligence\SignalEngine\Detect\OverviewBasedSignalDetector;
use App\Support\PlatformIntelligence\SignalEngine\Draft\SignalDraft;
use App\Support\PlatformIntelligence\SignalEngine\Explainability\SignalExplainabilityComposer;
use App\Support\PlatformIntelligence\SignalEngine\Normalize\OverviewSnapshotNormalizer;
use App\Support\PlatformIntelligence\SignalEngine\Recommendation\RecommendedNextStepPolicy;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

final class PlatformSignalEngineComponentsTest extends TestCase
{
    public function test_normalizer_exposes_kpis_and_generated_at(): void
    {
        $norm = new OverviewSnapshotNormalizer([
            'generated_at' => '2026-04-01T12:00:00+00:00',
            'kpis' => ['inactive_companies' => 3, 'low_activity_companies' => 2],
            'health' => ['trend' => 'stable'],
            'alerts' => [],
            'companies_requiring_attention' => [],
            'definitions' => ['x' => 'y'],
        ]);
        $this->assertSame(3, $norm->kpis()['inactive_companies'] ?? 0);
        $this->assertSame('2026-04-01T12:00:00+00:00', $norm->generatedAt()->format(DateTimeInterface::ATOM));
    }

    public function test_detector_emits_signals_with_required_fields(): void
    {
        $norm = new OverviewSnapshotNormalizer([
            'generated_at' => '2026-04-01T12:00:00+00:00',
            'kpis' => [
                'inactive_companies' => 2,
                'low_activity_companies' => 1,
                'companies_new_7d' => 2,
                'companies_new_30d' => 4,
            ],
            'health' => ['trend' => 'degraded', 'failed_jobs' => 25, 'api' => 'ok', 'scheduler_last_run_at' => null],
            'alerts' => [
                ['type' => 'pending_financial_review', 'severity' => 'medium', 'message' => 'x'],
            ],
            'companies_requiring_attention' => [
                ['company_id' => 10, 'reason' => 'inactive_14d', 'last_activity_days_ago' => 20, 'reasons' => ['inactive_14d']],
                ['company_id' => 11, 'reason' => 'inactive_14d', 'last_activity_days_ago' => 30, 'reasons' => ['inactive_14d']],
                ['company_id' => 12, 'reason' => 'low_activity_7_14d', 'last_activity_days_ago' => 10, 'reasons' => ['low_activity_7_14d']],
                ['company_id' => 13, 'reason' => 'trial_ending', 'last_activity_days_ago' => 1, 'reasons' => ['trial_ending']],
            ],
            'definitions' => ['a' => 'b'],
        ]);
        $detector = new OverviewBasedSignalDetector();
        $drafts = $detector->detect($norm);
        $this->assertNotEmpty($drafts);
        foreach ($drafts as $d) {
            $this->assertNotSame('', $d->draft_key);
            $this->assertNotSame('', $d->title);
            $this->assertNotSame('', $d->affected_scope);
            $this->assertNotSame('', $d->summary_stub);
            $this->assertNotSame('', $d->why_stub);
        }
    }

    public function test_severity_scorer_respects_queue_pressure(): void
    {
        $scorer = new SeverityScorer();
        $draft = new SignalDraft(
            'k',
            PlatformSignalType::MetricThreshold,
            PlatformSignalSourceType::System,
            't',
            's',
            'w',
            'platform:runtime',
            ['e'],
            [],
            [],
            ['queue_pressure' => true, 'supporting_factor_count' => 2],
            null,
            new DateTimeImmutable(),
        );
        $sev = $scorer->score($draft);
        $this->assertContains(
            $sev,
            [PlatformIntelligenceSeverity::High, PlatformIntelligenceSeverity::Medium, PlatformIntelligenceSeverity::Critical, PlatformIntelligenceSeverity::Low],
        );
    }

    public function test_confidence_scorer_penalizes_sparse_metrics(): void
    {
        $scorer = new ConfidenceScorer();
        $norm = new OverviewSnapshotNormalizer(['generated_at' => '2026-01-01T00:00:00Z', 'kpis' => []]);
        $draft = new SignalDraft(
            'k',
            PlatformSignalType::Rule,
            PlatformSignalSourceType::System,
            't',
            's',
            'w',
            'platform:scheduler',
            [],
            [],
            [],
            ['scheduler_stale' => true, 'sparse_metrics' => true],
            null,
            new DateTimeImmutable(),
        );
        $c = $scorer->score($draft, $norm);
        $this->assertLessThan(0.75, $c);
    }

    public function test_dedupe_merges_identical_fingerprints(): void
    {
        $dedupe = new SignalDedupeService();
        $t = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $a = new PlatformSignalContract(
            'sig.duplicate',
            PlatformSignalType::Rule,
            'Title',
            'Sum',
            'Why',
            PlatformIntelligenceSeverity::Low,
            0.5,
            PlatformSignalSourceType::Operations,
            null,
            'scope',
            ['e'],
            [1],
            $t,
            $t,
            'next',
            ['k1'],
            null,
            'cid',
        );
        $b = new PlatformSignalContract(
            'sig.duplicate',
            PlatformSignalType::Rule,
            'Title',
            'Sum2',
            'Why2',
            PlatformIntelligenceSeverity::Medium,
            0.6,
            PlatformSignalSourceType::Operations,
            null,
            'scope',
            ['e'],
            [1],
            $t,
            $t,
            'next',
            ['k2'],
            null,
            'cid',
        );
        $out = $dedupe->dedupe([$a, $b]);
        $this->assertCount(1, $out);
        $this->assertContains('k1', $out[0]->correlation_keys);
        $this->assertContains('k2', $out[0]->correlation_keys);
    }

    public function test_explainability_appends_interpretation_lines(): void
    {
        $composer = new SignalExplainabilityComposer();
        $t = new DateTimeImmutable();
        $s = new PlatformSignalContract(
            'sig.x',
            PlatformSignalType::Trend,
            'T',
            'S',
            'Base why',
            PlatformIntelligenceSeverity::Medium,
            0.8,
            PlatformSignalSourceType::Operations,
            null,
            'platform:tenants',
            ['ent'],
            [5],
            $t,
            $t,
            'راقب',
            [],
            null,
            'cid',
        );
        $out = $composer->compose($s);
        $this->assertStringContainsString('تفسير الشدة', $out->why_summary);
        $this->assertStringContainsString('تفسير الثقة', $out->why_summary);
    }

    public function test_recommended_next_step_policy_returns_advisory_only(): void
    {
        $p = new RecommendedNextStepPolicy();
        $text = $p->forSignalKey('sig.platform.tenant.inactive_cluster');
        $this->assertStringContainsString('راقب', $text);
        $this->assertStringContainsString('مرشح', $text);
        $this->assertStringNotContainsString('ledger', strtolower($text));
    }
}
