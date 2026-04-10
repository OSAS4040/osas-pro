<?php

namespace App\Services\Finance;

use App\Services\Finance\Exceptions\ReconciliationConcurrencyBlockedException;
use App\Services\Finance\Exceptions\ReconciliationStuckRunException;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationService
{
    public const RUNNING_WINDOW_MINUTES = 20;

    public const STUCK_CRITICAL_MINUTES = 60;

    public function recordAttempt(
        string $status,
        string $runType = 'daily',
        ?string $runDate = null,
        ?int $runId = null,
        ?int $blockedByRunId = null,
        ?string $reason = null,
        array $meta = []
    ): void {
        DB::table('financial_reconciliation_run_attempts')->insert([
            'run_type' => $runType,
            'run_date' => $runDate,
            'attempt_status' => $status,
            'run_id' => $runId,
            'blocked_by_run_id' => $blockedByRunId,
            'reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'trace_id' => (string) Str::uuid(),
            'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function markRunStarted(string $artifactPath, string $runType = 'daily', ?string $runDate = null): array
    {
        $runDate = $runDate ?: now()->toDateString();
        $startedAt = now();

        return DB::transaction(function () use ($artifactPath, $runType, $runDate, $startedAt): array {
            $runningRows = DB::table('financial_reconciliation_runs')
                ->where('execution_status', 'running')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($runningRows as $running) {
                $startedAtCandidate = $running->started_at ? Carbon::parse((string) $running->started_at) : null;
                if (! $startedAtCandidate) {
                    continue;
                }

                $runningMinutes = (int) $startedAtCandidate->diffInMinutes(now());
                if ($runningMinutes > self::RUNNING_WINDOW_MINUTES) {
                    DB::table('financial_reconciliation_runs')->where('id', $running->id)->update([
                        'execution_status' => 'failed',
                        'completed_at' => now(),
                        'duration_ms' => $runningMinutes * 60000,
                        'failure_message' => 'Marked as stuck running and auto-failed by concurrency guard.',
                        'failure_class' => ReconciliationStuckRunException::class,
                        'updated_at' => now(),
                    ]);
                    continue;
                }

                throw new ReconciliationConcurrencyBlockedException(
                    (int) $running->id,
                    "Another reconciliation run is already active (run_id={$running->id})."
                );
            }

            $existing = DB::table('financial_reconciliation_runs')
                ->where('run_type', $runType)
                ->where('run_date', $runDate)
                ->first();

            $payload = [
                'execution_status' => 'running',
                'started_at' => $startedAt,
                'completed_at' => null,
                'duration_ms' => null,
                'failure_message' => null,
                'failure_class' => null,
                'executed_at' => $startedAt,
                'artifact_path' => $artifactPath,
                'trace_id' => (string) Str::uuid(),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('financial_reconciliation_runs')
                    ->where('id', $existing->id)
                    ->update($payload);

                return ['run_id' => (int) $existing->id, 'created' => false, 'started_at' => $startedAt->toIso8601String()];
            }

            $runId = (int) DB::table('financial_reconciliation_runs')->insertGetId(array_merge($payload, [
                'uuid' => (string) Str::uuid(),
                'run_type' => $runType,
                'run_date' => $runDate,
                'detected_cases' => 0,
                'healthy_cases' => 0,
                'invoice_without_ledger_count' => 0,
                'unbalanced_journal_entry_count' => 0,
                'anomalous_reversal_settlement_count' => 0,
                'meta' => json_encode(['stage' => 'started'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]));

            return ['run_id' => $runId, 'created' => true, 'started_at' => $startedAt->toIso8601String()];
        });
    }

    public function markRunFailed(int $runId, string $failureMessage, string $failureClass, int $durationMs): void
    {
        DB::table('financial_reconciliation_runs')
            ->where('id', $runId)
            ->update([
                'execution_status' => 'failed',
                'completed_at' => now(),
                'duration_ms' => max(0, $durationMs),
                'failure_message' => mb_substr($failureMessage, 0, 255),
                'failure_class' => mb_substr($failureClass, 0, 160),
                'updated_at' => now(),
            ]);
    }

    public function run(int $sampleLimit = 100): array
    {
        $invoiceWithoutLedgerQuery = DB::table('invoices')
            ->leftJoin('journal_entries as je', function ($join): void {
                $join->on('je.source_id', '=', 'invoices.id')
                    ->where('je.source_type', '=', 'App\\Models\\Invoice');
            })
            ->whereNull('je.id');

        $invoiceTotal = DB::table('invoices')->count();
        $invoiceWithoutLedgerCount = (clone $invoiceWithoutLedgerQuery)->count();
        $invoiceWithoutLedgerRows = (clone $invoiceWithoutLedgerQuery)
            ->select('invoices.id', 'invoices.uuid', 'invoices.company_id', 'invoices.invoice_number', 'invoices.status')
            ->orderBy('invoices.id')
            ->get();
        $invoiceWithoutLedgerSample = $invoiceWithoutLedgerRows
            ->take($sampleLimit)
            ->values();

        $unbalancedEntriesQuery = DB::table('journal_entries')
            ->whereRaw('ABS(total_debit - total_credit) >= 0.001');

        $journalTotal = DB::table('journal_entries')->count();
        $unbalancedEntriesCount = (clone $unbalancedEntriesQuery)->count();
        $unbalancedEntriesRows = (clone $unbalancedEntriesQuery)
            ->select('id', 'uuid', 'company_id', 'entry_number', 'type', 'total_debit', 'total_credit', 'trace_id')
            ->orderBy('id')
            ->get();
        $unbalancedEntriesSample = $unbalancedEntriesRows
            ->take($sampleLimit)
            ->values();

        $anomalousReversalJournalQuery = DB::table('journal_entries')
            ->where(function ($q): void {
                $q->where('type', 'reversal')->whereNull('reversed_entry_id');
            })
            ->orWhere(function ($q): void {
                $q->where('type', '!=', 'reversal')->whereNotNull('reversed_entry_id');
            });

        $anomalousReversalPaymentQuery = DB::table('payments')
            ->where(function ($q): void {
                $q->where('status', 'refunded')->whereNull('original_payment_id');
            })
            ->orWhere(function ($q): void {
                $q->whereNotNull('original_payment_id')->where('status', '!=', 'refunded');
            });

        $journalReversalAnomaliesCount = (clone $anomalousReversalJournalQuery)->count();
        $paymentReversalAnomaliesCount = (clone $anomalousReversalPaymentQuery)->count();
        $reversalAnomalyCount = $journalReversalAnomaliesCount + $paymentReversalAnomaliesCount;

        $reversalJournalRows = (clone $anomalousReversalJournalQuery)
            ->select('id', 'uuid', 'company_id', 'entry_number', 'type', 'reversed_entry_id', 'reversed_by_entry_id', 'trace_id')
            ->orderBy('id')
            ->get();
        $reversalJournalSample = $reversalJournalRows
            ->take($sampleLimit)
            ->values();

        $reversalPaymentRows = (clone $anomalousReversalPaymentQuery)
            ->select('id', 'uuid', 'company_id', 'invoice_id', 'status', 'original_payment_id', 'reversal_payment_id', 'trace_id')
            ->orderBy('id')
            ->get();
        $reversalPaymentSample = $reversalPaymentRows
            ->take($sampleLimit)
            ->values();

        $detectedCases = $invoiceWithoutLedgerCount + $unbalancedEntriesCount + $reversalAnomalyCount;
        $healthyCases = max(0, ($invoiceTotal - $invoiceWithoutLedgerCount))
            + max(0, ($journalTotal - $unbalancedEntriesCount))
            + max(0, ((DB::table('journal_entries')->whereNotNull('reversed_entry_id')->count() + DB::table('payments')->count()) - $reversalAnomalyCount));

        return [
            'generated_at' => now()->toIso8601String(),
            'checks' => [
                'invoice_without_ledger' => [
                    'detected' => $invoiceWithoutLedgerCount,
                    'total_examined' => $invoiceTotal,
                    'healthy' => max(0, $invoiceTotal - $invoiceWithoutLedgerCount),
                    'sample' => $invoiceWithoutLedgerSample,
                ],
                'unbalanced_journal_entries' => [
                    'detected' => $unbalancedEntriesCount,
                    'total_examined' => $journalTotal,
                    'healthy' => max(0, $journalTotal - $unbalancedEntriesCount),
                    'sample' => $unbalancedEntriesSample,
                ],
                'anomalous_reversal_settlement' => [
                    'detected' => $reversalAnomalyCount,
                    'total_examined' => DB::table('journal_entries')->whereNotNull('reversed_entry_id')->count() + DB::table('payments')->count(),
                    'healthy' => max(0, (DB::table('journal_entries')->whereNotNull('reversed_entry_id')->count() + DB::table('payments')->count()) - $reversalAnomalyCount),
                    'sample' => [
                        'journal_entries' => $reversalJournalSample,
                        'payments' => $reversalPaymentSample,
                    ],
                ],
            ],
            'summary' => [
                'detected_cases' => $detectedCases,
                'healthy_cases' => $healthyCases,
            ],
            'findings' => [
                'invoice_without_ledger' => $invoiceWithoutLedgerRows->map(static function ($row): array {
                    return [
                        'finding_type' => 'invoice_without_ledger',
                        'company_id' => $row->company_id,
                        'invoice_id' => $row->id,
                        'journal_entry_id' => null,
                        'payment_id' => null,
                        'reference_type' => 'invoice',
                        'reference_id' => $row->id,
                        'trace_reference' => null,
                        'details' => [
                            'invoice_uuid' => $row->uuid,
                            'invoice_number' => $row->invoice_number,
                            'status' => $row->status,
                        ],
                    ];
                })->all(),
                'unbalanced_journal_entry' => $unbalancedEntriesRows->map(static function ($row): array {
                    return [
                        'finding_type' => 'unbalanced_journal_entry',
                        'company_id' => $row->company_id,
                        'invoice_id' => null,
                        'journal_entry_id' => $row->id,
                        'payment_id' => null,
                        'reference_type' => 'journal_entry',
                        'reference_id' => $row->id,
                        'trace_reference' => $row->trace_id,
                        'details' => [
                            'entry_number' => $row->entry_number,
                            'entry_type' => $row->type,
                            'total_debit' => (float) $row->total_debit,
                            'total_credit' => (float) $row->total_credit,
                        ],
                    ];
                })->all(),
                'anomalous_reversal_settlement' => array_merge(
                    $reversalJournalRows->map(static function ($row): array {
                        return [
                            'finding_type' => 'anomalous_reversal_settlement',
                            'company_id' => $row->company_id,
                            'invoice_id' => null,
                            'journal_entry_id' => $row->id,
                            'payment_id' => null,
                            'reference_type' => 'journal_entry',
                            'reference_id' => $row->id,
                            'trace_reference' => $row->trace_id,
                            'details' => [
                                'entry_number' => $row->entry_number,
                                'entry_type' => $row->type,
                                'reversed_entry_id' => $row->reversed_entry_id,
                                'reversed_by_entry_id' => $row->reversed_by_entry_id,
                            ],
                        ];
                    })->all(),
                    $reversalPaymentRows->map(static function ($row): array {
                        return [
                            'finding_type' => 'anomalous_reversal_settlement',
                            'company_id' => $row->company_id,
                            'invoice_id' => $row->invoice_id,
                            'journal_entry_id' => null,
                            'payment_id' => $row->id,
                            'reference_type' => 'payment',
                            'reference_id' => $row->id,
                            'trace_reference' => $row->trace_id,
                            'details' => [
                                'payment_status' => $row->status,
                                'original_payment_id' => $row->original_payment_id,
                                'reversal_payment_id' => $row->reversal_payment_id,
                            ],
                        ];
                    })->all()
                ),
            ],
        ];
    }

    public function persistRun(array $report, string $artifactPath, string $runType = 'daily', ?string $runDate = null, ?int $runId = null, ?string $startedAt = null, ?int $durationMs = null): array
    {
        $runDate = $runDate ?: now()->toDateString();
        $traceId = (string) Str::uuid();

        return DB::transaction(function () use ($report, $artifactPath, $runType, $runDate, $traceId, $runId, $startedAt, $durationMs): array {
            $existing = $runId
                ? DB::table('financial_reconciliation_runs')->where('id', $runId)->first()
                : DB::table('financial_reconciliation_runs')->where('run_type', $runType)->where('run_date', $runDate)->first();

            $payload = [
                'executed_at' => now(),
                'artifact_path' => $artifactPath,
                'execution_status' => 'succeeded',
                'started_at' => $startedAt ?: now()->toIso8601String(),
                'completed_at' => now(),
                'duration_ms' => $durationMs ?? 0,
                'failure_message' => null,
                'failure_class' => null,
                'detected_cases' => (int) $report['summary']['detected_cases'],
                'healthy_cases' => (int) $report['summary']['healthy_cases'],
                'invoice_without_ledger_count' => (int) $report['checks']['invoice_without_ledger']['detected'],
                'unbalanced_journal_entry_count' => (int) $report['checks']['unbalanced_journal_entries']['detected'],
                'anomalous_reversal_settlement_count' => (int) $report['checks']['anomalous_reversal_settlement']['detected'],
                'trace_id' => $traceId,
                'meta' => json_encode([
                    'generated_at' => $report['generated_at'] ?? now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('financial_reconciliation_runs')
                    ->where('id', $existing->id)
                    ->update($payload);
                $runId = (int) $existing->id;
                $wasCreated = false;
            } else {
                $runId = (int) DB::table('financial_reconciliation_runs')
                    ->insertGetId(array_merge($payload, [
                        'uuid' => (string) Str::uuid(),
                        'run_type' => $runType,
                        'run_date' => $runDate,
                        'created_at' => now(),
                    ]));
                $wasCreated = true;
            }

            DB::table('financial_reconciliation_findings')->where('run_id', $runId)->delete();

            $rows = [];
            foreach (['invoice_without_ledger', 'unbalanced_journal_entry', 'anomalous_reversal_settlement'] as $type) {
                foreach (($report['findings'][$type] ?? []) as $finding) {
                    $rows[] = [
                        'run_id' => $runId,
                        'finding_type' => $finding['finding_type'],
                        'company_id' => $finding['company_id'],
                        'invoice_id' => $finding['invoice_id'],
                        'journal_entry_id' => $finding['journal_entry_id'],
                        'payment_id' => $finding['payment_id'],
                        'reference_type' => $finding['reference_type'],
                        'reference_id' => $finding['reference_id'],
                        'trace_reference' => $finding['trace_reference'],
                        'details' => json_encode($finding['details'], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('financial_reconciliation_findings')->insert($rows);
            }

            return [
                'run_id' => $runId,
                'created' => $wasCreated,
                'findings_count' => count($rows),
            ];
        });
    }
}
