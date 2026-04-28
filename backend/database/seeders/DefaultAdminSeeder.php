<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Default tenant admin for local/staging access (idempotent).
 */
class DefaultAdminSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@osas.sa';

    public const ADMIN_PASSWORD = '12345678';

    public function run(): void
    {
        $allowInProd = filter_var((string) env('APP_DEFAULT_ADMIN_SEEDER', ''), FILTER_VALIDATE_BOOLEAN);
        if (app()->environment('production') && ! $allowInProd) {
            $this->command?->warn(
                'DefaultAdminSeeder skipped in production. Set APP_DEFAULT_ADMIN_SEEDER=true to allow explicitly.'
            );

            return;
        }

        $password = trim((string) env('APP_DEFAULT_ADMIN_PASSWORD', self::ADMIN_PASSWORD));
        if ($password === '') {
            $password = self::ADMIN_PASSWORD;
        }

        if (app()->environment('production') && $password === self::ADMIN_PASSWORD) {
            $this->command?->warn(
                'DefaultAdminSeeder skipped in production because APP_DEFAULT_ADMIN_PASSWORD is not set.'
            );

            return;
        }

        // One default tenant: match by canonical email first, else legacy English name (any email),
        // so we never create a second company when the old row used a different email.
        $byEmail = Company::query()->where('email', 'hq@osas.sa')->first();
        $byName = $byEmail ? null : Company::query()
            ->whereIn('name', ['OSAS Platform', 'Asas Platform'])
            ->orderBy('id')
            ->first();
        $company = $byEmail ?? $byName;

        $attrs = [
            'email'     => 'hq@osas.sa',
            'name'      => 'Osas Pro',
            'name_ar'   => 'أسس برو',
            'currency'  => 'SAR',
            'timezone'  => 'Asia/Riyadh',
            'status'    => 'active',
            'is_active' => true,
        ];

        if ($company !== null) {
            $company->update($attrs);
            $company->refresh();
        } else {
            $company = Company::create(array_merge(
                ['uuid' => (string) Str::uuid()],
                $attrs
            ));
        }

        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'is_main' => true],
            [
                'uuid'      => (string) Str::uuid(),
                'name'      => 'Main Branch',
                'name_ar'   => 'الفرع الرئيسي',
                'code'      => 'MAIN',
                'status'    => 'active',
                'is_active' => true,
            ]
        );

        Subscription::firstOrCreate(
            ['company_id' => $company->id],
            [
                'uuid'         => (string) Str::uuid(),
                'plan'         => 'professional',
                'status'       => 'active',
                'starts_at'    => now(),
                'ends_at'      => now()->addYear(),
                'amount'       => 0,
                'currency'     => 'SAR',
                'max_branches' => 10,
                'max_users'    => 50,
            ]
        );

        User::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'company_id' => $company->id,
                'email'      => self::ADMIN_EMAIL,
            ],
            [
                'branch_id'            => $branch->id,
                'name'                 => 'Osas Pro Admin',
                'password'             => $password,
                'phone'                => PhoneNormalizer::normalizeForStorage('966501000099'),
                'phone_verified_at'    => now(),
                'registration_stage'   => 'phone_verified',
                'role'                 => UserRole::Owner,
                'status'               => UserStatus::Active,
                'is_active'            => true,
            ]
        );

        if (app()->environment(['local', 'testing'])) {
            $this->command?->info('Default admin: ' . self::ADMIN_EMAIL . ' / ' . $password);
        } else {
            $this->command?->info('Default admin seeded: ' . self::ADMIN_EMAIL);
        }
    }
}
