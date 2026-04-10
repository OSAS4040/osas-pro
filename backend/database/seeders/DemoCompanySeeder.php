<?php

namespace Database\Seeders;

use App\Enums\CompanyFinancialModel;
use App\Enums\CompanyFinancialModelStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Demo Auto Center'],
            [
                'uuid'     => Str::uuid(),
                'name'     => 'Demo Auto Center',
                'name_ar'  => 'مركز ديمو للسيارات',
                'email'    => 'demo@autocenter.sa',
                'phone'    => '+966500000000',
                'city'     => 'Riyadh',
                'country'  => 'SAU',
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status'   => 'active',
                'is_active'=> true,
            ]
        );

        // Isolated demo tenant: approved prepaid model so operational APIs (e.g. work orders) are exercisable in staging/load tests.
        $company->update([
            'financial_model' => CompanyFinancialModel::Prepaid,
            'financial_model_status' => CompanyFinancialModelStatus::ApprovedPrepaid,
        ]);

        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'is_main' => true],
            [
                'uuid'      => Str::uuid(),
                'name'      => 'Main Branch',
                'name_ar'   => 'الفرع الرئيسي',
                'code'      => 'MAIN',
                'status'    => 'active',
                'is_active' => true,
            ]
        );

        $users = [
            [
                'name'  => 'Demo Owner',
                'email' => 'owner@demo.sa',
                'role'  => 'owner',
            ],
            [
                'name'  => 'Demo Manager',
                'email' => 'manager@demo.sa',
                'role'  => 'manager',
            ],
            [
                'name'  => 'Demo Staff',
                'email' => 'staff@demo.sa',
                'role'  => 'staff',
            ],
            [
                'name'  => 'Demo Cashier',
                'email' => 'cashier@demo.sa',
                'role'  => 'cashier',
            ],
            [
                'name'  => 'Demo Technician',
                'email' => 'tech@demo.sa',
                'role'  => 'technician',
            ],
            [
                'name'  => 'Fleet Contact',
                'email' => 'fleet.contact@demo.sa',
                'role'  => 'fleet_contact',
            ],
            [
                'name'  => 'Fleet Manager',
                'email' => 'fleet.manager@demo.sa',
                'role'  => 'fleet_manager',
            ],
            [
                'name'  => 'Demo Customer',
                'email' => 'customer@demo.sa',
                'role'  => 'customer',
            ],
        ];

        /*
         * users: unique (company_id, email) — لا تبحث بالبريد وحده لأن نفس العنوان قد يوجد في شركات أخرى
         * (مثل admin أو ديمو)، فيُنشأ/يُحدَّث دائماً ضمن «Demo Auto Center» فقط.
         * كلمة المرور نصية؛ الـ cast «hashed» على User يخزن الـ hash مرة واحدة.
         */
        $demoPassword = 'password';

        foreach ($users as $userData) {
            User::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email'      => $userData['email'],
                ],
                [
                    'branch_id' => $branch->id,
                    'name'      => $userData['name'],
                    'password'  => $demoPassword,
                    'role'      => UserRole::from($userData['role']),
                    'status'    => UserStatus::Active,
                    'is_active' => true,
                ]
            );
        }

        Subscription::firstOrCreate(
            ['company_id' => $company->id],
            [
                'uuid'       => Str::uuid(),
                'plan'       => 'professional',
                'status'     => 'active',
                'starts_at'  => now(),
                'ends_at'    => now()->addYear(),
                'amount'     => 4990,
                'currency'   => 'SAR',
                'max_branches' => 5,
                'max_users'    => 20,
            ]
        );

        $this->command->info('Demo company seeded: owner@demo.sa / password');
    }
}
