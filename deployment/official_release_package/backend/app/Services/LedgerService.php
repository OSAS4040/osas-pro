<?php

namespace App\Services;

use App\Enums\JournalEntryType;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
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
     * } $data
     */
    public function post(
        int    $companyId,
        array  $data,
        ?int   $branchId = null,
        ?int   $userId   = null,
    ): JournalEntry {
        $traceId = trim((string) ($data['trace_id'] ?? ''));
        if ($traceId === '') {
            throw new \InvalidArgumentException('trace_id is required for journal posting.');
        }

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

        $totalDebit  = 0.0;
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

        return DB::transaction(function () use ($companyId, $branchId, $userId, $data, $totalDebit, $totalCredit, $lines, $traceId) {
            $entryNumber = $this->generateEntryNumber($companyId);

            $entry = JournalEntry::create([
                'uuid'             => Str::uuid(),
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'entry_number'     => $entryNumber,
                'type'             => $data['type'],
                'source_type'      => $data['source_type'] ?? null,
                'source_id'        => $data['source_id'] ?? null,
                'entry_date'       => $data['entry_date'] ?? now()->toDateString(),
                'description'      => $data['description'] ?? null,
                'total_debit'      => round($totalDebit, 4),
                'total_credit'     => round($totalCredit, 4),
                'currency'         => $data['currency'] ?? 'SAR',
                'trace_id'         => $traceId,
                'created_by_user_id' => $userId,
            ]);

            foreach ($lines as $line) {
                $account = $this->resolveAccount($companyId, $line['account_code']);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $account->id,
                    'type'             => $line['type'],
                    'amount'           => round((float) $line['amount'], 4),
                    'description'      => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Reverse an existing journal entry by creating a mirror entry with swapped debit/credit.
     */
    public function reverse(
        JournalEntry $originalEntry,
        string       $reason,
        ?int         $userId = null,
    ): JournalEntry {
        if ($originalEntry->reversed_by_entry_id) {
            throw new \DomainException("Entry {$originalEntry->entry_number} has already been reversed.");
        }

        $reversalLines = $originalEntry->lines->map(fn($line) => [
            'account_code' => $line->account->code,
            'type'         => $line->type === 'debit' ? 'credit' : 'debit',
            'amount'       => $line->amount,
            'description'  => "Reversal: {$line->description}",
        ])->toArray();

        $reversalTraceId = trim((string) ($originalEntry->trace_id ?? ''));
        if ($reversalTraceId === '') {
            $reversalTraceId = 'reversal-legacy:'.$originalEntry->id;
        }

        $reversalEntry = $this->post(
            companyId: $originalEntry->company_id,
            data: [
                'type'        => JournalEntryType::Reversal->value,
                'description' => "Reversal of {$originalEntry->entry_number}: {$reason}",
                'source_type' => $originalEntry->source_type,
                'source_id'   => $originalEntry->source_id,
                'entry_date'  => now()->toDateString(),
                'lines'       => $reversalLines,
                'currency'    => $originalEntry->currency,
                'trace_id'    => $reversalTraceId,
            ],
            branchId: $originalEntry->branch_id,
            userId:   $userId,
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
            ->when($fromDate, fn($q) => $q->where('journal_entries.entry_date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('journal_entries.entry_date', '<=', $toDate))
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

        return $query->map(fn($row) => [
            'code'         => $row->code,
            'name'         => $row->name,
            'type'         => $row->type,
            'total_debit'  => (float) $row->total_debit,
            'total_credit' => (float) $row->total_credit,
            'balance'      => (float) $row->total_debit - (float) $row->total_credit,
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
        $last = JournalEntry::where('company_id', $companyId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->entry_number, -8)) + 1 : 1;

        return sprintf('JE-%d-%08d', $companyId, $seq);
    }
}
