<?php

namespace App\Services\Auth;

use App\Support\Auth\PhoneNormalizer;
use App\Support\SaasPlatformAccess;
use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds mobile / SPA bootstrap payload after successful password verification.
 */
final class LoginBootstrapService
{
    /**
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    public function build(User $user, array $permissions): array
    {
        if ($user->company_id === null) {
            if (SaasPlatformAccess::isPlatformOperator($user)) {
                return [
                    'company'         => null,
                    'branches'        => [],
                    'enabled_modules' => [],
                    'home_screen'     => 'dashboard',
                    'profile'         => $this->profileStub($user, null),
                ];
            }

            return [
                'company'         => null,
                'branches'        => [],
                'enabled_modules' => [],
                'home_screen'     => 'phone_onboarding',
                'profile'         => $this->profileStub($user, null),
            ];
        }

        $company = Company::withTrashed()->find($user->company_id);
        $companyPayload = $this->formatCompany($company);

        $branches = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $user->company_id)
            ->whereNull('deleted_at')
            ->where('status', BranchStatus::Active)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->get(['id', 'uuid', 'name', 'name_ar', 'code', 'is_main']);

        $enabledModules = $this->resolveEnabledModules($permissions);
        $homeScreen = $this->resolveHomeScreen($enabledModules);

        return [
            'company'          => $companyPayload,
            'branches'         => $branches->map(fn (Branch $b) => [
                'id'      => $b->id,
                'uuid'    => $b->uuid,
                'name'    => $b->name,
                'name_ar' => $b->name_ar,
                'code'    => $b->code,
                'is_main' => (bool) $b->is_main,
            ])->values()->all(),
            'enabled_modules'  => $enabledModules,
            'home_screen'      => $homeScreen,
            'profile'          => $this->profileStub($user, $company),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatCompany(?Company $company): ?array
    {
        if ($company === null || $company->trashed()) {
            return null;
        }

        return [
            'id'        => $company->id,
            'uuid'      => $company->uuid,
            'name'      => $company->name,
            'currency'  => $company->currency,
            'timezone'  => $company->timezone,
            'status'    => $company->status instanceof \BackedEnum ? $company->status->value : (string) $company->status,
        ];
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function resolveEnabledModules(array $permissions): array
    {
        $permSet = array_fill_keys($permissions, true);
        $modules = (array) config('mobile_bootstrap.modules', []);
        $enabled = [];

        foreach ($modules as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $any = (array) ($row['requires_any'] ?? []);
            foreach ($any as $p) {
                if (isset($permSet[(string) $p])) {
                    $enabled[] = $id;
                    break;
                }
            }
        }

        return array_values(array_unique($enabled));
    }

    /**
     * @param  list<string>  $enabledModules
     */
    private function resolveHomeScreen(array $enabledModules): string
    {
        $set = array_fill_keys($enabledModules, true);
        $priority = (array) config('mobile_bootstrap.home_screen_priority', []);

        foreach ($priority as $id) {
            if (isset($set[(string) $id])) {
                return (string) $id;
            }
        }

        return (string) config('mobile_bootstrap.fallback_home_screen', 'dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function profileStub(User $user, ?Company $company): array
    {
        return [
            'locale'     => 'ar',
            'timezone'   => $company?->timezone ?? 'Asia/Riyadh',
            'currency'   => $company?->currency ?? 'SAR',
            'branch_id'  => $user->branch_id,
            'company_id' => $user->company_id,
        ];
    }

    /**
     * Find users matching normalized phone variants; password verification remains caller responsibility.
     *
     * @param  list<string>  $digitVariants
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function usersMatchingPhoneVariants(array $digitVariants): \Illuminate\Support\Collection
    {
        if ($digitVariants === []) {
            return collect();
        }

        $connection = DB::connection()->getDriverName();
        if ($connection !== 'pgsql') {
            $wanted = [];
            foreach ($digitVariants as $v) {
                foreach (PhoneNormalizer::comparisonVariants($v) as $x) {
                    $wanted[$x] = true;
                }
            }

            return User::withoutGlobalScope('tenant')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('id')
                ->get()
                ->filter(function (User $u) use ($wanted): bool {
                    $raw = (string) $u->getRawOriginal('phone');
                    foreach (PhoneNormalizer::comparisonVariants($raw) as $x) {
                        if (isset($wanted[$x])) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values();
        }

        return User::withoutGlobalScope('tenant')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($q) use ($digitVariants): void {
                foreach ($digitVariants as $v) {
                    $q->orWhereRaw(
                        "regexp_replace(phone, '[^0-9]+', '', 'g') = ?",
                        [$v]
                    );
                }
            })
            ->orderBy('id')
            ->get();
    }
}
