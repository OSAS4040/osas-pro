<?php

namespace App\Exceptions;

use App\Support\Accounting\LedgerSqlDiagnostics;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raised when a journal entry cannot be posted for an invoice/POS sale.
 * The surrounding DB transaction must roll back — no finalized invoice without a ledger row.
 */
final class LedgerPostingFailedException extends \RuntimeException
{
    public const ERROR_CODE = 'LEDGER_POST_FAILED';

    /**
     * @param  array<string, mixed>|null  $diagnostics  GL snapshot, ids, idempotency key, etc.
     */
    public function __construct(
        public readonly string $source,
        public readonly int $companyId,
        public readonly ?int $invoiceId,
        ?Throwable $previous = null,
        public readonly ?int $paymentId = null,
        public readonly ?array $diagnostics = null,
    ) {
        parent::__construct(
            'Accounting entry could not be recorded. The transaction was not completed.',
            0,
            $previous
        );
    }

    /**
     * Structured production alert — route this channel in ops (e.g. Datadog, CloudWatch, Sentry breadcrumbs).
     */
    public function report(): void
    {
        $prev = $this->getPrevious();
        $sqlDiag = LedgerSqlDiagnostics::fromThrowable($prev);

        $d = $this->diagnostics ?? [];

        $payload = [
            'code'               => self::ERROR_CODE,
            'posting_service'    => \App\Services\LedgerService::class,
            'source'             => $this->source,
            'operation_type'     => $d['operation_type'] ?? $this->source,
            'company_id'         => $this->companyId,
            'invoice_id'         => $this->invoiceId,
            'payment_id'         => $this->paymentId,
            'journal_id'         => $d['journal_id'] ?? null,
            'posting_idempotency_key' => $d['posting_idempotency_key'] ?? null,
            'trace_id'           => app()->bound('trace_id') ? app('trace_id') : null,
            'request_id'         => app()->bound('request_id') ? app('request_id') : null,
            'account_codes'      => $d['account_codes'] ?? null,
            'gl_total_debit'     => $d['gl_total_debit'] ?? null,
            'gl_total_credit'    => $d['gl_total_credit'] ?? null,
            'gl_line_count'      => $d['gl_line_count'] ?? null,
            'gl_balanced'        => $d['gl_balanced'] ?? null,
            'previous_class'     => $prev ? $prev::class : null,
            'previous_message'   => $prev ? mb_substr($prev->getMessage(), 0, 2000) : null,
            'previous_root_message' => $this->deepestMessage($prev),
        ];

        $payload = array_merge($payload, $sqlDiag);
        if (isset($payload['chain']) && is_array($payload['chain'])) {
            $payload['chain'] = array_slice($payload['chain'], 0, 8);
        }
        if (is_array($this->diagnostics)) {
            foreach ($this->diagnostics as $k => $v) {
                if (! array_key_exists($k, $payload)) {
                    $payload[$k] = $v;
                }
            }
        }

        Log::critical('ledger.alert.ledger_posting_failed', $payload);
    }

    private function deepestMessage(?Throwable $e): ?string
    {
        $last = $e;
        while ($last?->getPrevious() !== null) {
            $last = $last->getPrevious();
        }

        return $last ? mb_substr($last->getMessage(), 0, 2000) : null;
    }
}
