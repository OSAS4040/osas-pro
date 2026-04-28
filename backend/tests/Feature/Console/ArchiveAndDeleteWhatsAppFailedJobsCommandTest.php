<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveAndDeleteWhatsAppFailedJobsCommandTest extends TestCase
{
    public function test_archives_then_deletes_whatsapp_failed_jobs_on_apply(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid'        => $uuid,
            'connection'  => 'redis',
            'queue'       => 'default',
            'payload'     => '{"displayName":"App\\\\Jobs\\\\NotifyCustomerWorkOrderWhatsAppJob","uuid":"'.$uuid.'"}',
            'exception'   => 'test exception',
            'failed_at'   => now(),
        ]);

        DB::table('failed_jobs')->insert([
            'uuid'        => (string) Str::uuid(),
            'connection'  => 'redis',
            'queue'       => 'default',
            'payload'     => '{"displayName":"App\\\\Jobs\\\\SomeOtherJob"}',
            'exception'   => 'other',
            'failed_at'   => now(),
        ]);

        $this->assertSame(2, (int) DB::table('failed_jobs')->count());

        Artisan::call('whatsapp:archive-and-delete-failed-jobs', [
            '--apply'  => true,
            '--reason' => 'whatsapp stale cleanup',
        ]);

        $this->assertSame(1, (int) DB::table('failed_jobs')->count());
        $this->assertSame(1, (int) DB::table('failed_jobs_archive')->count());
        $this->assertSame($uuid, (string) DB::table('failed_jobs_archive')->value('uuid'));
        $this->assertSame('whatsapp stale cleanup', (string) DB::table('failed_jobs_archive')->value('archive_reason'));
    }
}
