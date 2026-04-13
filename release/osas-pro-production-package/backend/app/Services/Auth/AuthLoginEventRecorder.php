<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AuthLoginEvent;
use App\Models\User;
use App\Support\Auth\UserAgentSummarizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Append-only auth audit trail (separate from tenant audit_logs which require company_id).
 */
final class AuthLoginEventRecorder
{
    /** After one "table missing" failure, skip further inserts in this PHP process (avoids log spam). */
    private static bool $skipAuthLoginAudit = false;

    private static bool $missingTableLogged = false;

    public function loginSuccess(User $user, ?PersonalAccessToken $token, string $authChannel, Request $request): void
    {
        $this->insert($user, 'login_success', $authChannel, null, $token?->id, $request);
    }

    public function loginDenied(?User $user, string $reasonCode, string $authChannel, Request $request): void
    {
        $this->insert($user, 'login_denied', $authChannel, $reasonCode, null, $request);
    }

    public function logoutSession(User $user, ?PersonalAccessToken $token, Request $request): void
    {
        $this->insert($user, 'logout_session', null, null, $token?->id, $request);
    }

    public function logoutAll(User $user, Request $request): void
    {
        $this->insert($user, 'logout_all', null, null, null, $request);
    }

    public function revokeOtherSessions(User $user, Request $request): void
    {
        $this->insert($user, 'revoke_other_sessions', null, null, null, $request);
    }

    public function revokeSession(User $user, int $revokedTokenId, Request $request): void
    {
        $this->insert($user, 'revoke_session', null, null, $revokedTokenId, $request);
    }

    private function insert(
        ?User $user,
        string $event,
        ?string $authChannel,
        ?string $reasonCode,
        ?int $tokenId,
        Request $request,
    ): void {
        if (self::$skipAuthLoginAudit) {
            return;
        }

        $summary = UserAgentSummarizer::summarize((string) $request->userAgent());

        try {
            AuthLoginEvent::query()->create([
                'user_id'              => $user?->id,
                'company_id'           => $user?->company_id,
                'event'                => $event,
                'auth_channel'         => $authChannel,
                'reason_code'          => $reasonCode,
                'token_id'             => $tokenId,
                'ip_address'           => $request->ip(),
                'user_agent_summary'   => $summary,
                'trace_id'             => app()->bound('trace_id') ? (string) app('trace_id') : null,
            ]);
        } catch (QueryException $e) {
            if (! $this->isMissingAuthLoginEventsTable($e)) {
                throw $e;
            }
            $this->markAuthLoginAuditUnavailable();
        } catch (Throwable $e) {
            if (! $this->isMissingAuthLoginEventsTable($e)) {
                throw $e;
            }
            $this->markAuthLoginAuditUnavailable();
        }
    }

    private function markAuthLoginAuditUnavailable(): void
    {
        self::$skipAuthLoginAudit = true;
        if (! self::$missingTableLogged) {
            self::$missingTableLogged = true;
            Log::warning('Auth login audit skipped: relation auth_login_events is missing or inaccessible. Run: php artisan migrate');
        }
    }

    /**
     * Match PDO / Laravel across drivers (pgsql 42P01, mysql 42S02, message text).
     */
    private function isMissingAuthLoginEventsTable(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $state = (string) ($e->errorInfo[0] ?? '');
            if (in_array($state, ['42P01', '42S02'], true)) {
                $msg = strtolower($e->getMessage());

                return str_contains($msg, 'auth_login_events');
            }
        }

        $msg = strtolower($e->getMessage());
        if (! str_contains($msg, 'auth_login_events')) {
            return false;
        }

        return str_contains($msg, '42p01')
            || str_contains($msg, '42s02')
            || str_contains($msg, 'does not exist')
            || str_contains($msg, "doesn't exist")
            || str_contains($msg, 'undefined table')
            || str_contains($msg, 'no such table');
    }
}
