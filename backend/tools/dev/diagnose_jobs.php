<?php

declare(strict_types=1);

use App\Jobs\CheckSubscriptionStatusJob;
use App\Jobs\ExpireIdempotencyKeysJob;
use App\Jobs\ExpireInventoryReservationsJob;
use App\Jobs\PostPosLedgerJob;
use App\Jobs\SendDocumentExpiryNotificationsJob;
use App\Services\LedgerService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = (string)($argv[1] ?? '');
$invoiceId = (int)($argv[2] ?? 0);
$traceId = (string)($argv[3] ?? 'diag-trace');

if ($mode === '') {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php tools/dev/diagnose_jobs.php postpos <invoiceId> [traceId]\n");
    fwrite(STDERR, "  php tools/dev/diagnose_jobs.php expire-idempotency\n");
    fwrite(STDERR, "  php tools/dev/diagnose_jobs.php expire-reservations\n");
    fwrite(STDERR, "  php tools/dev/diagnose_jobs.php check-subscriptions\n");
    fwrite(STDERR, "  php tools/dev/diagnose_jobs.php send-doc-expiry\n");
    exit(2);
}

try {
    switch ($mode) {
        case 'postpos':
            if ($invoiceId <= 0) {
                throw new InvalidArgumentException('invoiceId is required for postpos mode');
            }
            $job = new PostPosLedgerJob($invoiceId, $traceId);
            $job->handle($app->make(LedgerService::class));
            break;
        case 'expire-idempotency':
            (new ExpireIdempotencyKeysJob())->handle();
            break;
        case 'expire-reservations':
            (new ExpireInventoryReservationsJob())->handle();
            break;
        case 'check-subscriptions':
            (new CheckSubscriptionStatusJob())->handle();
            break;
        case 'send-doc-expiry':
            (new SendDocumentExpiryNotificationsJob())->handle($app->make(\App\Services\AlertService::class));
            break;
        default:
            throw new InvalidArgumentException("Unknown mode: {$mode}");
    }

    fwrite(STDOUT, "OK\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
