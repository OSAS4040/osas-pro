<?php

namespace App\Console\Commands;

use App\Services\Finance\Exceptions\ReconciliationConcurrencyBlockedException;
use App\Services\Finance\FinancialReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class FinancialReconciliationCommand extends Command
{
    protected $signature = 'finance:reconcile-daily
        {--out-file=reports/financial-reliability/reconciliation-report.json : Fixed report artifact path}
        {--sample-limit=100 : Number of sample rows per anomaly bucket}
        {--run-date= : Override run date (YYYY-MM-DD) for testing/idempotency checks}
        {--simulate-failure : Force command failure for execution observability tests}';

    protected $description = 'Run financial reconciliation checks and persist a reviewable artifact report.';

    public function __construct(private readonly FinancialReconciliationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $outFile = (string) $this->option('out-file');
        $sampleLimit = max(1, min(1000, (int) $this->option('sample-limit')));
        $runDate = $this->option('run-date') ? (string) $this->option('run-date') : null;
        $runStart = microtime(true);
        $startedAt = now();

        try {
            $started = $this->service->markRunStarted($outFile, 'daily', $runDate);
            $runId = (int) $started['run_id'];
            $this->service->recordAttempt('started', 'daily', $runDate, $runId);
        } catch (ReconciliationConcurrencyBlockedException $blocked) {
            $this->service->recordAttempt(
                'blocked',
                'daily',
                $runDate,
                null,
                $blocked->runningRunId,
                $blocked->getMessage()
            );
            $this->error('RECONCILIATION_BLOCKED='.$blocked->getMessage());
            return self::FAILURE;
        }

        try {
            if ((bool) $this->option('simulate-failure')) {
                throw new \RuntimeException('Simulated reconciliation failure for observability validation.');
            }

            $report = $this->service->run($sampleLimit);

            $absolutePath = base_path($outFile);
            $dir = dirname($absolutePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $durationMs = (int) round((microtime(true) - $runStart) * 1000);
            $persisted = $this->service->persistRun(
                $report,
                $outFile,
                'daily',
                $runDate,
                $runId,
                $startedAt->toIso8601String(),
                $durationMs
            );

            $this->info("RECONCILIATION_REPORT={$outFile}");
            $this->info('RUN_ID='.$persisted['run_id']);
            $this->info('RUN_CREATED='.($persisted['created'] ? 'true' : 'false'));
            $this->info('DB_FINDINGS='.$persisted['findings_count']);
            $this->info('EXECUTION_STATUS=succeeded');
            $this->info('DURATION_MS='.$durationMs);
            $this->info('DETECTED_CASES='.$report['summary']['detected_cases']);
            $this->info('HEALTHY_CASES='.$report['summary']['healthy_cases']);
            $this->info('CHECK_invoice_without_ledger='.$report['checks']['invoice_without_ledger']['detected']);
            $this->info('CHECK_unbalanced_journal_entries='.$report['checks']['unbalanced_journal_entries']['detected']);
            $this->info('CHECK_anomalous_reversal_settlement='.$report['checks']['anomalous_reversal_settlement']['detected']);
            $this->service->recordAttempt('succeeded', 'daily', $runDate, $runId, null, null, ['duration_ms' => $durationMs]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $runStart) * 1000);
            $this->service->markRunFailed($runId, $e->getMessage(), get_class($e), $durationMs);
            $this->service->recordAttempt('failed', 'daily', $runDate, $runId, null, $e->getMessage(), ['duration_ms' => $durationMs]);
            $this->error('RECONCILIATION_FAILED='.$e->getMessage());
            $this->error('RUN_ID='.$runId);
            $this->error('EXECUTION_STATUS=failed');
            return self::FAILURE;
        }
    }
}
