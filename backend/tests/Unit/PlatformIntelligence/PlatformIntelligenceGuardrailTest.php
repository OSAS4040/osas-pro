<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use PHPUnit\Framework\TestCase;

final class PlatformIntelligenceGuardrailTest extends TestCase
{
    public function test_intelligence_support_tree_has_no_finance_namespace_coupling(): void
    {
        $root = realpath(__DIR__.'/../../../app/Support/PlatformIntelligence');
        $this->assertNotFalse($root);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('App\\Models\\Ledger', $contents, $file->getPathname());
            $this->assertStringNotContainsString('Ledger::', $contents, $file->getPathname());
            $this->assertStringNotContainsString('Wallet', $contents, $file->getPathname());
        }
    }

    public function test_api_intelligence_incident_center_exists_and_decision_log_not_mutated_early(): void
    {
        $api = (string) file_get_contents(__DIR__.'/../../../routes/api.php');
        $this->assertStringContainsString('/platform/intelligence/signals', $api);
        $this->assertStringContainsString('PlatformIntelligenceSignalsController', $api);
        $this->assertStringContainsString('/platform/intelligence/incident-candidates', $api);
        $this->assertStringContainsString('PlatformIntelligenceIncidentCandidatesController', $api);
        $this->assertStringContainsString('/platform/intelligence/incidents', $api);
        $this->assertStringContainsString('PlatformIntelligenceIncidentsController', $api);
        $this->assertStringContainsString('PlatformDecisionLogController', $api);
        $this->assertStringContainsString('PlatformIncidentWorkflowController', $api);
        $this->assertStringContainsString('PlatformIntelligenceCommandSurfaceController', $api);
        $this->assertStringContainsString('PlatformControlledActionController', $api);
        $this->assertStringContainsString("/platform/intelligence/command-surface'", $api);
        $this->assertStringContainsString("/platform/intelligence/decisions'", $api);
        // Decision writes are only nested under incidents — no loose POST /platform/intelligence/decisions.
        $this->assertSame(0, preg_match_all('/Route::(post|put|patch|delete)\([^;]*platform\\/intelligence\\/decisions[\'\"]/is', $api));
        $this->assertSame(0, preg_match_all('/Route::(post|put|patch|delete)\([^;]*platform\\/intelligence\\/command-surface/is', $api));
        $this->assertSame(0, preg_match_all('/Route::(post|put|patch|delete)\([^;]*intelligence\\/incidents[^;]*\\/correlation/is', $api));
        $this->assertSame(0, preg_match_all('/Route::post\([^)]*\\/platform\\/intelligence\\/controlled-actions\\/execute/is', $api));
        $this->assertGreaterThan(0, preg_match_all('/PlatformIncidentLifecycleController::class/is', $api));
    }
}
