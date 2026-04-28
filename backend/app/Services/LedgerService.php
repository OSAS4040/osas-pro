<?php

namespace App\Services;

use App\Enums\JournalEntryType;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LedgerService — Append-Only Double-Entry Accounting Engine
 *
 * Rules:
 *  - Every journal entry must balance (total_debit == total_credit).
 *  - Entries cannot be updated or deleted (enforced by DB trigger + this service).
 *  - Corrections are done via reversal entries only.
 */
class LedgerService
{
    /**
     * Session advisory lock for journal-entry sequence bootstrap / repair only (not xact-wide).
     * Distinct from POSService invoice lock key to avoid cross-module coupling.
     */
    private const ADVISORY_LOCK_JE_SEQ_K1 = 928_374_652;

    /**
     * Post a balanced journal entry.
     *
     * @param  array{
     *   type: string,
     *   description: string,
     *   source_type?: string,
     *   source_id?: int,
     *   entry_date?: string,
     *   lines: array<array{account_code: string, type: 'debit'|'credit', amount: float, description?: string}>,
     *   currency?: string,
     *   trace_id: string,
     *   posting_idempotency_key?: string,
     * } $data
     */
    public function post(
        int $companyId,
        array $data,
        ?int $branchId = null,
        ?int $userId = null,
    ): JournalEntry {
        $traceId = trim((string) ($data['trace_id'] ?? ''));
        if ($traceId === '') {
            throw new \InvalidArgumentException('trace_id is required for journal posting.');
        }

        $postingIdempotencyKey = trim((string) ($data['posting_idempotency_key'] ?? ''));

        $lines = $data['lines'] ?? [];

        if (count($lines) < 2) {
            throw new \DomainException('Journal entry requires at least 2 lines.');
        }

        foreach ($lines as $i => $line) {
            if (! is_array($line)) {
                throw new \DomainException("Journal line {$i} must be an array.");
            }
            foreach (['account_code', 'type', 'amount'] as $key) {
                if (! array_key_exists($key, $line)) {
                    throw new \DomainException("Journal line {$i} is missing '{$key}'.");
                }
            }
            if ($line['type'] !== 'debit' && $line['type'] !== 'credit') {
                throw new \DomainException("Journal line {$i} type must be 'debit' or 'credit'.");
            }
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            if ($line['type'] === 'debit') {
                $totalDebit += (float) $line['amount'];
            } else {
                $totalCredit += (float) $line['amount'];
            }
        }

        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw new \DomainException(
                "Journal entry is not balanced. Debit: {$totalDebit} | Credit: {$totalCredit}"
            );
        }

