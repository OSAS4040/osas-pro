<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResolveLoginContextAction;
use App\Enums\CompanyStatus;
use App\Enums\LoginPrincipalKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Auth\AuthLoginEventRecorder;
use App\Services\Auth\AuthSecurityTelemetryService;
use App\Services\Auth\AuthSessionMetadataWriter;
use App\Services\Auth\LoginBootstrapService;
use App\Services\Auth\LoginOtpNotifier;
use App\Services\NavigationVisibilityService;
use App\Support\Auth\PhoneNormalizer;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterPushDeviceRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Jobs\SyncUserPushDeviceJob;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\UserPushDevice;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Auth\LoginContextResolution;
use App\Support\SubscriptionAccessEvaluator;
use Database\Seeders\DemoPlatformAdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Auth", description="Authentication endpoints")
 */
class AuthController extends Controller
{
    private const LOGIN_CONTEXT_RESOLUTION_REQUEST_KEY = 'login.context_resolution';

    public function __construct(
        private readonly LoginBootstrapService $loginBootstrap,
        private readonly LoginOtpNotifier $loginOtpNotifier,
        private readonly ResolveLoginContextAction $resolveLoginContext,
        private readonly AuthSessionMetadataWriter $authSessionMetadataWriter,
        private readonly AuthLoginEventRecorder $authLoginEventRecorder,
        private readonly AuthSecurityTelemetryService $authSecurityTelemetry,
        private readonly PlatformAuditLogger $platformAuditLogger,
        private readonly NavigationVisibilityService $navigationVisibility,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     summary="Login (email or unified identifier) and get Bearer token + bootstrap",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="email", type="string", format="email", description="SPA: email + password"),
     *             @OA\Property(property="identifier", type="string", description="Mobile: email or phone + device fields"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="device_name", type="string"),
     *             @OA\Property(property="device_type", type="string", enum={"android","ios","ipados","unknown"}),
     *             @OA\Property(property="fcm_token", type="string", nullable=true),
     *             @OA\Property(property="otp", type="string", nullable=true),
     *             @OA\Property(property="otp_challenge", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="company", type="object", nullable=true),
     *             @OA\Property(property="branches", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="enabled_modules", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="home_screen", type="string"),
     *             @OA\Property(property="profile", type="object"),
     *             @OA\Property(property="trace_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            return $this->completeLogin($request);
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'message'  => $this->tokenOrDbFailureMessage($e),
                'trace_id' => app('trace_id'),
            ], 503);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message'  => config('app.debug')
                    ? $e->getMessage().' ['.basename($e->getFile()).':'.$e->getLine().']'
                    : 'Login failed. Check laravel.log and database connectivity.',
                'trace_id' => app('trace_id'),
            ], 500);
        }
    }

    private function completeLogin(LoginRequest $request): JsonResponse
    {
        $plain = (string) $request->password;

        if ($request->filled('otp_challenge') && $request->filled('otp')) {
            return $this->completeLoginAfterOtp($request, $plain);
        }

        $user = $this->resolveUserMatchingPassword($request, $plain);
        if (! $user) {
            Log::info('auth.login.failed', [
                'reason'     => 'bad_credentials',
                'ip'         => $request->ip(),
                'has_email'  => $request->filled('email'),
                'identifier' => $request->filled('identifier'),
                'trace_id'   => app('trace_id'),
            ]);

            $this->authLoginEventRecorder->loginDenied(null, 'invalid_credentials', 'password', $request);
            $this->authSecurityTelemetry->recordInvalidPasswordLogin($request);

            return $this->invalidCredentialsJsonResponse($request);
        }

        $guard = $this->loginPreTokenGuards($user, $request);
        if ($guard !== null) {
            return $guard;
        }

        if (config('saas.login_otp_enabled')) {
            $emailNorm = $this->resolveEmailNormForOtp($request, $user);

            return $this->issueLoginOtpChallenge($user, $emailNorm);
        }

        Log::info('auth.login.success', [
            'user_id'     => $user->id,
            'device_type' => $request->input('device_type'),
            'trace_id'    => app('trace_id'),
        ]);

        return $this->issueAuthTokenResponse($user, $request);
    }

    /**
     * Email login uses request email; identifier login uses email-shaped identifier or phone lookup.
     */
    private function resolveUserMatchingPassword(LoginRequest $request, string $plain): ?User
    {
        $emailNorm = $this->resolveEmailNormFromRequest($request);
        if ($emailNorm !== null && $emailNorm !== '') {
            return $this->findUserMatchingPassword($emailNorm, $plain);
        }

        $identifier = trim((string) $request->input('identifier', ''));
        if ($identifier === '') {
            return null;
        }

        $variants = PhoneNormalizer::comparisonVariants($identifier);
        if ($variants === []) {
            return null;
        }

        return $this->findUserMatchingPasswordByPhoneVariants($variants, $plain);
    }

    private function resolveEmailNormFromRequest(LoginRequest $request): ?string
    {
        if ($request->filled('email')) {
            return Str::lower(trim((string) $request->input('email')));
        }
        $id = trim((string) $request->input('identifier', ''));
        if ($id !== '' && str_contains($id, '@')) {
            return Str::lower($id);
        }

        return null;
    }

    private function resolveEmailNormForOtp(LoginRequest $request, User $user): string
    {
        $fromRequest = $this->resolveEmailNormFromRequest($request);
        if ($fromRequest !== null && $fromRequest !== '') {
            return $fromRequest;
        }

        return Str::lower(trim((string) $user->email));
    }

    /**
     * @param  list<string>  $digitVariants
     */
    private function findUserMatchingPasswordByPhoneVariants(array $digitVariants, string $plain): ?User
    {
        $candidates = $this->loginBootstrap->usersMatchingPhoneVariants($digitVariants);
        foreach ($candidates as $candidate) {
            $hash = $candidate->getRawOriginal('password');
            if (is_string($hash) && $hash !== '' && Hash::check($plain, $hash)) {
                return $candidate;
            }
        }

        return null;
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $emailNorm = Str::lower(trim((string) $request->validated('email')));

        $table = (new User)->getTable();
        $users = User::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(TRIM('.$table.'.email)) = ?', [$emailNorm])
            ->orderBy($table.'.id')
            ->get();

        if ($users->count() === 1) {
            $user = $users->first();
            $token = Str::random(64);
            $ttl = (int) config('saas.password_reset_ttl_seconds', 3600);
            Cache::put('pwd_reset:'.$token, [
                'user_id'    => $user->id,
                'email_norm' => $emailNorm,
            ], now()->addSeconds($ttl));

            $frontend = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_PUBLIC_URL', '')), '/');
            if ($frontend === '') {
                $frontend = rtrim((string) config('app.url'), '/');
            }
            $link = $frontend.'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email);

            try {
                Mail::raw(
                    "لإعادة تعيين كلمة المرور افتح الرابط التالي (صالح لمدة محدودة):\n\n{$link}\n\nإن لم تطلب ذلك، تجاهل الرسالة.",
                    function ($message) use ($user): void {
                        $message->to($user->email)
                            ->subject((string) config('app.name').' — إعادة تعيين كلمة المرور');
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('auth.forgot_password.mail_failed', [
                    'error'    => $e->getMessage(),
                    'trace_id' => app('trace_id'),
                ]);
            }
        } elseif ($users->count() > 1) {
            Log::info('auth.forgot_password.ambiguous_email', [
                'email_suffix' => substr($emailNorm, 0, 3).'***',
                'trace_id'     => app('trace_id'),
            ]);
        }

        return response()->json([
            'message'  => 'إن وُجد حساب مرتبط بهذا البريد، ستصلك تعليمات إعادة التعيين قريباً.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $token = (string) $request->validated('token');
        $emailNorm = Str::lower(trim((string) $request->validated('email')));

        $payload = Cache::pull('pwd_reset:'.$token);
        if (! is_array($payload) || ($payload['email_norm'] ?? '') !== $emailNorm || empty($payload['user_id'])) {
            return response()->json([
                'message'  => 'رابط إعادة التعيين غير صالح أو منتهٍ. اطلب رابطاً جديداً.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $user = User::withoutGlobalScope('tenant')->find((int) $payload['user_id']);
        if (! $user || Str::lower(trim($user->email)) !== $emailNorm) {
            return response()->json([
                'message'  => 'تعذّر إكمال العملية. اطلب رابطاً جديداً.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $resolution = ($this->resolveLoginContext)($user);
        if (! $resolution->eligibility->allowed) {
            return response()->json($resolution->eligibility->toForbiddenResponseBody('ar'), 403);
        }

        $user->password = $request->validated('password');
        $user->save();

        return response()->json([
            'message'  => 'تم تحديث كلمة المرور. يمكنك تسجيل الدخول الآن.',
            'trace_id' => app('trace_id'),
        ]);
    }

    private function findUserMatchingPassword(string $emailNorm, string $plain): ?User
    {
        $table = (new User)->getTable();
        $candidates = User::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(TRIM('.$table.'.email)) = ?', [$emailNorm])
            ->orderBy($table.'.id')
            ->get();

        if ($this->devPasswordlessLoginAllowed($emailNorm) && $candidates->isNotEmpty()) {
            Log::warning('auth.dev_passwordless_login', [
                'email_suffix' => substr($emailNorm, 0, 4).'***',
                'trace_id'     => app()->bound('trace_id') ? (string) app('trace_id') : null,
            ]);

            return $candidates->first();
        }

        foreach ($candidates as $candidate) {
            $hash = $candidate->getRawOriginal('password');
            if (is_string($hash) && $hash !== '' && Hash::check($plain, $hash)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * تجاوز كلمة المرور للتطوير المحلي فقط — انظر config/auth_security.php.
     */
    private function devPasswordlessLoginAllowed(string $emailNorm): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }
        if (! app()->environment('local') || ! config('app.debug')) {
            return false;
        }
        if (! (bool) config('auth_security.dev_passwordless', false)) {
            return false;
        }
        /** @var list<string> $list */
        $list = (array) config('auth_security.dev_passwordless_emails', []);

        return $list !== [] && in_array($emailNorm, $list, true);
    }

    private function invalidCredentialsJsonResponse(?LoginRequest $request = null): JsonResponse
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $messages = (array) config('auth_login_eligibility.messages.'.$locale, []);
        $resolved = is_string($messages['auth.login.invalid_credentials'] ?? null)
            ? (string) $messages['auth.login.invalid_credentials']
            : 'بيانات الدخول غير صحيحة.';

        $payload = [
            'message'      => $resolved,
            'reason_code'  => 'INVALID_CREDENTIALS',
            'message_key'  => 'auth.login.invalid_credentials',
            'trace_id'     => app('trace_id'),
        ];

        $hint = $this->localLoginDiagnostics($request);
        if ($hint !== null) {
            $payload['dev_hint'] = $hint;
        }

        $platformHint = $this->platformDemoLoginFailureHint($request);
        if ($platformHint !== null) {
            $payload['platform_demo_hint'] = $platformHint;
        }

        return response()->json($payload, 401);
    }

    /**
     * تلميح ثابت عند فشل الدخول ببريد التجربة فقط — لا يعتمد على APP_DEBUG (يُستثنى الإنتاج).
     */
    private function platformDemoLoginFailureHint(?LoginRequest $request): ?string
    {
        if ($request === null || app()->environment('production')) {
            return null;
        }

        $emailNorm = $this->resolveEmailNormFromRequest($request);
        if ($emailNorm === null || $emailNorm === '' || $emailNorm !== strtolower(DemoPlatformAdminSeeder::DEMO_EMAIL)) {
            return null;
        }

        $plain = (string) $request->input('password', '');
        $table = (new User)->getTable();
        $row = User::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(TRIM('.$table.'.email)) = ?', [$emailNorm])
            ->orderBy($table.'.id')
            ->first();

        $isEn = app()->getLocale() === 'en';

        if ($row === null) {
            return $isEn
                ? 'No user with this demo email in this database. Run: php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder (Docker: docker compose exec app php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder). If APP_ENV=production, set APP_DEMO_PLATFORM_ADMIN=true or use platform-admin:provision.'
                : 'لا يوجد مستخدم بهذا البريد في قاعدة البيانات التي يتصل بها الـ API. نفّذ: php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder (مع Docker: docker compose exec app قبل الأمر). إن كان APP_ENV=production فعّل APP_DEMO_PLATFORM_ADMIN=true في .env أو استخدم platform-admin:provision.';
        }

        $hash = $row->getRawOriginal('password');
        if (! is_string($hash) || $hash === '') {
            return $isEn
                ? 'User row has an empty password hash — re-run DemoPlatformAdminSeeder.'
                : 'سجل المستخدم بدون hash لكلمة المرور — أعد تشغيل DemoPlatformAdminSeeder.';
        }

        if (Hash::check($plain, $hash)) {
            return $isEn
                ? 'Password matches database but login failed earlier in the pipeline — report with trace_id.'
                : 'كلمة المرور تطابق القاعدة لكن الدخول فشل في خطوة لاحقة — أبلغ مع trace_id.';
        }

        $storedIsDemo = Hash::check(DemoPlatformAdminSeeder::DEMO_PASSWORD, $hash);
        if ($storedIsDemo) {
            return $isEn
                ? 'Wrong password for this demo row. Use exactly: '.DemoPlatformAdminSeeder::DEMO_PASSWORD
                : 'كلمة المرور المدخلة لا تطابق القاعدة. القيمة التجريبية المتوقعة حرفياً: '.DemoPlatformAdminSeeder::DEMO_PASSWORD;
        }

        return $isEn
            ? 'Stored password is not the demo value. Re-run: php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder'
            : 'كلمة المرور المخزّنة ليست التجريبية (تم تغييرها). أعد ضبطها: php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function localLoginDiagnostics(?LoginRequest $request = null): ?array
    {
        if (! config('app.debug') || app()->environment('production')) {
            return null;
        }

        try {
            $count = User::withoutGlobalScope('tenant')->count();

            $out = [
                'users_in_db'    => $count,
                'vite_proxy_tip' => 'Vite on :5173 + Docker API on port 80 → frontend/.env: VITE_DEV_PROXY_TARGET=http://127.0.0.1',
                'seed_command'   => 'docker compose exec app php artisan workshop:seed-demo',
            ];

            $emailNorm = $request !== null ? $this->resolveEmailNormFromRequest($request) : null;
            $demoEmail = strtolower(DemoPlatformAdminSeeder::DEMO_EMAIL);
            if ($emailNorm !== null && $emailNorm !== '' && $emailNorm === $demoEmail) {
                $table = (new User)->getTable();
                $row = User::withoutGlobalScope('tenant')
                    ->whereRaw('LOWER(TRIM('.$table.'.email)) = ?', [$emailNorm])
                    ->orderBy($table.'.id')
                    ->first();
                $out['platform_demo_user_row_exists'] = $row !== null;
                if ($row === null) {
                    $out['platform_demo_next_step'] =
                        'لا يوجد صف في users لهذا البريد. شغّل: php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder '
                        .'(في Docker: docker compose exec app php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder). '
                        .'إن كان APP_ENV=production فعّل APP_DEMO_PLATFORM_ADMIN=true أو استخدم platform-admin:provision.';
                } else {
                    $hash = $row->getRawOriginal('password');
                    $demoPlain = DemoPlatformAdminSeeder::DEMO_PASSWORD;
                    $ok = is_string($hash) && $hash !== '' && Hash::check($demoPlain, $hash);
                    $out['platform_demo_password_matches_seeder'] = $ok;
                    if (! $ok) {
                        $out['platform_demo_next_step'] =
                            'الحساب موجود لكن كلمة المرور لا تطابق التجريبية ('.$demoPlain.'). أعد تشغيل DemoPlatformAdminSeeder لتحديثها.';
                    }
                }
            }

            return $out;
        } catch (\Throwable) {
            return [
                'users_in_db'     => null,
                'database_error' => true,
            ];
        }
    }

    /**
     * @return JsonResponse|null  JSON error or null if OK
     */
    private function loginPreTokenGuards(User $user, Request $request): ?JsonResponse
    {
        $roleValue = (string) $user->getRawOriginal('role');
        $permissions = $this->getUserPermissions($roleValue);
        $resolution = ($this->resolveLoginContext)($user, $permissions);
        $request->attributes->set(self::LOGIN_CONTEXT_RESOLUTION_REQUEST_KEY, $resolution);

        if (! $resolution->eligibility->allowed) {
            Log::info('auth.login.denied_eligibility', [
                'user_id'     => $user->id,
                'reason_code' => $resolution->eligibility->reasonCode,
                'message_key' => $resolution->eligibility->messageKey,
                'ip'          => $request->ip(),
                'trace_id'    => app('trace_id'),
            ]);

            $this->authLoginEventRecorder->loginDenied(
                $user,
                (string) $resolution->eligibility->reasonCode,
                'password',
                $request,
            );

            return response()->json($resolution->eligibility->toForbiddenResponseBody('ar'), 403);
        }

        if (! $user->company_id) {
            if ($resolution->accountContext?->principalKind === \App\Enums\LoginPrincipalKind::PlatformEmployee) {
                return null;
            }

            if ((string) $user->getRawOriginal('role') === \App\Enums\UserRole::PhoneOnboarding->value) {
                return null;
            }

            return response()->json([
                'message'  => 'لا يمكن ربط هذا الحساب بشركة صالحة. تواصل مع الدعم.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $company = Company::withTrashed()->find($user->company_id);
        if (! $company) {
            return response()->json([
                'message'  => 'لا يمكن ربط هذا الحساب بشركة صالحة. تواصل مع الدعم.',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        if ($company->trashed()) {
            return response()->json([
                'message'  => 'سجل الشركة غير متاح. تواصل مع الدعم.',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        if ($company->status === CompanyStatus::Suspended) {
            return response()->json([
                'message'  => 'حساب الشركة موقوف حالياً.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        if ($user->branch_id !== null) {
            $branch = Branch::withTrashed()
                ->withoutGlobalScope('tenant')
                ->where('id', $user->branch_id)
                ->where('company_id', $user->company_id)
                ->first();
            if (! $branch || $branch->trashed()) {
                return response()->json([
                    'message'  => 'لا يمكن ربط هذا الحساب بفرع صالح. تواصل مع المسؤول.',
                    'trace_id' => app('trace_id'),
                ], 403);
            }
        }

        if ($user->company_id !== null) {
            $subBlock = SubscriptionAccessEvaluator::evaluate((int) $user->company_id, $request, true);
            if ($subBlock !== null) {
                return response()->json([
                    'message'  => $subBlock['message'],
                    'trace_id' => app('trace_id'),
                ], $subBlock['code']);
            }
        }

        return null;
    }

    private function issueAuthTokenResponse(User $user, Request $request): JsonResponse
    {
        $roleValue = (string) $user->getRawOriginal('role');
        $permissions = $this->getUserPermissions($roleValue);
        $deviceLabel = $request->filled('device_name')
            ? (string) $request->input('device_name')
            : 'web-spa';
        $abilitiesForToken = $permissions !== [] ? $permissions : ['*'];
        $newAccessToken = $user->createToken($deviceLabel, $abilitiesForToken);
        $this->authSessionMetadataWriter->apply($newAccessToken->accessToken, $request, 'password');
        $this->authLoginEventRecorder->loginSuccess($user, $newAccessToken->accessToken, 'password', $request);
        $token = $newAccessToken->plainTextToken;

        $user->loadMissing('company', 'branch');
        $bootstrap = $this->loginBootstrap->build($user, $permissions);

        if ($request->filled('fcm_token')) {
            $this->dispatchPushDeviceJob(
                $user,
                (string) $request->input('fcm_token'),
                $request->filled('device_name') ? (string) $request->input('device_name') : null,
                $request->filled('device_type') ? (string) $request->input('device_type') : null,
            );
        }

        $resolution = $request->attributes->get(self::LOGIN_CONTEXT_RESOLUTION_REQUEST_KEY);
        $accountContextPayload = [];
        if ($resolution instanceof LoginContextResolution && $resolution->accountContext !== null) {
            $accountContextPayload['account_context'] = $resolution->accountContext->toArray();
            if ($resolution->accountContext->principalKind === LoginPrincipalKind::PlatformEmployee) {
                $this->platformAuditLogger->record($user, 'platform.login', $request, ['channel' => 'password']);
            }
        }

        return response()->json(array_merge([
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $this->formatUser($user),
            'permissions' => $permissions,
            'trace_id'    => app('trace_id'),
        ], $bootstrap, $accountContextPayload));
    }

    private function issueLoginOtpChallenge(User $user, string $emailNorm): JsonResponse
    {
        $code = (string) random_int(100000, 999999);
        $challengeId = Str::uuid()->toString();
        $ttl = (int) config('saas.login_otp_ttl_seconds', 300);

        Cache::put('login_otp:'.$challengeId, [
            'user_id'    => $user->id,
            'email_norm' => $emailNorm,
            'code_hash'  => Hash::make($code),
            'attempts'   => 0,
        ], now()->addSeconds($ttl));

        Log::info('login.otp_issued', [
            'user_id'         => $user->id,
            'challenge_suffix'=> substr($challengeId, -8),
            'trace_id'        => app('trace_id'),
        ]);

        if (config('app.debug')) {
            Log::debug('login.otp_code', ['code' => $code]);
        }

        $sent = $this->loginOtpNotifier->deliver($user, $code, $ttl);
        $deliveryMessage = $this->loginOtpNotifier->describeDelivery($sent['sms'], $sent['email']);

        return response()->json([
            'otp_required'  => true,
            'challenge_id'  => $challengeId,
            'expires_in'    => $ttl,
            'message'       => $deliveryMessage,
            'trace_id'      => app('trace_id'),
        ]);
    }

    private function completeLoginAfterOtp(LoginRequest $request, string $plain): JsonResponse
    {
        $challengeId = (string) $request->input('otp_challenge');
        $otp = (string) $request->input('otp');
        $cacheKey = 'login_otp:'.$challengeId;
        $row = Cache::get($cacheKey);
        $expectedUserId = (int) ($row['user_id'] ?? 0);
        if (! is_array($row) || $expectedUserId < 1) {
            return response()->json([
                'message'  => 'جلسة التحقق غير صالحة. أعد تسجيل الدخول من البداية.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $attempts = (int) ($row['attempts'] ?? 0);
        if ($attempts >= 8) {
            Cache::forget($cacheKey);

            return response()->json([
                'message'  => 'تجاوزت عدد المحاولات. أعد تسجيل الدخول.',
                'trace_id' => app('trace_id'),
            ], 429);
        }

        if (! is_string($row['code_hash'] ?? null) || ! Hash::check($otp, $row['code_hash'])) {
            $row['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $row, now()->addSeconds((int) config('saas.login_otp_ttl_seconds', 300)));
            $this->authSecurityTelemetry->recordInvalidPasswordLogin($request);

            return response()->json([
                'message'  => 'رمز التحقق غير صحيح.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $cachedEmailNorm = is_string($row['email_norm'] ?? null) ? (string) $row['email_norm'] : '';
        $requestEmailNorm = $this->resolveEmailNormFromRequest($request);
        if ($cachedEmailNorm !== '' && $requestEmailNorm !== null && $requestEmailNorm !== ''
            && $cachedEmailNorm !== $requestEmailNorm) {
            Cache::forget($cacheKey);

            return response()->json([
                'message'  => 'جلسة التحقق غير صالحة. أعد تسجيل الدخول من البداية.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $user = User::withoutGlobalScope('tenant')->find($expectedUserId);
        $hash = $user?->getRawOriginal('password');
        if (! $user || ! is_string($hash) || $hash === '' || ! Hash::check($plain, $hash)) {
            Cache::forget($cacheKey);
            $this->authSecurityTelemetry->recordInvalidPasswordLogin($request);

            return $this->invalidCredentialsJsonResponse($request);
        }

        Cache::forget($cacheKey);

        $guard = $this->loginPreTokenGuards($user, $request);
        if ($guard !== null) {
            return $guard;
        }

        return $this->issueAuthTokenResponse($user, $request);
    }

    private function tokenOrDbFailureMessage(QueryException $e): string
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'personal_access_tokens')) {
            return 'Cannot issue API token. Run: php artisan migrate (table personal_access_tokens).';
        }

        return config('app.debug')
            ? $e->getMessage()
            : 'Database error during login. Run php artisan migrate and verify DB credentials in .env.';
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Auth"},
     *     summary="Register a new company and owner",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_name","name","email","phone","password","password_confirmation"},
     *             @OA\Property(property="company_name", type="string", example="My Auto Center"),
     *             @OA\Property(property="name", type="string", example="John Owner"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string"),
     *             @OA\Property(property="phone", type="string", example="+966500000000"),
     *             @OA\Property(property="timezone", type="string", example="Asia/Riyadh")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registered successfully")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $company = Company::create([
            'uuid'      => Str::uuid(),
            'name'      => $data['company_name'],
            'currency'  => 'SAR',
            'timezone'  => $data['timezone'] ?? 'Asia/Riyadh',
            'status'    => 'active',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'uuid'       => Str::uuid(),
            'company_id' => $company->id,
            'name'       => 'Main Branch',
            'name_ar'    => 'الفرع الرئيسي',
            'code'       => 'MAIN',
            'status'     => 'active',
            'is_main'    => true,
            'is_active'  => true,
        ]);

        $user = User::create([
            'uuid'       => Str::uuid(),
            'company_id' => $company->id,
            'branch_id'  => $branch->id,
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => $data['password'],
            'phone'      => PhoneNormalizer::normalizeForStorage((string) $data['phone']),
            'role'       => 'owner',
            'status'     => 'active',
            'is_active'  => true,
        ]);

        Subscription::create([
            'uuid'        => Str::uuid(),
            'company_id'  => $company->id,
            'plan'        => 'trial',
            'status'      => 'active',
            'starts_at'   => now(),
            'ends_at'     => now()->addDays(14),
            'amount'      => 0,
            'max_branches'=> 1,
            'max_users'   => 3,
        ]);

        $newAccessToken = $user->createToken('auth_token');
        $this->authSessionMetadataWriter->apply($newAccessToken->accessToken, $request, 'register');
        $this->authLoginEventRecorder->loginSuccess($user, $newAccessToken->accessToken, 'register', $request);
        $token = $newAccessToken->plainTextToken;

        $user->loadMissing('company', 'branch');
        $resolution = ($this->resolveLoginContext)($user);

        $payload = [
            'token'       => $token,
            'user'        => $this->formatUser($user),
            'permissions' => $this->getUserPermissions((string) $user->getRawOriginal('role')),
            'trace_id'    => app('trace_id'),
        ];
        if ($resolution->eligibility->allowed && $resolution->accountContext !== null) {
            $payload['account_context'] = $resolution->accountContext->toArray();
        }

        return response()->json($payload, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout and revoke token",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="fcm_token", type="string", description="When sent, removes matching user_push_devices row for this user")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ]);

        $user = $request->user();

        if ($user && $request->filled('fcm_token')) {
            $tok = trim((string) $request->input('fcm_token'));
            if ($tok !== '') {
                UserPushDevice::query()
                    ->where('user_id', $user->id)
                    ->where('fcm_token', $tok)
                    ->delete();
            }
        }

        $currentToken = $user?->currentAccessToken();
        if ($user && $currentToken) {
            $this->authLoginEventRecorder->logoutSession($user, $currentToken, $request);
            $currentToken->delete();
        }

        return response()->json([
            'message'  => 'تم تسجيل الخروج من هذا الجهاز.',
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout-all",
     *     tags={"Auth"},
     *     summary="Revoke all Sanctum tokens for the current user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All sessions revoked")
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->authLoginEventRecorder->logoutAll($user, $request);
            UserPushDevice::query()->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        }

        return response()->json([
            'message'  => 'تم تسجيل الخروج من جميع الأجهزة.',
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/push-device",
     *     tags={"Auth"},
     *     summary="Register or refresh FCM token for push (queued persistence)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string"),
     *             @OA\Property(property="device_name", type="string", nullable=true),
     *             @OA\Property(property="device_type", type="string", enum={"android","ios","ipados","unknown"}, nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Queued / accepted")
     * )
     */
    public function registerPushDevice(RegisterPushDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $v = $request->validated();
        $this->dispatchPushDeviceJob(
            $user,
            (string) $v['fcm_token'],
            isset($v['device_name']) ? (string) $v['device_name'] : null,
            isset($v['device_type']) ? (string) $v['device_type'] : null,
        );

        return response()->json([
            'message'  => 'تم قبول تسجيل الجهاز للإشعارات.',
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Auth"},
     *     summary="Get authenticated user with permissions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('company', 'branch');
        $roleValue = (string) $user->getRawOriginal('role');
        $permissions = $this->getUserPermissions($roleValue);
        $resolution = ($this->resolveLoginContext)($user, $permissions);

        $payload = [
            'data'        => $this->formatUser($user),
            'permissions' => $permissions,
            'trace_id'    => app('trace_id'),
        ];

        if ($resolution->eligibility->allowed && $resolution->accountContext !== null) {
            $payload['account_context'] = $resolution->accountContext->toArray();
        }

        return response()->json($payload);
    }

    private function formatUser(User $user): array
    {
        $platformRoleRaw = $user->getRawOriginal('platform_role');

        return [
            'id'          => $user->id,
            'uuid'        => $user->uuid,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'role'        => $user->getRawOriginal('role'),
            'status'      => $user->getRawOriginal('status'),
            'company_id'  => $user->company_id,
            'branch_id'   => $user->branch_id,
            'customer_id' => $user->customer_id,
            'is_active'   => $user->is_active,
            'is_platform_user' => (bool) ($user->is_platform_user ?? false),
            'platform_role'    => is_string($platformRoleRaw) && $platformRoleRaw !== '' ? $platformRoleRaw : null,
            'account_type'       => $user->account_type,
            'registration_stage' => $user->registration_stage,
            'company'     => $user->relationLoaded('company') ? $user->company : null,
            'branch'      => $user->relationLoaded('branch') ? $user->branch : null,
            'subscription' => $user->company_id ? $this->subscriptionBillingSummary((int) $user->company_id) : null,
            'navigation_visibility' => $this->navigationVisibility->effectiveForUser($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionBillingSummary(int $companyId): array
    {
        $row = Subscription::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return [
                'plan'            => null,
                'status'          => null,
                'ends_at'         => null,
                'grace_ends_at'   => null,
                'billing_state'   => 'none',
                'max_branches'    => null,
                'max_users'       => null,
                'grace_read_only' => false,
            ];
        }

        $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
        $now    = now();
        $endsAt = $row->ends_at;
        $grace  = $row->grace_ends_at;

        $expiredByTime   = $endsAt !== null && $endsAt->lt($now);
        $inGraceWindow   = $expiredByTime && $grace !== null && $now->lt($grace);

        if ($status === 'suspended') {
            $billingState = 'suspended';
        } elseif (! $expiredByTime) {
            $billingState = 'active';
        } elseif ($inGraceWindow) {
            $billingState = 'grace';
        } else {
            $billingState = 'expired';
        }

        return [
            'plan'            => $row->plan,
            'status'          => $status,
            'ends_at'         => $row->ends_at?->toIso8601String(),
            'grace_ends_at'   => $row->grace_ends_at?->toIso8601String(),
            'billing_state'   => $billingState,
            'max_branches'    => (int) ($row->max_branches ?? 1),
            'max_users'       => (int) ($row->max_users ?? 5),
            'grace_read_only' => $billingState === 'grace',
        ];
    }

    private function getUserPermissions(string|\App\Enums\UserRole $role): array
    {
        $roleKey     = $role instanceof \App\Enums\UserRole ? $role->value : $role;
        $permissions = config('permissions.roles', []);
        $perms       = $permissions[$roleKey] ?? [];

        if (in_array('*', $perms)) {
            return config('permissions.all_permissions', []);
        }

        return $perms;
    }

    private function dispatchPushDeviceJob(
        User $user,
        string $fcmToken,
        ?string $deviceName,
        ?string $deviceType,
    ): void {
        $token = trim($fcmToken);
        if ($token === '' || $user->company_id === null) {
            return;
        }

        SyncUserPushDeviceJob::dispatch(
            (int) $user->id,
            (int) $user->company_id,
            $token,
            $deviceName,
            $deviceType,
        );
    }
}
