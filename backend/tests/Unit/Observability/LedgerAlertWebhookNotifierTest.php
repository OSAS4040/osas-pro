<?php

namespace Tests\Unit\Observability;

use App\Support\Observability\LedgerAlertWebhookNotifier;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LedgerAlertWebhookNotifierTest extends TestCase
{
    public function test_skips_http_when_webhook_url_empty(): void
    {
        Http::fake();
        Config::set('ledger_alerts.webhook_url', '');

        (new LedgerAlertWebhookNotifier())->notifyFromLogContext([
            'code'       => 'LEDGER_POST_FAILED',
            'company_id' => 1,
        ]);

        Http::assertNothingSent();
    }

    public function test_posts_json_payload_to_webhook(): void
    {
        Config::set('ledger_alerts.webhook_url', 'https://hooks.example.test/ledger');
        Config::set('ledger_alerts.webhook_secret', 'test-secret');
        Config::set('ledger_alerts.timeout_seconds', 5);

        Http::fake(['https://hooks.example.test/*' => Http::response([], 200)]);

        (new LedgerAlertWebhookNotifier())->notifyFromLogContext([
            'code'             => 'LEDGER_POST_FAILED',
            'source'           => 'pos',
            'company_id'       => 7,
            'invoice_id'       => 42,
            'trace_id'         => 'trace-abc',
            'previous_class'   => 'RuntimeException',
            'previous_message' => 'should not appear in body',
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->url() !== 'https://hooks.example.test/ledger') {
                return false;
            }
            $sig = $request->header('X-Ledger-Alert-Signature')[0] ?? '';
            $body = $request->body();
            $expected = hash_hmac('sha256', $body, 'test-secret');

            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $sig === $expected
                && ($data['event'] ?? null) === LedgerAlertWebhookNotifier::LOG_MESSAGE
                && ($data['context']['company_id'] ?? null) === 7
                && ($data['context']['invoice_id'] ?? null) === 42
                && ! isset($data['context']['previous_message']);
        });
    }
}
