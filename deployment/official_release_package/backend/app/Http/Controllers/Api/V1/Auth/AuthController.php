<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionAccessEvaluator;
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
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     summary="Login and get Bearer token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="owner@demo.sa"),
     *             @OA\Property(property="password", type="string", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object"),
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
        $emailNorm = Str::lower(trim((string) $request->email));
        $plain = (string) $request->password;

        if ($request->filled('otp_challenge') && $request->filled('otp')) {
            return $this->completeLoginAfterOtp($request, $emailNorm, $plain);
        }

        $user = $this->findUserMatchingPassword($emailNorm, $plain);
        if (! $user) {
            return $this->invalidCredentialsJsonResponse();
        }

        $guard = $this->loginPreTokenGuards($user, $request);
        if ($guard !== null) {
            return $guard;
        }

        if (config('saas.login_otp_enabled')) {
            return $this->issueLoginOtpChallenge($user, $emailNorm);
        }

        return $this->issueAuthTokenResponse($user);
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

        if (! $user->canLogin()) {
            return response()->json(['message' => 'Account is disabled.', 'trace_id' => app('trace_id')], 403);
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

        foreach ($candidates as $candidate) {
            $hash = $candidate->getRawOriginal('password');
            if (is_string($hash) && $hash !== '' && Hash::check($plain, $hash)) {
                return $candidate;
            }
        }

        return null;
    }

    private function invalidCredentialsJsonResponse(): JsonResponse
    {
        $payload = [
            'message'  => 'The provided credentials are incorrect.',
            'trace_id' => app('trace_id'),
        ];

        $hint = $this->localLoginDiagnostics();
        if ($hint !== null) {
            $payload['dev_hint'] = $hint;
        }

        return response()->json($payload, 401);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function localLoginDiagnostics(): ?array
    {
        if (! config('app.debug') || config('app.env') !== 'local') {
            return null;
        }

        try {
            $count = User::withoutGlobalScope('tenant')->count();

            return [
                'users_in_db'    => $count,
                'vite_proxy_tip' => 'Vite on :5173 + Docker API on port 80 → frontend/.env: VITE_DEV_PROXY_TARGET=http://127.0.0.1',
                'seed_command'   => 'docker compose exec app php artisan workshop:seed-demo',
            ];
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
        if (! $user->canLogin()) {
            return response()->json(['message' => 'Account is disabled.', 'trace_id' => app('trace_id')], 403);
        }

        $company = Company::withTrashed()->find($user->company_id);
        if (! $company) {
            return response()->json([
                'message'  => 'This account is not linked to any company in the database. Re-run seeders (DefaultAdminSeeder / DemoCompanySeeder) or repair company_id.',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        if ($company->trashed()) {
            return response()->json([
                'message'  => 'This company record was removed. Contact support.',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        if ($company->status === CompanyStatus::Suspended) {
            return response()->json([
                'message'  => 'Company account is suspended.',
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
                    'message'  => 'This account is not linked to a valid branch. Re-run seeders or repair branch_id.',
                    'trace_id' => app('trace_id'),
                ], 403);
            }
        }

        $subBlock = SubscriptionAccessEvaluator::evaluate((int) $user->company_id, $request, true);
        if ($subBlock !== null) {
            return response()->json([
                'message'  => $subBlock['message'],
                'trace_id' => app('trace_id'),
            ], $subBlock['code']);
        }

        return null;
    }

    private function issueAuthTokenResponse(User $user): JsonResponse
    {
        $roleValue = (string) $user->getRawOriginal('role');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'       => $token,
            'user'        => $this->formatUser($user),
            'permissions' => $this->getUserPermissions($roleValue),
            'trace_id'    => app('trace_id'),
        ]);
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

        try {
            Mail::raw(
                "رمز التحقق لتسجيل الدخول: {$code}\n\nالصلاحية: {$ttl} ثانية.\nإن لم تطلب ذلك، غيّر كلمة المرور وتواصل مع المسؤول.",
                function ($message) use ($user): void {
                    $message->to($user->email)
                        ->subject((string) config('app.name').' — رمز التحقق');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('login.otp_mail_failed', [
                'error'    => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);
        }

        return response()->json([
            'otp_required'  => true,
            'challenge_id'  => $challengeId,
            'expires_in'    => $ttl,
            'message'       => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
            'trace_id'      => app('trace_id'),
        ]);
    }

    private function completeLoginAfterOtp(LoginRequest $request, string $emailNorm, string $plain): JsonResponse
    {
        $challengeId = (string) $request->input('otp_challenge');
        $otp = (string) $request->input('otp');
        $cacheKey = 'login_otp:'.$challengeId;
        $row = Cache::get($cacheKey);
        if (! is_array($row) || ($row['email_norm'] ?? '') !== $emailNorm) {
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

            return response()->json([
                'message'  => 'رمز التحقق غير صحيح.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $user = $this->findUserMatchingPassword($emailNorm, $plain);
        if (! $user || (int) $user->id !== (int) $row['user_id']) {
            Cache::forget($cacheKey);

            return $this->invalidCredentialsJsonResponse();
        }

        Cache::forget($cacheKey);

        $guard = $this->loginPreTokenGuards($user, $request);
        if ($guard !== null) {
            return $guard;
        }

        return $this->issueAuthTokenResponse($user);
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
     *             required={"company_name","name","email","password","password_confirmation"},
     *             @OA\Property(property="company_name", type="string", example="My Auto Center"),
     *             @OA\Property(property="name", type="string", example="John Owner"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string"),
     *             @OA\Property(property="phone", type="string"),
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
            'phone'      => $data['phone'] ?? null,
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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'       => $token,
            'user'        => $this->formatUser($user),
            'permissions' => $this->getUserPermissions((string) $user->getRawOriginal('role')),
            'trace_id'    => app('trace_id'),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout and revoke token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message'  => 'Logged out.',
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

        return response()->json([
            'data'        => $this->formatUser($user),
            'permissions' => $this->getUserPermissions((string) $user->getRawOriginal('role')),
            'trace_id'    => app('trace_id'),
        ]);
    }

    private function formatUser(User $user): array
    {
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
            'company'     => $user->relationLoaded('company') ? $user->company : null,
            'branch'      => $user->relationLoaded('branch') ? $user->branch : null,
            'subscription' => $user->company_id ? $this->subscriptionBillingSummary((int) $user->company_id) : null,
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
}
