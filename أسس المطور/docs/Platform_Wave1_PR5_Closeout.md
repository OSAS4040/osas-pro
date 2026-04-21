# WAVE 1 — PR5 Closeout: Rate Limiting & Suspicious Login Signals

## Executive summary

PR5 adds named HTTP rate limiters for password login and phone OTP (request + verify), structured `429` responses with `message_key` / `reason_code`, cache-backed failed-attempt counters with threshold-based **append-only** suspicious signal rows, and a read-only internal listing endpoint. Tokens are never returned on `429` or on existing `401`/`422` auth rejection paths covered here.

## Modified / added files

- `backend/config/auth_security.php` (new)
- `backend/database/migrations/2026_04_12_210000_create_auth_suspicious_login_signals_table.php` (new)
- `backend/app/Models/AuthSuspiciousLoginSignal.php` (new)
- `backend/app/Services/Auth/AuthSecurityTelemetryService.php` (new)
- `backend/app/Http/Controllers/Api/V1/Internal/AuthSuspiciousLoginSignalsController.php` (new)
- `backend/app/Providers/AppServiceProvider.php` (limiters: `login`, `otp-verify`, `otp-resend`)
- `backend/routes/api.php` (middleware + internal GET)
- `backend/bootstrap/app.php` (`429` JSON contract + telemetry hook)
- `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` (failed-password telemetry)
- `backend/app/Http/Controllers/Api/V1/Auth/PhoneOtpAuthController.php` (`message_key` / `reason_code` on verify failures + OTP telemetry)
- `backend/tests/Feature/Auth/AuthSecurityPr5Test.php` (new)
- `backend/tests/Feature/Auth/PhoneRegistrationFlowTest.php` (assertions aligned with OTP verify contract)

## Migrations

- `2026_04_12_210000_create_auth_suspicious_login_signals_table.php` — table `auth_suspicious_login_signals` (append-only, `created_at` only).

Run: `php artisan migrate`

## Functional changes

- **Rate limiting:** `POST /api/v1/auth/login` uses `throttle:login` with per-minute limit from `auth_security.login.per_minute` when set, else `saas.login_rate_limit_per_minute`. `POST .../phone/request-otp` → `throttle:otp-resend`; `POST .../phone/verify-otp` → `throttle:otp-verify` (keys: IP + normalized phone, configurable per minute).
- **429 contract (JSON API):** `message`, `message_key` (`auth.security.rate_limited`), `reason_code` (`RATE_LIMITED`), `trace_id`, plus throttle headers when applicable.
- **Failed attempts:** in-memory/cache counters for password failures and failed OTP verifies; sliding window TTL from config.
- **Suspicious signals (minimal):** DB rows for `failed_login_burst`, `failed_otp_verify_burst`, and debounced `rate_limited_*` per endpoint kind.
- **No token on throttle/rejection:** unchanged for `401` invalid password; `422` OTP errors and `429` throttles omit `token`.
- **Internal visibility:** `GET /api/v1/internal/auth/suspicious-login-signals` (same middleware stack as other internal QA routes) returns masked fingerprints and metadata — read-only.

## Out of scope (unchanged)

- No 2FA, geo engine, advanced device trust, autonomous blocking, impersonation, financial modules, or `customers` changes.
- No change to eligibility resolution rules beyond existing responses; phone eligibility `403` behaviour preserved.

## Tests

- `tests/Feature/Auth/AuthSecurityPr5Test.php` — throttles (login / verify / resend), burst signals, cache counter sanity, no token on reject/throttle, regression on login + `/auth/me` + `/auth/sessions`, internal signals JSON.
- Full suite under `tests/Feature/Auth/` executed in CI-style environment.

## PASS / FAIL

**PASS** — `docker exec saas_app php artisan test tests/Feature/Auth/` (69 tests) including PR5 additions.

## Residual risks

- Burst counters use a **sliding** window (TTL refreshed on each failure); not a strict fixed window.
- `rate_limited_*` signals are **debounced** per fingerprint to avoid DB spam; rapid multi-IP abuse may be under-represented by design (WAVE 1 scope).
- `trace_id` column is UUID-typed; values come from existing `trace_id` binding.

## GO / NO-GO for PR6

**GO** — PR5 scope is implemented, covered by automated tests, and auth flows (`login`, eligibility, `account_context`, sessions) remain green under the Feature\Auth suite.
