<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Messaging\TeamUserWelcomeNotifier;
use App\Support\SubscriptionQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerPortalTeamUsersController extends Controller
{
    public function __construct(
        private readonly TeamUserWelcomeNotifier $welcomeNotifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->role->isCustomer()) {
            return response()->json(['message' => 'Customer portal only.', 'trace_id' => app('trace_id')], 403);
        }

        $data = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role' => ['sometimes', 'nullable', 'string', Rule::in(['customer', 'fleet_manager', 'fleet_contact', 'viewer'])],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()
            ->where('company_id', (int) $actor->company_id)
            ->whereIn('role', ['customer', 'fleet_manager', 'fleet_contact', 'viewer']);

        if (! empty($data['role'])) {
            $query->where('role', (string) $data['role']);
        }
        if (array_key_exists('is_active', $data)) {
            $query->where('is_active', (bool) $data['is_active']);
        }
        if (! empty($data['search'])) {
            $term = trim((string) $data['search']);
            if ($term !== '') {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)->orWhere('phone', 'ilike', $like);
                });
            }
        }

        $users = $query
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 25));

        return response()->json(['data' => $users, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->role->isCustomer()) {
            return response()->json(['message' => 'Customer portal only.', 'trace_id' => app('trace_id')], 403);
        }

        SubscriptionQuota::assertCanCreateUser((int) $actor->company_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) use ($actor) {
                    if (User::query()->where('company_id', $actor->company_id)->where('email', $value)->exists()) {
                        $fail('This email is already registered in your company.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::in(['customer', 'fleet_manager', 'fleet_contact', 'viewer'])],
            'is_active' => ['nullable', 'boolean'],
            'send_welcome_notification' => ['nullable', 'boolean'],
        ]);

        $plainPassword = (string) $validated['password'];

        $user = User::query()->create([
            'uuid' => Str::uuid(),
            'company_id' => $actor->company_id,
            'name' => $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'password' => $plainPassword,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
            'status' => 'active',
        ]);

        $notification = ['sms' => false, 'whatsapp' => false];
        if (($validated['send_welcome_notification'] ?? true) === true) {
            $notification = $this->welcomeNotifier->send($user, $plainPassword);
        }

        return response()->json([
            'data' => $user->makeHidden(['password']),
            'meta' => [
                'welcome_notification' => $notification,
            ],
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->role->isCustomer()) {
            return response()->json(['message' => 'Customer portal only.', 'trace_id' => app('trace_id')], 403);
        }

        $user = User::query()
            ->where('company_id', (int) $actor->company_id)
            ->whereIn('role', ['customer', 'fleet_manager', 'fleet_contact', 'viewer'])
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['sometimes', 'string', Rule::in(['customer', 'fleet_manager', 'fleet_contact', 'viewer'])],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update($validated);

        return response()->json(['data' => $user->fresh()->makeHidden(['password']), 'trace_id' => app('trace_id')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->role->isCustomer()) {
            return response()->json(['message' => 'Customer portal only.', 'trace_id' => app('trace_id')], 403);
        }
        if ((int) $actor->id === $id) {
            return response()->json(['message' => 'لا يمكن حذف المستخدم الحالي.', 'trace_id' => app('trace_id')], 422);
        }

        $user = User::query()
            ->where('company_id', (int) $actor->company_id)
            ->whereIn('role', ['customer', 'fleet_manager', 'fleet_contact', 'viewer'])
            ->findOrFail($id);

        $user->delete();

        return response()->json(['message' => 'User deleted.', 'trace_id' => app('trace_id')]);
    }
}
