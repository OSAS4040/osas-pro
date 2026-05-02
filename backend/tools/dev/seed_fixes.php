<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Employee;
use App\Models\User;

// Check and seed wallet data + employees for demo company
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = User::where('email', 'owner@demo.sa')->first();
$companyId = $user->company_id;

// 1. Create wallets if none
$walletCount = CustomerWallet::where('company_id', $companyId)->count();
echo "Existing wallets: $walletCount\n";
if ($walletCount == 0) {
    // Get first customer
    $customers = Customer::where('company_id', $companyId)->take(3)->get();
    foreach ($customers as $c) {
        CustomerWallet::firstOrCreate(
            ['company_id' => $companyId, 'customer_id' => $c->id, 'wallet_type' => 'cash'],
            ['balance' => rand(100, 2000), 'status' => 'active', 'currency' => 'SAR']
        );
    }
    echo 'Created wallets for '.$customers->count()." customers\n";
}

// 2. Check employees with branches
$empCount = Employee::where('company_id', $companyId)->count();
echo "Company employees: $empCount\n";
if ($empCount == 0) {
    $branch = Branch::where('company_id', $companyId)->first();
    $branchId = $branch?->id ?? 1;
    for ($i = 1; $i <= 3; $i++) {
        Employee::firstOrCreate(
            ['company_id' => $companyId, 'email' => "emp{$i}@demo.sa"],
            [
                'name' => "موظف $i",
                'phone' => '05'.rand(10000000, 99999999),
                'position' => ['فني', 'كاشير', 'مدير'][($i - 1) % 3],
                'department' => 'التشغيل',
                'branch_id' => $branchId,
                'status' => 'active',
                'hire_date' => now()->subMonths(rand(1, 24)),
                'salary' => rand(3000, 8000),
            ]
        );
    }
    echo "Created 3 demo employees\n";
}

// 3. Check fleet users
$fleetUser = User::where('email', 'fleet.contact@demo.sa')->first();
if ($fleetUser) {
    echo 'Fleet user exists: role='.($fleetUser->role ?? 'N/A')."\n";
}

echo "\nAll OK!\n";
