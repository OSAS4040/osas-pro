<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight integrity checks (no ledger / journal / wallet mutations).
 */
final class IntegrityVerifyCommand extends Command
{
    protected $signature = 'integrity:sanity {--quiet-check : Minimal output}';

    protected $description = 'Lightweight checks: DB connectivity, platform IAM columns, platform_audit_logs table (no ledger reads).';

    public function handle(): int
    {
        try {
            DB::select('select 1 as ok');
        } catch (\Throwable $e) {
            $this->error('Database connectivity failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! Schema::hasTable('users')) {
            $this->error('Missing users table.');

            return self::FAILURE;
        }

        foreach (['is_platform_user', 'platform_role'] as $col) {
            if (! Schema::hasColumn('users', $col)) {
                $this->error("Missing users.{$col} — run migrations.");

                return self::FAILURE;
            }
        }

        if (! Schema::hasTable('platform_audit_logs')) {
            $this->error('Missing platform_audit_logs — run migrations.');

            return self::FAILURE;
        }

        if (! $this->option('quiet-check')) {
            $this->info('integrity:sanity passed (DB + platform IAM tables).');
        }

        return self::SUCCESS;
    }
}
