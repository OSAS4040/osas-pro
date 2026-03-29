<?php

namespace Tests\Feature\Integration;

use App\Jobs\DispatchWebhookJob;
use App\Models\ApiKey;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiKeyAuthTest extends TestCase
{
    private array  $tenant;
    private string $rawSecret;
    private ApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant    = $this->createTenant();
        $this->rawSecret = Str::random(64);

        $this->apiKey = ApiKey::create([
            'key_id'             => (string) Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'created_by_user_id' => $this->tenant['user']->id,
            'name'               => 'Test Key',
            'secret_hash'        => hash('sha256', $this->rawSecret),
            'permissions_scope'  => ['invoices.read', 'invoices.write'],
            'rate_limit'         => 1000,
        ]);
    }

    public function test_missing_authorization_header_returns_401(): void
    {
        $this->getJson('/api/v1/external/v1/invoices/some-uuid')
            ->assertStatus(401)
            ->assertJson(['message' => 'API key required.']);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer wrong_key'])
            ->getJson('/api/v1/external/v1/invoices/some-uuid')
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid or expired API key.']);
    }

    public function test_revoked_api_key_is_rejected(): void
    {
        $this->apiKey->update(['revoked_at' => now()]);

        $this->withHeaders(['Authorization' => "Bearer {$this->rawSecret}"])
            ->getJson('/api/v1/external/v1/invoices/some-uuid')
            ->assertStatus(401);
    }

    public function test_expired_api_key_is_rejected(): void
    {
        $this->apiKey->update(['expires_at' => now()->subMinute()]);

        $this->withHeaders(['Authorization' => "Bearer {$this->rawSecret}"])
            ->getJson('/api/v1/external/v1/invoices/some-uuid')
            ->assertStatus(401);
    }

    public function test_api_key_creation_returns_secret_once(): void
    {
        $response = $this->actingAsUser($this->tenant['user'])
            ->postJson('/api/v1/api-keys', [
                'name'       => 'My Integration Key',
                'rate_limit' => 500,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['key_id', 'name'], 'secret', 'message']);

        $this->assertNotEmpty($response->json('secret'));
    }
}

class WebhookSignatureTest extends TestCase
{
    public function test_sign_generates_valid_hmac_signature(): void
    {
        $signature = WebhookService::sign('my_secret', ['id' => 1], time());

        $this->assertStringStartsWith('t=', $signature);
        $this->assertStringContainsString(',v1=', $signature);
    }

    public function test_verify_accepts_valid_signature(): void
    {
        $ts        = time();
        $payload   = ['event' => 'invoice.created', 'id' => 1];
        $signature = WebhookService::sign('my_secret', $payload, $ts);

        $this->assertTrue(WebhookService::verify('my_secret', $payload, $signature));
    }

    public function test_verify_rejects_tampered_payload(): void
    {
        $ts        = time();
        $payload   = ['event' => 'invoice.created', 'id' => 1];
        $signature = WebhookService::sign('my_secret', $payload, $ts);

        $this->assertFalse(WebhookService::verify('my_secret', ['id' => 999], $signature));
    }

    public function test_verify_rejects_expired_timestamp(): void
    {
        $payload   = ['event' => 'test'];
        $old_ts    = time() - 400;
        $signature = WebhookService::sign('my_secret', $payload, $old_ts);

        $this->assertFalse(WebhookService::verify('my_secret', $payload, $signature, 300));
    }

    public function test_dispatch_queues_job_for_subscribed_endpoints(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();

        WebhookEndpoint::create([
            'uuid'                => Str::uuid(),
            'company_id'          => $tenant['company']->id,
            'created_by_user_id'  => $tenant['user']->id,
            'url'                 => 'https://example.com/hook',
            'events'              => ['invoice.created'],
            'secret_hash'         => hash('sha256', 'secret'),
            'is_active'           => true,
        ]);

        app(WebhookService::class)->dispatch(
            $tenant['company']->id, 'invoice.created', ['id' => 1], 'trace-001'
        );

        Queue::assertPushed(DispatchWebhookJob::class, 1);
    }

    public function test_dispatch_skips_non_subscribed_events(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();

        WebhookEndpoint::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'created_by_user_id' => $tenant['user']->id,
            'url'                => 'https://example.com/hook',
            'events'             => ['payment.created'],
            'secret_hash'        => hash('sha256', 'secret'),
            'is_active'          => true,
        ]);

        app(WebhookService::class)->dispatch(
            $tenant['company']->id, 'invoice.created', ['id' => 1], 'trace-002'
        );

        Queue::assertNotPushed(DispatchWebhookJob::class);
    }
}

class TraceIdTest extends TestCase
{
    public function test_trace_id_is_generated_and_returned_in_response_header(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAsUser($tenant['user'])->getJson('/api/v1/auth/me');

        $response->assertHeader('X-Trace-Id');
        $this->assertNotEmpty($response->headers->get('X-Trace-Id'));
    }

    public function test_trace_id_in_request_is_propagated_to_response(): void
    {
        $tenant  = $this->createTenant();
        $myTrace = 'custom-trace-12345';

        $response = $this->actingAsUser($tenant['user'])
            ->withHeaders(['X-Trace-Id' => $myTrace])
            ->getJson('/api/v1/auth/me');

        $response->assertHeader('X-Trace-Id', $myTrace);
    }

    public function test_trace_id_included_in_401_error_response(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertNotEmpty($response->json('trace_id'));
    }
}
