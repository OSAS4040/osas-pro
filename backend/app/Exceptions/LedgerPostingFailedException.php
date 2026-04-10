<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raised when a journal entry cannot be posted for an invoice/POS sale.
 * The surrounding DB transaction must roll back — no finalized invoice without a ledger row.
 */
final class LedgerPostingFailedException extends \RuntimeException
{
    public const ERROR_CODE = 'LEDGER_POST_FAILED';

    public function __construct(
        public readonly string $source,
        public readonly int $companyId,
        public readonly ?int $invoiceId,
        ?Throwable $previous = null,
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

        Log::critical('ledger.alert.ledger_posting_failed', [
            'code'             => self::ERROR_CODE,
            'source'           => $this->source,
            'company_id'       => $this->companyId,
            'invoice_id'       => $this->invoiceId,
            'trace_id'         => app()->bound('trace_id') ? app('trace_id') : null,
            'previous_class'   => $prev ? $prev::class : null,
            'previous_message' => $prev ? mb_substr($prev->getMessage(), 0, 500) : null,
        ]);
    }
}
