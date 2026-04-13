<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Runs DB stress + concurrent wallet idempotency race (+ optional simulation:stress).
 * Persists JSON to storage and project reports/ for visibility.
 */
class QaValidationRunnerService
{
    public function __construct(
        private readonly string $backendRoot,
        private readonly string $projectRoot,
    ) {}

    public static function make(): self
    {
        $backendRoot = base_path();
        $projectRoot = dirname($backendRoot);

        return new self($backendRoot, $projectRoot);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $email,
        int $stressOps = 1000,
        int $raceWorkers = 20,
        bool $runSimulation = false,
    ): array {
        $stressOps   = max(100, min(50_000, $stressOps));
        $raceWorkers = max(2, min(60, $raceWorkers));

        Log::info('qa.validation.run.start', [
            'email'        => $email,
            'stress_ops'   => $stressOps,
            'race_workers' => $raceWorkers,
            'simulation'   => $runSimulation,
        ]);

        $latMs = [];
        $stressFailures = 0;
        for ($i = 0; $i < $stressOps; $i++) {
            $t = microtime(true);
            try {
                DB::select('select 1 as ok');
                $latMs[] = (microtime(true) - $t) * 1000;
            } catch (\Throwable $e) {
                $stressFailures++;
                Log::warning('qa.validation.stress.fail', ['i' => $i, 'error' => $e->getMessage()]);
            }
        }

        $sorted = array_values(array_filter($latMs));
        sort($sorted);
        $p95idx = count($sorted) > 0 ? (int) floor(0.95 * (count($sorted) - 1)) : 0;

        $stress = [
            'operations'    => $stressOps,
            'success_count' => count($latMs),
            'failure_count' => $stressFailures,
            'latency_ms'    => [
                'avg' => count($sorted) ? round(array_sum($sorted) / count($sorted), 4) : 0,
                'min' => count($sorted) ? round(min($sorted), 4) : 0,
                'max' => count($sorted) ? round(max($sorted), 4) : 0,
                'p95' => count($sorted) ? round($sorted[$p95idx] ?? 0, 4) : 0,
            ],
        ];

        $raceKey = 'ctov-race-'.Str::uuid()->toString();
        $php     = PHP_BINARY;
        $artisan = $this->backendRoot.DIRECTORY_SEPARATOR.'artisan';
        $processes = [];
        for ($w = 0; $w < $raceWorkers; $w++) {
            $p = new Process([
                $php,
                $artisan,
                'ctov:wallet-race-attempt',
                '--email='.$email,
                '--key='.$raceKey,
                '--amount=0.01',
            ], $this->backendRoot, null, null, 120);
            $p->start();
            $processes[] = $p;
        }

        $raceSuccess = 0;
        $raceDuplicate = 0;
        $raceFail = 0;
        foreach ($processes as $p) {
            $p->wait();
            if ($p->getExitCode() === 0) {
                $raceSuccess++;
            } elseif ($p->getExitCode() === 2) {
                $raceDuplicate++;
            } else {
                $raceFail++;
            }
        }

        $race = [
            'workers'                       => $raceWorkers,
            'shared_idempotency_key_prefix' => Str::limit($raceKey, 48, ''),
            'exit_code_0_success'           => $raceSuccess,
            'exit_code_2_duplicate'         => $raceDuplicate,
            'exit_other_failure'            => $raceFail,
        ];

        $sim = ['ran' => false, 'exit_code' => null, 'note' => 'skipped'];
        if ($runSimulation) {
            $simProcess = new Process([
                $php,
                $artisan,
                'simulation:stress',
                '--email='.$email,
                '--customers=2',
                '--batches=1',
                '--skip-wo',
            ], $this->backendRoot, null, null, 600);
            $simProcess->run();
            $sim = [
                'ran'         => true,
                'exit_code'   => $simProcess->getExitCode(),
                'stdout_tail' => Str::limit($simProcess->getOutput(), 4000, '…'),
                'stderr_tail' => Str::limit($simProcess->getErrorOutput(), 2000, '…'),
            ];
        }

        $doubleDebitSuspected = $raceSuccess > 1;
        $integrityFlags = [
            'double_debit_suspected'          => $doubleDebitSuspected,
            'duplicate_invoice_suspected'     => false,
            'negative_stock_suspected'        => false,
            'note'                            => 'invoice/stock flags require dedicated audit queries; wallet race: >1 success implies duplicate credit risk.',
        ];

        $systemStatus = 'PASS';
        if ($stressFailures > 0 || $raceFail > 0) {
            $systemStatus = 'FAIL';
        } elseif ($doubleDebitSuspected || $raceSuccess !== 1) {
            $systemStatus = 'NEEDS_FIX';
        }

        $payload = [
            'generated_at'              => now()->toIso8601String(),
            'tenant_user'               => $email,
            'stress_db_ping'            => $stress,
            'wallet_race'               => $race,
            'simulation_stress'         => $sim,
            'integrity_flags'           => $integrityFlags,
            'system_status'             => $systemStatus,
            'wallet_idempotency_expect' => 'Exactly one race worker should succeed (exit 0); others exit 2 (duplicate).',
        ];

        $storagePath = storage_path('app/qa-validation-latest.json');
        file_put_contents($storagePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $reportsPath = $this->projectRoot.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.'qa_validation_latest.json';
        if (! is_dir(dirname($reportsPath))) {
            mkdir(dirname($reportsPath), 0755, true);
        }
        file_put_contents($reportsPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info('qa.validation.run.done', ['status' => $systemStatus, 'path' => $storagePath]);

        return $payload;
    }
}
