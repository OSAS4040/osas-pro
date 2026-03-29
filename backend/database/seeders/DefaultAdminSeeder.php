<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
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
        $company = Company::firstOrCreate(
            ['name' => 'OSAS Platform'],
            [
                'uuid'      => (string) Str::uuid(),
                'name_ar'   => 'منصة أواس',
                'email'     => 'hq@osas.sa',
                'currency'  => 'SAR',
                'timezone'  => 'Asia/Riyadh',
                'status'    => 'active',
                'is_active' => true,
            ]
        );

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

        $user = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'uuid'       => (string) Str::uuid(),
                'company_id' => $company->id,
                'branch_id'  => $branch->id,
                'name'       => 'OSAS Admin',
                'password'   => self::ADMIN_PASSWORD,
                'role'       => 'owner',
                'status'     => 'active',
                'is_active'  => true,
            ]
        );

        $user->forceFill([
            'company_id' => $company->id,
            'branch_id'  => $branch->id,
            'name'       => 'OSAS Admin',
            'password'   => self::ADMIN_PASSWORD,
            'role'       => 'owner',
            'status'     => 'active',
            'is_active'  => true,
        ])->save();

        $this->command?->info('Default admin: ' . self::ADMIN_EMAIL . ' / ' . self::ADMIN_PASSWORD);
    }
}