        try {
            return DB::transaction(function () use (
                $companyId,
                $branchId,
                $userId,
                $data,
                $totalDebit,
                $totalCredit,
                $lines,
                $traceId,
                $postingIdempotencyKey,
            ) {
                if ($postingIdempotencyKey !== '') {
                    $existing = JournalEntry::query()
                        ->where('company_id', $companyId)
                        ->where('posting_idempotency_key', $postingIdempotencyKey)
                        ->first();
                    if ($existing) {
                        return $existing->load('lines.account');
                    }
                }

                $pgsql = DB::getDriverName() === 'pgsql';
                $entry = null;

                for ($entryAttempt = 0; $entryAttempt < 2; $entryAttempt++) {
                    if ($pgsql) {
                        DB::statement('SAVEPOINT ledger_entry_number');
                    }
                    try {
                        $entryNumber = $this->generateEntryNumber($companyId);

                        $entry = JournalEntry::create([
                            'uuid' => Str::uuid(),
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'entry_number' => $entryNumber,
                            'type' => $data['type'],
                            'source_type' => $data['source_type'] ?? null,
                            'source_id' => $data['source_id'] ?? null,
                            'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                            'description' => $data['description'] ?? null,
                            'total_debit' => round($totalDebit, 4),
                            'total_credit' => round($totalCredit, 4),
                            'currency' => $data['currency'] ?? 'SAR',
                            'trace_id' => $traceId,
                            'posting_idempotency_key' => $postingIdempotencyKey !== '' ? $postingIdempotencyKey : null,
                            'created_by_user_id' => $userId,
                        ]);

                        foreach ($lines as $line) {
                            $account = $this->resolveAccount($companyId, $line['account_code']);

                            JournalEntryLine::create([
                                'journal_entry_id' => $entry->id,
                                'account_id' => $account->id,
                                'type' => $line['type'],
                                'amount' => round((float) $line['amount'], 4),
                                'description' => $line['description'] ?? null,
                            ]);
                        }

                        if ($pgsql) {
                            DB::statement('RELEASE SAVEPOINT ledger_entry_number');
                        }

                        return $entry->load('lines.account');
                    } catch (UniqueConstraintViolationException $e) {
                        if ($pgsql) {
                            DB::statement('ROLLBACK TO SAVEPOINT ledger_entry_number');
                        } else {
                            throw $e;
                        }

                        if ($postingIdempotencyKey !== '' && $this->isPostgresDuplicateIdempotencyKey($e)) {
                            $existing = JournalEntry::query()
                                ->where('company_id', $companyId)
                                ->where('posting_idempotency_key', $postingIdempotencyKey)
                                ->first();
                            if ($existing) {
                                return $existing->load('lines.account');
                            }
                        }

                        if ($pgsql && $entryAttempt === 0 && $this->isJournalEntryNumberUniqueConstraintViolation($e)) {
                            Log::warning('ledger.post.entry_number_unique_retry', [
                                'company_id' => $companyId,
                                'trace_id' => $traceId,
                                'attempt' => $entryAttempt,
                            ]);
                            $this->repairJournalEntrySequenceAfterUniqueViolation((int) $companyId);

                            continue;
                        }

                        throw $e;
                    }
                }

                throw new \RuntimeException('Ledger journal entry was not created.');
            });
        } catch (QueryException $e) {
            if ($postingIdempotencyKey !== '' && $this->isPostgresDuplicateIdempotencyKey($e)) {
                $existing = JournalEntry::query()
                    ->where('company_id', $companyId)
                    ->where('posting_idempotency_key', $postingIdempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing->load('lines.account');
                }
            }

            throw $e;
        }
    }

    /**
     * Reverse an existing journal entry by creating a mirror entry with swapped debit/credit.
     */
    public function reverse(
        JournalEntry $originalEntry,
        string $reason,
        ?int $userId = null,
    ): JournalEntry {
        if ($originalEntry->reversed_by_entry_id) {
            throw new \DomainException("Entry {$originalEntry->entry_number} has already been reversed.");
        }

        $reversalLines = $originalEntry->lines->map(fn ($line) => [
            'account_code' => $line->account->code,
            'type' => $line->type === 'debit' ? 'credit' : 'debit',
            'amount' => $line->amount,
            'description' => "Reversal: {$line->description}",
        ])->toArray();

        $reversalTraceId = trim((string) ($originalEntry->trace_id ?? ''));
        if ($reversalTraceId === '') {
            $reversalTraceId = 'reversal-legacy:'.$originalEntry->id;
        }

        $reversalEntry = $this->post(
            companyId: $originalEntry->company_id,
            data: [
                'type' => JournalEntryType::Reversal->value,
                'description' => "Reversal of {$originalEntry->entry_number}: {$reason}",
                'source_type' => $originalEntry->source_type,
                'source_id' => $originalEntry->source_id,
                'entry_date' => now()->toDateString(),
                'lines' => $reversalLines,
                'currency' => $originalEntry->currency,
                'trace_id' => $reversalTraceId,
            ],
            branchId: $originalEntry->branch_id,
            userId: $userId,
        );

        // Link both entries
        $originalEntry->update(['reversed_by_entry_id' => $reversalEntry->id]);
        $reversalEntry->update(['reversed_entry_id' => $originalEntry->id]);

        return $reversalEntry;
    }

    /**
     * Get account balance for a specific account and company.
     */
    public function getAccountBalance(int $companyId, string $accountCode): float
    {
        $account = $this->resolveAccount($companyId, $accountCode);

        return $account->balance;
    }

    /**
     * Get trial balance for a company.
     */
    public function getTrialBalance(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->when($fromDate, fn ($q) => $q->where('journal_entries.entry_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('journal_entries.entry_date', '<=', $toDate))
            ->selectRaw('
                chart_of_accounts.id,
                chart_of_accounts.code,
                chart_of_accounts.name,
                chart_of_accounts.type,
                SUM(CASE WHEN journal_entry_lines.type = \'debit\'  THEN journal_entry_lines.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_entry_lines.type = \'credit\' THEN journal_entry_lines.amount ELSE 0 END) as total_credit
            ')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type')
            ->orderBy('chart_of_accounts.code')
            ->get();

        return $query->map(fn ($row) => [
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'total_debit' => (float) $row->total_debit,
            'total_credit' => (float) $row->total_credit,
            'balance' => (float) $row->total_debit - (float) $row->total_credit,
        ])->toArray();
    }

    private function resolveAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new \DomainException("Account code '{$code}' not found for company {$companyId}.");
        }

        return $account;
    }

    private function generateEntryNumber(int $companyId): string
    {
        if (DB::getDriverName() !== 'pgsql') {
            $last = JournalEntry::where('company_id', $companyId)
                ->orderByDesc('id')
                ->first();
            $seq = $last ? ((int) substr($last->entry_number, -8)) + 1 : 1;

            return sprintf('JE-%d-%08d', $companyId, $seq);
        }

        $sequenceName = $this->companyJournalEntrySequenceName($companyId);
        if (! $this->companyJournalEntrySequenceExists($sequenceName)) {
            $this->bootstrapCompanyJournalEntrySequenceSessionLocked((int) $companyId, $sequenceName);
        }

        // Hot path: nextval only — no MAX(journal_entries) and no setval here.
        $reg = $this->journalEntrySequenceRegclassLiteral($companyId);
        $row = DB::selectOne("SELECT nextval({$reg}::regclass) AS next_seq");
        $seq = (int) ($row->next_seq ?? 1);

        return sprintf('JE-%d-%08d', $companyId, $seq);
    }

    private function companyJournalEntrySequenceName(int $companyId): string
    {
        return 'journal_entry_company_'.$companyId.'_seq';
    }

    /**
     * Safe regclass literal for journal per-company sequence (name built from int company id only).
     */
    private function journalEntrySequenceRegclassLiteral(int $companyId): string
    {
        $name = $this->companyJournalEntrySequenceName((int) $companyId);

        return "'".str_replace("'", "''", $name)."'";
    }

    private function companyJournalEntrySequenceExists(string $sequenceName): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM pg_class c
                INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE c.relkind = 'S'
                  AND n.nspname = 'public'
                  AND c.relname = ?
            ) AS e
            SQL,
            [$sequenceName]
        );

        return (bool) ($row->e ?? false);
    }

    private function bootstrapCompanyJournalEntrySequenceSessionLocked(int $companyId, string $sequenceName): void
    {
        $this->withJournalEntrySequenceBootstrapSessionLock($companyId, function () use ($companyId, $sequenceName): void {
            DB::statement(sprintf('CREATE SEQUENCE IF NOT EXISTS "%s"', $sequenceName));
            $this->forwardCompanyJournalEntrySequenceToMaxObserved((int) $companyId, $sequenceName);
        });
    }

    private function repairJournalEntrySequenceAfterUniqueViolation(int $companyId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $sequenceName = $this->companyJournalEntrySequenceName($companyId);
        $this->withJournalEntrySequenceBootstrapSessionLock($companyId, function () use ($companyId, $sequenceName): void {
            DB::statement(sprintf('CREATE SEQUENCE IF NOT EXISTS "%s"', $sequenceName));
            $this->forwardCompanyJournalEntrySequenceToMaxObserved((int) $companyId, $sequenceName);
        });

        Log::info('ledger.journal_entry_sequence.repaired_after_unique_violation', [
            'company_id' => $companyId,
        ]);
    }

    /**
     * @param  callable():void  $fn
     */
    private function withJournalEntrySequenceBootstrapSessionLock(int $companyId, callable $fn): void
    {
        $k1 = self::ADVISORY_LOCK_JE_SEQ_K1;
        $k2 = (int) $companyId;
        DB::selectOne('SELECT pg_advisory_lock(?, ?)', [$k1, $k2]);
        try {
            $fn();
        } finally {
            DB::selectOne('SELECT pg_advisory_unlock(?, ?)', [$k1, $k2]);
        }
    }

    /**
     * Caller must hold the journal-entry sequence bootstrap session lock.
     */
    private function forwardCompanyJournalEntrySequenceToMaxObserved(int $companyId, string $sequenceName): void
    {
        $target = $this->maxJournalEntryCounterFromJournalEntriesTable((int) $companyId);
        if ($target <= 0) {
            return;
        }

        $seqLast = $this->readJournalEntrySequenceLastValue($sequenceName);
        if ($target <= $seqLast) {
            return;
        }

        $reg = $this->journalEntrySequenceRegclassLiteral($companyId);
        DB::selectOne(
            "SELECT setval({$reg}::regclass, GREATEST(?::bigint, 1), true)",
            [$target]
        );
    }

    private function readJournalEntrySequenceLastValue(string $sequenceName): int
    {
        $q = '"'.str_replace('"', '""', $sequenceName).'"';
        $row = DB::selectOne("SELECT last_value FROM {$q}");

        return (int) ($row->last_value ?? 0);
    }

    /**
     * Max numeric suffix for JE-{companyId}-NNNNNNNN rows. Cold bootstrap / 23505 repair only.
     */
    private function maxJournalEntryCounterFromJournalEntriesTable(int $companyId): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            return 0;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT COALESCE(MAX(x.seq), 0) AS m
            FROM (
                SELECT (regexp_match(entry_number, '^JE-[0-9]+-([0-9]+)$'))[1]::bigint AS seq
                FROM journal_entries
                WHERE company_id = ? AND entry_number IS NOT NULL
            ) AS x
            WHERE x.seq IS NOT NULL
            SQL,
            [$companyId]
        );

        return (int) ($row->m ?? 0);
    }

    private function isJournalEntryNumberUniqueConstraintViolation(UniqueConstraintViolationException $e): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'journal_entries_entry_number_unique')
            || str_contains($msg, 'journal_entries.entry_number');
    }

    private function isPostgresDuplicateIdempotencyKey(QueryException $e): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'ux_journal_entries_company_posting_idem');
    }
}
