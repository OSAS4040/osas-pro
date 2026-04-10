<?php

namespace App\Console\Commands;

use App\Jobs\PostPosLedgerJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcilePosLedgerCommand extends Command
{
    protected $signature = 'ledger:reconcile-pos
        {--dry-run : Only report missing invoices without dispatch}
        {--limit=5000 : Max invoices to process}
        {--chunk=200 : Chunk size for querying/dispatching}';

    protected $description = 'Reconcile missing POS sale ledger entries by dispatching PostPosLedgerJob for invoices without journal entries.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $chunk = max(1, min(1000, (int) $this->option('chunk')));

        $baseQuery = DB::table('invoices')
            ->select('invoices.id')
            ->leftJoin('journal_entries as je', function ($join): void {
                $join->on('je.source_id', '=', 'invoices.id')
                    ->where('je.source_type', '=', 'App\\Models\\Invoice');
            })
            ->where('invoices.type', 'sale')
            ->whereNull('je.id')
            ->orderBy('invoices.id');

        $missingTotal = (clone $baseQuery)->count();
        $toProcess = min($missingTotal, $limit);

        $this->info("missing_total={$missingTotal}");
        $this->info("processing_limit={$toProcess}");
        $this->info("dry_run=".($dryRun ? 'true' : 'false'));

        if ($toProcess === 0 || $dryRun) {
            return self::SUCCESS;
        }

        $processed = 0;
        (clone $baseQuery)
            ->limit($toProcess)
            ->chunk($chunk, function ($rows) use (&$processed): void {
                foreach ($rows as $row) {
                    PostPosLedgerJob::dispatch((int) $row->id, Str::uuid()->toString())->afterCommit();
                    $processed++;
                }
            });

        $this->info("dispatched={$processed}");

        return self::SUCCESS;
    }
}

