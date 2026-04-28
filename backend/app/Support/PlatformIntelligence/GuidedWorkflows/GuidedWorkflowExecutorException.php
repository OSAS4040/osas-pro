<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\GuidedWorkflows;

use RuntimeException;

final class GuidedWorkflowExecutorException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
