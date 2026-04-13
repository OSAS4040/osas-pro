<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * فحص سلامة تشغيلية/مالية سريع (بدون ميزات جديدة في المنتج):
 * فواتير بعرض حي بدون قيد، مخزون سالب، محافظ غير متوافقة مع آخر حركة، تكرار فاتورة.
 */
class DataIntegrityVerifyCommand extends Command
{
    protected $signature = 'integrity:verify
                            {--company= : حصر الفحص على company_id}
                            {--json : مخرجات JSON فقط}';

    protected $description = 'Verify operational/financial data integrity (invoices↔ledger, inventory, wallets, duplicates)';

    public function handle(): int
    {
        $companyId = $this->option('company') !== null && $this->option('company') !== ''
            ? (int) $this->option('company')
            : null;

        $violations = [
            'invoices_without_journal' => $this->invoicesMissingJournal($companyId),
            'negative_on_hand_stock'  => $this->negativeInventory($companyId),
            'wallet_balance_drift'   => $this->walletBalanceDrift($companyId),
            'duplicate_invoice_sources' => $this->duplicateInvoiceSources($companyId),
            'duplicate_invoice_hashes'  => $this->duplicateInvoiceHashes($companyId),
        ];

        $total = array_sum(array_map('count', $violations));

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok'         => $total === 0,
                'violations' => $violations,
                'counts'     => array_map('count', $violations),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $total === 0 ? self::SUCCESS : self::FAILURE;
        }

        foreach ($violations as $label => $rows) {
            $c = count($rows);
            if ($c === 0) {
                $this->info("✓ {$label}: 0");
                continue;
            }
            $this->error("✗ {$label}: {$c}");
            foreach (array_slice($rows, 0, 20) as $row) {
                $this->line('  · ' . json_encode($row, JSON_UNESCAPED_UNICODE));
            }
            if ($c > 20) {
                $this->line("  … و" . ($c - 20) . ' إضافية');
            }
        }

        if ($total > 0) {
            $this->newLine();
            $this->error("SUMMARY: {$total} violation row(s) — exit 1");

            return self::FAILURE;
        }

        $this->info('SUMMARY: all integrity checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return list<object|array<string,mixed>>
     */
    private function invoicesMissingJournal(?int $companyId): array
    {
        $q = DB::table('invoices')
            ->leftJoin('journal_entries as je', function ($join): void {
                $join->on('je.source_id', '=', 'invoices.id')
                    ->where('je.source_type', '=', Invoice::class);
            })
            ->whereNull('je.id')
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->whereNull('invoices.deleted_at');

        if ($companyId !== null) {
            $q->where('invoices.company_id', $companyId);
        }

        return $q->select(
            'invoices.id',
            'invoices.company_id',
            'invoices.invoice_number',
            'invoices.status'
        )->orderBy('invoices.id')->limit(500)->get()->all();
    }

    /**
     * @return list<object|array<string,mixed>>
     */
    private function negativeInventory(?int $companyId): array
    {
        $q = DB::table('inventory')
            ->where(function ($q): void {
                $q->where('quantity', '<', 0)
                    ->orWhereRaw('(quantity - COALESCE(reserved_quantity, 0)) < 0');
            });

        if ($companyId !== null) {
            $q->where('company_id', $companyId);
        }

        return $q->select('id', 'company_id', 'branch_id', 'product_id', 'quantity', 'reserved_quantity')
            ->orderBy('id')->limit(500)->get()->all();
    }

    /**
     * @return list<object|array<string,mixed>>
     */
    private function walletBalanceDrift(?int $companyId): array
    {
        $wallets = DB::table('customer_wallets as w')
            ->where('w.status', 'active')
            ->when($companyId !== null, fn ($q) => $q->where('w.company_id', $companyId))
            ->select('w.id', 'w.company_id', 'w.balance')
            ->orderBy('w.id')
            ->get();

        $out = [];
        foreach ($wallets as $w) {
            $last = DB::table('wallet_transactions')
                ->where('customer_wallet_id', $w->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->select('balance_after')
                ->first();

            $bal = (float) $w->balance;
            if ($last === null) {
                if (abs($bal) > 0.0001) {
                    $out[] = [
                        'wallet_id'   => $w->id,
                        'company_id'  => $w->company_id,
                        'issue'       => 'no_transactions_but_nonzero_balance',
                        'balance'     => $bal,
                    ];
                }
                continue;
            }

            $after = (float) $last->balance_after;
            if (abs($bal - $after) > 0.02) {
                $out[] = [
                    'wallet_id'        => $w->id,
                    'company_id'       => $w->company_id,
                    'issue'            => 'balance_ne_last_txn_balance_after',
                    'wallet_balance'   => $bal,
                    'last_balance_after'=> $after,
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<object|array<string,mixed>>
     */
    private function duplicateInvoiceSources(?int $companyId): array
    {
        $q = DB::table('invoices')
            ->select('company_id', 'source_type', 'source_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->whereNull('deleted_at')
            ->groupBy('company_id', 'source_type', 'source_id')
            ->havingRaw('COUNT(*) > 1');

        if ($companyId !== null) {
            $q->where('company_id', $companyId);
        }

        return $q->orderBy('company_id')->limit(500)->get()->all();
    }

    /**
     * @return list<object|array<string,mixed>>
     */
    private function duplicateInvoiceHashes(?int $companyId): array
    {
        $q = DB::table('invoices')
            ->select('company_id', 'invoice_hash', DB::raw('COUNT(*) as c'))
            ->whereNotNull('invoice_hash')
            ->where('invoice_hash', '!=', '')
            ->whereNull('deleted_at')
            ->groupBy('company_id', 'invoice_hash')
            ->havingRaw('COUNT(*) > 1');

        if ($companyId !== null) {
            $q->where('company_id', $companyId);
        }

        return $q->orderBy('company_id')->limit(500)->get()->all();
    }
}
