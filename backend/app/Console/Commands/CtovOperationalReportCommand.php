<?php

namespace App\Console\Commands;

use App\Services\QaValidationRunnerService;
use Illuminate\Console\Command;

/**
 * CTO validation: stress read latency + concurrent wallet idempotency + optional simulation:stress.
 */
class CtovOperationalReportCommand extends Command
{
    protected $signature = 'ctov:operational-report
                            {--stress-ops=1000 : Number of DB ping operations}
                            {--race-workers=20 : Concurrent wallet race processes}
                            {--email=owner@demo.sa : User email for race + simulation}
                            {--skip-simulation : Do not run simulation:stress}
                            {--output=reports/after_fix.json : JSON report path (relative to project root)}';

    protected $description = 'Generate operational validation JSON (stress + race + optional simulation)';

    public function handle(): int
    {
        $projectRoot = dirname(__DIR__, 4);
        $ops    = max(100, min(50_000, (int) $this->option('stress-ops')));
        $workers = max(2, min(60, (int) $this->option('race-workers')));
        $email  = (string) $this->option('email');
        $outOpt = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $this->option('output'));
        $reportPath = str_starts_with($outOpt, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:[\\\\/]#', $outOpt)
            ? $outOpt
            : $projectRoot.DIRECTORY_SEPARATOR.$outOpt;

        if (! is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0755, true);
        }

        $payload = QaValidationRunnerService::make()->run(
            $email,
            $ops,
            $workers,
            ! (bool) $this->option('skip-simulation'),
        );
        $payload['phase'] = 'cli_after_fix';

        file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Report written: '.$reportPath);

        return self::SUCCESS;
    }
}
