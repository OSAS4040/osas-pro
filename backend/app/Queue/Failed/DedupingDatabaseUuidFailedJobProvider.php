<?php

namespace App\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Date;

/**
 * Avoids UniqueConstraintViolationException when the worker retries logging the same
 * failing Redis job UUID (payload uuid matches an existing failed_jobs row).
 */
class DedupingDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    public function log($connection, $queue, $payload, $exception)
    {
        $uuid = json_decode($payload, true)['uuid'];

        $this->getTable()->updateOrInsert(
            ['uuid' => $uuid],
            [
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
                'failed_at' => Date::now(),
            ]
        );

        return $uuid;
    }
}
