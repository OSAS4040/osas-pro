<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'               => 'trial',
                'name'               => 'Trial',
                'name_ar'            => 'تجريبي',
                'price_monthly'      => 0,
                'price_yearly'       => 0,
                'currency'           => 'SAR',
                'max_branches'       => 1,
                'max_users'          => 3,
                'max_products'       => 100,
                'grace_period_days'  => 0,
                'is_active'          => true,
                'sort_order'         => 0,
                'features'           => [
                    'pos'             => true,
                    'invoices'        => true,
                    'work_orders'     => false,
                    'fleet'           => false,
                    'reports'         => false,
                    'api_access'      => false,
                    'zatca'           => false,
                ],
            ],
            [
                'slug'               => 'basic',
                'name'               => 'Basic',
                'name_ar'            => 'الأساسي',
                'price_monthly'      => 199,
                'price_yearly'       => 1990,
                'currency'           => 'SAR',
                'max_branches'       => 1,
                'max_users'          => 5,
                'max_products'       => 500,
                'grace_period_days'  => 15,
                'is_active'          => true,
                'sort_order'         => 1,
                'features'           => [
                    'pos'             => true,
                    'invoices'        => true,
                    'work_orders'     => true,
                    'fleet'           => false,
                    'reports'         => true,
                    'api_access'      => false,
                    'zatca'           => false,
                    'work_order_advanced_pricing' => false,
                ],
            ],
            [
                'slug'               => 'professional',
                'name'               => 'Professional',
                'name_ar'            => 'المهني',
                'price_monthly'      => 499,
                'price_yearly'       => 4990,
                'currency'           => 'SAR',
                'max_branches'       => 5,
                'max_users'          => 20,
                'max_products'       => 5000,
                'grace_period_days'  => 15,
                'is_active'          => true,
                'sort_order'         => 2,
                'features'           => [
                    'pos'             => true,
                    'invoices'        => true,
                    'work_orders'     => true,
                    'fleet'           => true,
                    'reports'         => true,
                    'api_access'      => true,
                    'zatca'           => true,
                    'work_order_advanced_pricing' => true,
                ],
            ],
            [
                'slug'               => 'enterprise',
                'name'               => 'Enterprise',
                'name_ar'            => 'المؤسسي',
                'price_monthly'      => 1499,
                'price_yearly'       => 14990,
                'currency'           => 'SAR',
                'max_branches'       => 999,
                'max_users'          => 999,
                'max_products'       => 999999,
                'grace_period_days'  => 30,
                'is_active'          => true,
                'sort_order'         => 3,
                'features'           => [
                    'pos'             => true,
                    'invoices'        => true,
                    'work_orders'     => true,
                    'fleet'           => true,
                    'reports'         => true,
                    'api_access'      => true,
                    'zatca'           => true,
                    'work_order_advanced_pricing' => true,
                    'dedicated_support' => true,
                    'sla'             => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        (new PlanAddonSeeder)->run();
    }
}
