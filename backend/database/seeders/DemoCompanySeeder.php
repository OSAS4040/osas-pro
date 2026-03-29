<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
                'name'  => 'Demo Customer',
                'email' => 'customer@demo.sa',
                'role'  => 'customer',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'uuid'       => Str::uuid(),
                    'company_id' => $company->id,
                    'branch_id'  => $branch->id,
                    'name'       => $userData['name'],
                    'password'   => Hash::make('password'),
                    'role'       => $userData['role'],
                    'status'     => 'active',
                    'is_active'  => true,
                ]
            );
            // Update password for existing users to ensure correct value
            $user->update(['password' => Hash::make('password')]);
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
