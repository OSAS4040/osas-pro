<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create super admin company if not exists (no slug column — use email as unique key)
$company = App\Models\Company::firstOrCreate(
    ['email' => 'admin@osas.sa'],
    [
        'name' => 'إدارة المنصة — أسس برو',
        'name_ar' => 'إدارة المنصة',
        'phone' => '0500000001',
        'is_active' => true,
        'cr_number' => '1000000001',
        'tax_number' => '300000000000003',
        'country' => 'SA',
        'city' => 'الرياض',
    ]
);

// Create subscription for admin company
App\Models\Subscription::firstOrCreate(
    ['company_id' => $company->id],
    [
        'uuid' => \Illuminate\Support\Str::uuid(),
        'plan' => 'enterprise',
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addYears(10),
        'amount' => 0,
        'currency' => 'SAR',
        'features' => json_encode(['pos'=>true,'invoices'=>true,'work_orders'=>true,'fleet'=>true,'reports'=>true,'api_access'=>true,'zatca'=>true,'plugins'=>true]),
        'max_branches' => 999,
        'max_users' => 999,
    ]
);

// Create admin branch
$branch = App\Models\Branch::firstOrCreate(
    ['company_id' => $company->id, 'code' => 'ADMIN-HQ'],
    ['name' => 'المقر الرئيسي', 'is_active' => true, 'is_main' => true]
);

// Create super admin user
$existingUser = App\Models\User::where('email', 'superadmin@osas.sa')->first();
if (!$existingUser) {
    App\Models\User::create([
        'name' => 'مدير المنصة',
        'email' => 'superadmin@osas.sa',
        'password' => bcrypt('SuperAdmin@2026!'),
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'role' => 'owner',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    echo "Created super admin user: superadmin@osas.sa / SuperAdmin@2026!\n";
} else {
    $existingUser->update(['password' => bcrypt('SuperAdmin@2026!'), 'is_active' => true]);
    echo "Updated super admin: superadmin@osas.sa / SuperAdmin@2026!\n";
}

echo "Company ID: " . $company->id . "\n";
echo "Login URL: http://localhost/admin\n";
echo "Email: superadmin@osas.sa\n";
echo "Password: SuperAdmin@2026!\n";
