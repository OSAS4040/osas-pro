<?php

namespace App\Services\Finance\Exceptions;

use RuntimeException;

class ReconciliationConcurrencyBlockedException extends RuntimeException
{
    public function __construct(public readonly int $runningRunId, string $message)
    {
        parent::__construct($message);
    }
}
