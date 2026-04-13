<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\CompanyFinancialModel;
use App\Enums\CompanyFinancialModelStatus;
use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * مستأجرون إضافيون لعرض واقعي في لوحة مشغّل المنصة (/admin): عدة شركات، فروع، عملاء، مستخدمون.
 * آمن للتكرار: firstOrCreate / updateOrCreate حسب البريد المميز لكل شركة.
 */
final class PlatformShowcaseTenantsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'email'         => 'showcase.riyadh@demo.sa',
                'name'          => 'Golden Service — Riyadh',
                'name_ar'       => 'الخدمة الذهبية — الرياض',
                'plan'          => 'professional',
                'owner_email'   => 'owner.golden@demo.sa',
                'manager_email' => 'manager.golden@demo.sa',
                'phone_seed'    => '966502010001',
                'manager_phone' => '966502010011',
            ],
            [
                'email'         => 'showcase.khobar@demo.sa',
                'name'          => 'Blue Fleet — Khobar',
                'name_ar'       => 'الأسطول الأزرق — الخبر',
                'plan'          => 'trial',
                'owner_email'   => 'owner.bluefleet@demo.sa',
                'manager_email' => 'manager.bluefleet@demo.sa',
                'phone_seed'    => '966502010002',
                'manager_phone' => '966502020011',
            ],
        ];

        foreach ($tenants as $t) {
            $company = Company::firstOrCreate(
                ['email' => $t['email']],
                [
                    'uuid'       => (string) Str::uuid(),
                    'name'       => $t['name'],
                    'name_ar'    => $t['name_ar'],
                    'phone'      => '+'.ltrim($t['phone_seed'], '+'),
                    'city'       => 'Eastern Province',
                    'country'    => 'SAU',
                    'currency'   => 'SAR',
                    'timezone'   => 'Asia/Riyadh',
                    'status'     => CompanyStatus::Active,
                    'is_active'  => true,
                ]
            );

            $company->update([
                'financial_model'        => CompanyFinancialModel::Prepaid,
                'financial_model_status' => CompanyFinancialModelStatus::ApprovedPrepaid,
            ]);

            $branch = Branch::firstOrCreate(
                ['company_id' => $company->id, 'is_main' => true],
                [
                    'uuid'      => (string) Str::uuid(),
                    'name'      => 'Main',
                    'name_ar'   => 'الفرع الرئيسي',
                    'code'      => 'MAIN-'.substr((string) $company->id, 0, 6),
                    'status'    => BranchStatus::Active,
                    'is_active' => true,
                ]
            );

            Subscription::firstOrCreate(
                ['company_id' => $company->id],
                [
                    'uuid'         => (string) Str::uuid(),
                    'plan'         => $t['plan'],
                    'status'       => SubscriptionStatus::Active,
                    'starts_at'    => now()->subDays(14),
                    'ends_at'      => now()->addYear(),
                    'amount'       => 0,
                    'currency'     => 'SAR',
                    'max_branches' => 8,
                    'max_users'    => 30,
                ]
            );

            $ownerPhone = PhoneNormalizer::normalizeForStorage($t['phone_seed']);
            User::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email'      => $t['owner_email'],
                ],
                [
                    'branch_id'            => $branch->id,
                    'name'                 => 'مالك — '.$t['name_ar'],
                    'password'             => 'password',
                    'phone'                => $ownerPhone,
                    'phone_verified_at'    => now(),
                    'registration_stage'   => 'phone_verified',
                    'role'                 => UserRole::Owner,
                    'status'               => UserStatus::Active,
                    'is_active'          => true,
                    'is_platform_user'   => false,
                    'platform_role'      => null,
                ]
            );

            $mgrPhone = PhoneNormalizer::normalizeForStorage($t['manager_phone']);
            User::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email'      => $t['manager_email'],
                ],
                [
                    'branch_id'          => $branch->id,
                    'name'               => 'مدير تشغيل',
                    'password'           => 'password',
                    'phone'              => $mgrPhone,
                    'phone_verified_at'  => now(),
                    'registration_stage' => 'phone_verified',
                    'role'               => UserRole::Manager,
                    'status'             => UserStatus::Active,
                    'is_active'          => true,
                ]
            );

            for ($c = 0; $c < 4; $c++) {
                Customer::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'email'      => 'showcase-c-'.$company->id.'-'.$c.'@demo.local',
                    ],
                    [
                        'uuid'       => (string) Str::uuid(),
                        'branch_id'  => $branch->id,
                        'type'       => 'b2c',
                        'name'       => 'عميل عرض '.$company->id.' — '.($c + 1),
                        'name_ar'    => 'عميل عرض '.($c + 1),
                        'phone'      => '+9665'.sprintf('%08d', 30000000 + $company->id * 10 + $c),
                        'is_active'  => true,
                    ]
                );
            }
        }

        $this->command?->info(
            'PlatformShowcaseTenantsSeeder: owners/managers password = «password» — '.
            'owner.golden@demo.sa، owner.bluefleet@demo.sa'
        );
    }
}
