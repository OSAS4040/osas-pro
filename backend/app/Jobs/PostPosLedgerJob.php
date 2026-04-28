<?php

namespace App\Jobs;

use App\Enums\JournalEntryType;
use App\Exceptions\LedgerPostingFailedException;
use App\Models\Invoice;
use App\Services\LedgerService;
use App\Support\Accounting\FinancialGlMapping;
use App\Support\Accounting\LedgerPostingDiagnostics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Backfill / repair path for POS invoices missing a journal row (e.g. legacy data).
 * Normal POS sales post the ledger synchronously inside {@see \App\Services\POSService::sale()}.
 */
class PostPosLedgerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 85;

    /** @var list<int> */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public int $invoiceId,
        public string $traceId,
    ) {
        $this->onQueue('high_priority');
        $this->afterCommit();
    }

    public function handle(LedgerService $ledger): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        $lines = [];
        try {
            // Idempotency guard for async posting retries/concurrency.
            $alreadyPosted = \Illuminate\Support\Facades\DB::table('journal_entries')
                ->where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->exists();
            if ($alreadyPosted) {
                return;
            }

            $lines = FinancialGlMapping::linesForPosSale($invoice);
            $repairKey = 'pos_async_repair:invoice:'.$invoice->id;
            $ledger->post(
                companyId: $invoice->company_id,
                data: [
                    'type'                      => JournalEntryType::Sale->value,
                    'description'               => "POS Sale {$invoice->invoice_number}",
                    'source_type'               => Invoice::class,
                    'source_id'                 => $invoice->id,
                    'entry_date'                => now()->toDateString(),
                    'lines'                     => $lines,
                    'trace_id'                  => $this->traceId,
                    'posting_idempotency_key'   => $repairKey,
                ],
                branchId: $invoice->branch_id,
                userId: $invoice->created_by_user_id,
            );
        } catch (LedgerPostingFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $repairKey = 'pos_async_repair:invoice:'.$invoice->id;
            $diag = LedgerPostingDiagnostics::fromGlLines('pos_async_repair', $lines, [
                'posting_idempotency_key' => $repairKey,
                'invoice_number'          => $invoice->invoice_number,
            ]);
            $wrapped = new LedgerPostingFailedException(
                source: 'pos_async_repair',
                companyId: (int) $invoice->company_id,
                invoiceId: $invoice->id,
                previous: $e,
                paymentId: null,
                diagnostics: $diag,
            );
            if ($e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
                report($wrapped);
                $this->fail($wrapped);

                return;
            }

            throw $wrapped;
        }
    }
}

