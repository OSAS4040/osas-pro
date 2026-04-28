<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $companyIds = DB::table('journal_entries')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $companyId = (int) $companyId;
            if ($companyId <= 0) {
                continue;
            }

            $agg = DB::selectOne(
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
            $maxSeq = (int) ($agg->m ?? 0);

            $seqName = 'journal_entry_company_'.$companyId.'_seq';
            DB::statement(sprintf('CREATE SEQUENCE IF NOT EXISTS "%s"', $seqName));
            if ($maxSeq > 0) {
                DB::selectOne(
                    'SELECT setval(?::regclass, GREATEST(?::bigint, 1), true)',
                    [$seqName, $maxSeq]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep per-company sequences to avoid journal entry number regression.
    }
};
