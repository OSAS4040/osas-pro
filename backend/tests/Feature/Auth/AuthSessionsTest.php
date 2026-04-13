<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuthLoginEvent;
use App\Models\AuthPersonalAccessToken;
use App\Services\Auth\AuthSessionMetadataWriter;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthSessionsTest extends TestCase
{
    public function test_login_success_writes_audit_and_token_metadata(): void
    {
        $tenant = $this->createTenant('owner');
        $user    = $tenant['user'];

        $before = AuthLoginEvent::query()->count();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ], [
            'User-Agent' => 'PHPUnitAuthSessions/1.0',
        ])->assertOk();

        $this->assertSame($before + 1, AuthLoginEvent::query()->count());

        $event = AuthLoginEvent::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('login_success', $event->event);
        $this->assertSame('password', $event->auth_channel);
        $this->assertNotNull($event->token_id);

        $token = AuthPersonalAccessToken::query()->find($event->token_id);
        $this->assertNotNull($token);
        $this->assertSame('password', $token->auth_channel);
        $this->assertNotNull($token->user_agent_summary);
    }

    public function test_login_denied_writes_audit_without_token(): void
    {
        $tenant = $this->createTenant('owner');
        $user    = $tenant['user'];

        $before = AuthLoginEvent::query()->count();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ])->assertUnauthorized();

        $this->assertSame($before + 1, AuthLoginEvent::query()->count());
        $event = AuthLoginEvent::query()->where('event', 'login_denied')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('invalid_credentials', $event->reason_code);
        $this->assertNull($event->token_id);
    }

    public function test_sessions_list_scoped_to_authenticated_user_and_marks_current(): void
    {
        $tenant = $this->createTenant('owner');
        $user   = $tenant['user'];

        $login1 = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ], ['User-Agent' => 'Device-A'])->assertOk();
        $token1 = (string) $login1->json('token');

        $login2 = $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password123!',
            'device_name' => 'other-device',
        ], ['User-Agent' => 'Device-B'])->assertOk();
        $token2 = (string) $login2->json('token');

        $id1 = (int) explode('|', $token1, 2)[0];
        $id2 = (int) explode('|', $token2, 2)[0];
        $this->assertNotSame($id1, $id2);

        $res = $this->withoutToken()->withToken($token2)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk();

        $rows = $res->json('data');
        $this->assertCount(2, $rows);
        $currentRows = array_values(array_filter($rows, static fn (array $r): bool => ($r['is_current'] ?? false) === true));
        $this->assertCount(1, $currentRows);
        $this->assertSame($id2, $currentRows[0]['id']);
        $this->assertSame('other-device', $currentRows[0]['device_name']);

        $rows1 = $this->withoutToken()->withToken($token1)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->json('data');
        $current1 = array_values(array_filter($rows1, static fn (array $r): bool => ($r['is_current'] ?? false) === true));
        $this->assertCount(1, $current1);
        $this->assertSame($id1, $current1[0]['id']);
        $this->assertSame('web-spa', $current1[0]['device_name']);
    }

    public function test_cannot_list_or_revoke_another_users_session(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $tokenA = (string) $this->postJson('/api/v1/auth/login', [
            'email'    => $a['user']->email,
            'password' => 'Password123!',
        ])->assertOk()->json('token');

        $tokenB = (string) $this->postJson('/api/v1/auth/login', [
            'email'    => $b['user']->email,
            'password' => 'Password123!',
        ])->assertOk()->json('token');

        $foreignId = AuthPersonalAccessToken::query()
            ->where('tokenable_id', $b['user']->id)
            ->value('id');

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson('/api/v1/auth/sessions/'.$foreignId)
            ->assertStatus(404);
    }

    public function test_cannot_revoke_current_session_via_delete_endpoint(): void
    {
        $tenant = $this->createTenant('owner');
        $user   = $tenant['user'];

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ])->assertOk()->json('token');

        $currentId = AuthPersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->orderByDesc('id')
            ->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/sessions/'.$currentId)
            ->assertStatus(422);
    }

    public function test_revoke_specific_non_current_session_and_audit(): void
    {
        $tenant = $this->createTenant('owner');
        $user   = $tenant['user'];

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ], ['User-Agent' => 'First'])->assertOk();

        $token2 = (string) $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password123!',
            'device_name' => 'second',
        ], ['User-Agent' => 'Second'])->assertOk()->json('token');

        $ids = AuthPersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $this->assertCount(2, $ids);
        $olderId = (int) $ids[0];

        $before = AuthLoginEvent::query()->where('event', 'revoke_session')->count();

        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->deleteJson('/api/v1/auth/sessions/'.$olderId)
            ->assertOk();

        $this->assertSame($before + 1, AuthLoginEvent::query()->where('event', 'revoke_session')->count());
        $this->assertSame(1, AuthPersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_revoke_others_keeps_only_current_and_writes_audit(): void
    {
        $tenant = $this->createTenant('owner');
        $user   = $tenant['user'];

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ])->assertOk();

        $tokenCurrent = (string) $this->postJson('/api/v1/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password123!',
            'device_name' => 'keeper',
        ])->assertOk()->json('token');

        $before = AuthLoginEvent::query()->where('event', 'revoke_other_sessions')->count();

        $this->withHeader('Authorization', 'Bearer '.$tokenCurrent)
            ->postJson('/api/v1/auth/sessions/revoke-others', [])
            ->assertOk();

        $this->assertSame($before + 1, AuthLoginEvent::query()->where('event', 'revoke_other_sessions')->count());
        $this->assertSame(1, AuthPersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        $this->withHeader('Authorization', 'Bearer '.$tokenCurrent)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.device_name', 'keeper');
    }

    public function test_logout_success_records_audit(): void
    {
        $tenant = $this->createTenant('owner');
        $user   = $tenant['user'];

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ])->assertOk()->json('token');

        $tid = AuthPersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->orderByDesc('id')
            ->value('id');

        $before = AuthLoginEvent::query()->where('event', 'logout_session')->count();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout', [])
            ->assertOk();

        $this->assertSame($before + 1, AuthLoginEvent::query()->where('event', 'logout_session')->count());
        $last = AuthLoginEvent::query()->where('event', 'logout_session')->latest('id')->first();
        $this->assertSame((int) $tid, (int) $last->token_id);
        $this->assertSame(0, AuthPersonalAccessToken::query()->where('id', $tid)->count());
    }

    public function test_manual_token_gets_metadata_via_writer_like_otp_channel(): void
    {
        $tenant = $this->createTenant('owner');
        $user   = $tenant['user'];

        $plain = $user->createToken('otp-device', ['*'])->plainTextToken;
        $pat   = $user->tokens()->latest('id')->first();
        $this->assertNotNull($pat);

        $req = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'OtpTestAgent/2.0',
            'REMOTE_ADDR'     => '203.0.113.10',
        ]);
        app(AuthSessionMetadataWriter::class)->apply($pat, $req, 'otp_phone');
        $pat->refresh();

        $this->assertSame('otp_phone', $pat->auth_channel);
        $this->assertStringContainsString('203.0.113.10', (string) $pat->ip_address);
        $this->assertNotNull($pat->user_agent_summary);
    }

    public function test_sessions_response_only_includes_tokens_for_authenticated_user(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $this->postJson('/api/v1/auth/login', [
            'email'    => $b['user']->email,
            'password' => 'Password123!',
        ])->assertOk();

        $tokenA = (string) $this->postJson('/api/v1/auth/login', [
            'email'    => $a['user']->email,
            'password' => 'Password123!',
        ])->assertOk()->json('token');

        $count = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $count);
    }
}
