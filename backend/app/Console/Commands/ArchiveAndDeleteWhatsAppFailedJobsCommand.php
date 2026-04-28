<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archives then deletes failed_jobs rows whose payload is NotifyCustomerWorkOrderWhatsAppJob only.
 * Does not touch other failed job types, ledger, or queue data.
 */
class ArchiveAndDeleteWhatsAppFailedJobsCommand extends Command
{
    private const PAYLOAD_LIKE = '%NotifyCustomerWorkOrderWhatsAppJob%';

    protected $signature = 'whatsapp:archive-and-delete-failed-jobs
                            {--apply : Insert into failed_jobs_archive then delete from failed_jobs}
                            {--reason=whatsapp stale cleanup : Value stored in archive_reason}';

    protected $description = 'Archive NotifyCustomerWorkOrderWhatsAppJob rows from failed_jobs, then delete them (dry-run by default)';

    public function handle(): int
    {
        if (! Schema::hasTable('failed_jobs_archive')) {
            $this->error('Table failed_jobs_archive is missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $reason = (string) $this->option('reason');

        $count = (int) DB::table('failed_jobs')
            ->where('payload', 'like', self::PAYLOAD_LIKE)
            ->count();

        $this->info('Matching failed_jobs (WhatsApp work order job): '.$count);
        $this->line('Archive reason: '.$reason);
        $this->line('Mode: '.($apply ? 'APPLY (archive + delete)' : 'dry-run (counts only)'));

        if (! $apply || $count === 0) {
            return self::SUCCESS;
        }

        $archivedAt = Carbon::now();

        DB::transaction(function () use ($reason, $archivedAt): void {
            DB::insert(
                'INSERT INTO failed_jobs_archive (uuid, connection, queue, payload, exception, failed_at, archived_at, archive_reason)
                 SELECT uuid, connection, queue, payload, exception, failed_at, ?, ?
                 FROM failed_jobs
                 WHERE payload LIKE ?',
                [$archivedAt, $reason, self::PAYLOAD_LIKE]
            );

            DB::table('failed_jobs')
                ->where('payload', 'like', self::PAYLOAD_LIKE)
                ->delete();
        });

        $remaining = (int) DB::table('failed_jobs')
            ->where('payload', 'like', self::PAYLOAD_LIKE)
            ->count();

        $this->info('Archived + deleted rows: '.$count);
        $this->info('Remaining matching failed_jobs: '.$remaining.' (expected 0)');

        return self::SUCCESS;
    }
}
