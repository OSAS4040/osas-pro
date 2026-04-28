<?php

namespace App\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Avoids UniqueConstraintViolationException when the worker retries logging the same
 * failing Redis job UUID (payload uuid matches an existing failed_jobs row).
 */
class DedupingDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    public function log($connection, $queue, $payload, $exception)
    {
        $uuid = json_decode($payload, true)['uuid'];

        $incoming = (string) mb_convert_encoding($exception, 'UTF-8');
        $existing = $this->getTable()->where('uuid', $uuid)->value('exception');
        $existingStr = is_string($existing) ? $existing : '';

        // Preserve the first non–max-attempts exception: the final write is often only MaxAttemptsExceededException.
        if ($existingStr !== ''
            && Str::contains($incoming, 'MaxAttemptsExceededException')
            && ! Str::contains($existingStr, 'MaxAttemptsExceededException')) {
            $incoming = $existingStr;
        }

        $this->getTable()->updateOrInsert(
            ['uuid' => $uuid],
            [
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => $incoming,
                'failed_at' => Date::now(),
            ]
        );

        return $uuid;
    }
}
