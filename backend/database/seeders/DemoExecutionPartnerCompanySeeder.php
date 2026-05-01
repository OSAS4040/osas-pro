<?php

namespace Database\Seeders;

use App\Enums\CompanyFinancialModel;
use App\Enums\CompanyFinancialModelStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * مستأجر ديمو لوضع «شريك تنفيذ المنصة»: لا إنشاء فاتورة يدوي من الواجهة، وعمليات فقط.
 * تسجيل الدخول: owner.execution@demo.sa / Password123!
 */
class DemoExecutionPartnerCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['email' => 'execution.partner@demo.sa'],
            [
                'uuid'       => Str::uuid(),
                'name'       => 'Demo — شريك تنفيذ المنصة',
                'name_ar'    => 'ديمو شريك تنفيذ المنصة',
                'phone'      => '+966500000099',
                'city'       => 'Riyadh',
                'country'    => 'SAU',
                'currency'   => 'SAR',
                'timezone'   => 'Asia/Riyadh',
                'status'     => 'active',
                'is_active'  => true,
            ]
        );

        $company->update([
            'financial_model'          => CompanyFinancialModel::Prepaid,
            'financial_model_status'   => CompanyFinancialModelStatus::ApprovedPrepaid,
        ]);

        $settings = is_array($company->settings) ? $company->settings : [];
        $profile  = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $matrix   = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];
        $matrix['platform_execution_partner'] = true;
        $profile['feature_matrix']            = $matrix;
        $profile['business_type']             = $profile['business_type'] ?? 'service_center';
        $settings['business_profile']          = $profile;
        $company->update(['settings' => $settings]);

        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'is_main' => true],
            [
                'uuid'      => Str::uuid(),
                'name'      => 'Main Branch',
                'name_ar'   => 'الفرع الرئيسي',
                'code'      => 'EXEC-MAIN',
                'status'    => 'active',
                'is_active' => true,
            ]
        );

        User::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'company_id' => $company->id,
                'email'      => 'owner.execution@demo.sa',
            ],
            [
                'branch_id'          => $branch->id,
                'name'               => 'مالك — شريك تنفيذ',
                'password'           => 'Password123!',
                'phone'              => PhoneNormalizer::normalizeForStorage('966501234599'),
                'phone_verified_at'  => now(),
                'registration_stage' => 'phone_verified',
                'role'               => UserRole::Owner,
                'status'             => UserStatus::Active,
                'is_active'          => true,
            ]
        );

        Subscription::firstOrCreate(
            ['company_id' => $company->id],
            [
                'uuid'         => Str::uuid(),
                'plan'         => 'professional',
                'status'       => 'active',
                'starts_at'    => now(),
                'ends_at'      => now()->addYear(),
                'amount'       => 0,
                'currency'     => 'SAR',
                'max_branches' => 5,
                'max_users'    => 10,
            ]
        );

        $catalog = [
            ['name' => 'تغيير زيت المحرك', 'sku' => 'EP-SVC-001', 'price' => 150, 'type' => 'service'],
            ['name' => 'فحص شامل للمركبة', 'sku' => 'EP-SVC-002', 'price' => 300, 'type' => 'service'],
            ['name' => 'فلتر زيت', 'sku' => 'EP-PRD-001', 'price' => 35, 'type' => 'physical'],
        ];
        foreach ($catalog as $row) {
            Product::firstOrCreate(
                ['company_id' => $company->id, 'sku' => $row['sku']],
                [
                    'uuid'            => Str::uuid(),
                    'company_id'      => $company->id,
                    'name'            => $row['name'],
                    'name_ar'         => $row['name'],
                    'sku'             => $row['sku'],
                    'sale_price'      => $row['price'],
                    'cost_price'      => round($row['price'] * 0.6, 2),
                    'product_type'    => $row['type'],
                    'is_active'       => true,
                    'track_inventory' => false,
                    'tax_rate'        => 15,
                ]
            );
        }

        $this->command?->info(
            'DemoExecutionPartnerCompanySeeder: owner.execution@demo.sa / Password123! — platform_execution_partner=true'
        );
    }
}
