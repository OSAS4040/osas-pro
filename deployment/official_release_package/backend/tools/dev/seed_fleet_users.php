<?php
// Seed fleet users for testing

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Customer;

$companyId = 1;
$branchId  = 1;

// أخذ أول عميل موجود لربطه بـ fleet users
$customer = Customer::where('company_id', $companyId)->first();
if (!$customer) {
    echo "لا يوجد عميل لربط fleet users به.\n";
    exit(1);
}

// fleet_contact
$fc = User::updateOrCreate(
    ['email' => 'fleet.contact@demo.sa'],
    [
        'uuid'        => \Illuminate\Support\Str::uuid(),
        'name'        => 'مدخل طلبات الأسطول',
        'password'    => Hash::make('Password123!'),
        'role'        => 'fleet_contact',
        'company_id'  => $companyId,
        'branch_id'   => $branchId,
        'customer_id' => $customer->id,
        'status'      => 'active',
        'is_active'   => true,
    ]
);
echo "fleet_contact: {$fc->email} (customer_id={$fc->customer_id})\n";

// fleet_manager
$fm = User::updateOrCreate(
    ['email' => 'fleet.manager@demo.sa'],
    [
        'uuid'        => \Illuminate\Support\Str::uuid(),
        'name'        => 'مدير أسطول تجريبي',
        'password'    => Hash::make('Password123!'),
        'role'        => 'fleet_manager',
        'company_id'  => $companyId,
        'branch_id'   => $branchId,
        'customer_id' => $customer->id,
        'status'      => 'active',
        'is_active'   => true,
    ]
);
echo "fleet_manager: {$fm->email} (customer_id={$fm->customer_id})\n";
echo "Done.\n";
