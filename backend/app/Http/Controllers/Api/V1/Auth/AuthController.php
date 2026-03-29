<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
     *             @OA\Property(property="password", type="string", example="Password123!")
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
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->canLogin()) {
            return response()->json(['message' => 'Account is disabled.', 'trace_id' => app('trace_id')], 403);
        }

        $subscription = Subscription::where('company_id', $user->company_id)
            ->whereIn('status', ['active', 'grace_period'])
            ->latest()
            ->first();

        if ($subscription?->status?->value === 'suspended' || (! $subscription && $user->role?->value !== 'owner')) {
            return response()->json(['message' => 'Company subscription is suspended.', 'trace_id' => app('trace_id')], 402);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'       => $token,
            'user'        => $this->formatUser($user),
            'permissions' => $this->getUserPermissions($user->role),
            'trace_id'    => app('trace_id'),
        ]);
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
            'permissions' => $this->getUserPermissions($user->role),
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
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.', 'trace_id' => app('trace_id')]);
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
            'permissions' => $this->getUserPermissions($user->role),
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
            'role'        => $user->role instanceof \App\Enums\UserRole ? $user->role->value : $user->role,
            'status'      => $user->status instanceof \App\Enums\UserStatus ? $user->status->value : $user->status,
            'company_id'  => $user->company_id,
            'branch_id'   => $user->branch_id,
            'customer_id' => $user->customer_id,
            'is_active'   => $user->is_active,
            'company'     => $user->relationLoaded('company') ? $user->company : null,
            'branch'      => $user->relationLoaded('branch') ? $user->branch : null,
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
