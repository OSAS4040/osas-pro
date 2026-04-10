<?php

namespace App\Providers;

use App\Queue\Failed\DedupingDatabaseUuidFailedJobProvider;
use App\Support\Observability\LedgerAlertWebhookNotifier;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Wrap framework queue.failer: database-uuids must upsert by payload UUID so workers do not
     * throw UniqueConstraintViolationException when the same failing job is recorded twice.
     */
    public function register(): void
    {
        $this->app->extend('queue.failer', function ($failer, $app) {
            $config = $app['config']['queue.failed'];

            if (($config['driver'] ?? null) !== 'database-uuids') {
                return $failer;
            }

            return new DedupingDatabaseUuidFailedJobProvider(
                $app['db'],
                $config['database'],
                $config['table']
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            $this->configureTestingDatabase();
        } else {
            $this->registerLedgerAlertWebhookForwarding();
        }
    }

    /**
     * Optional: POST JSON to LEDGER_ALERT_WEBHOOK_URL when ledger posting alerts are logged (production/staging).
     */
    private function registerLedgerAlertWebhookForwarding(): void
    {
        $url = config('ledger_alerts.webhook_url');
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            if ($event->level !== 'critical' || $event->message !== LedgerAlertWebhookNotifier::LOG_MESSAGE) {
                return;
            }

            app(LedgerAlertWebhookNotifier::class)->notifyFromLogContext($event->context);
        });
    }

    /**
     * Force unit/feature tests onto isolated infra so local runtime data
     * (including login accounts) is never modified by RefreshDatabase.
     */
    private function configureTestingDatabase(): void
    {
        $dbHost = $this->resolveTestDatabaseHost();
        $dbPort = $this->nonEmptyEnv('TEST_DB_PORT') ?? env('DB_PORT', '5432');
        $dbName = $this->nonEmptyEnv('TEST_DB_DATABASE') ?? 'saas_test';
        $dbUser = $this->nonEmptyEnv('TEST_DB_USERNAME') ?? env('DB_USERNAME', 'saas_user');
        $dbPass = $this->nonEmptyEnv('TEST_DB_PASSWORD') ?? env('DB_PASSWORD', 'saas_password');

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => $dbHost,
            'database.connections.pgsql.port' => $dbPort,
            'database.connections.pgsql.database' => $dbName,
            'database.connections.pgsql.username' => $dbUser,
            'database.connections.pgsql.password' => $dbPass,
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);

        DB::purge('pgsql');
        DB::reconnect('pgsql');

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        } catch (\Throwable) {
            // Best-effort for local test DB bootstrap.
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        } catch (\Throwable) {
            // Fallback function if uuid-ossp is unavailable in this environment.
            DB::statement('CREATE OR REPLACE FUNCTION uuid_generate_v4() RETURNS uuid AS $$ SELECT gen_random_uuid(); $$ LANGUAGE SQL');
        }
    }

    private function nonEmptyEnv(string $key): ?string
    {
        $v = env($key);

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Inside Docker, loopback in TEST_DB_HOST / .env usually points at the container itself, not Postgres.
     */
    private function resolveTestDatabaseHost(): string
    {
        $inDocker = file_exists('/.dockerenv');
        $configured = $this->nonEmptyEnv('TEST_DB_HOST');

        if ($configured !== null) {
            if ($inDocker && in_array($configured, ['127.0.0.1', 'localhost', '::1'], true)) {
                return 'postgres';
            }

            return $configured;
        }

        if ($inDocker) {
            return 'postgres';
        }

        return env('DB_HOST', 'postgres');
    }
}
