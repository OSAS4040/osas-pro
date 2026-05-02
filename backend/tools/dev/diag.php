<?php

use App\Models\Employee;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test 1: Subscription
$user = User::where('email', 'owner@demo.sa')->first();
$sub = Subscription::where('company_id', $user->company_id)->with('plan')->first();
echo "=== SUBSCRIPTION ===\n";
if ($sub) {
    echo 'Plan: '.($sub->plan ? $sub->plan->slug : 'NULL plan relation')."\n";
    echo 'Status: '.$sub->status."\n";
    echo 'Features: '.$sub->plan->features ?? 'N/A';
} else {
    echo 'NO subscription found for company_id: '.$user->company_id."\n";
    // Check available subscriptions
    $subs = Subscription::all();
    echo 'All subscriptions: '.$subs->count()."\n";
}

// Test 2: Employees
echo "\n=== EMPLOYEES ===\n";
$empCount = Employee::count();
echo "Total employees: $empCount\n";
$workshopEmp = Employee::where('company_id', $user->company_id)->count();
echo "Company employees: $workshopEmp\n";

// Test 3: Wallets route
echo "\n=== WALLETS ===\n";
$wallets = Wallet::where('company_id', $user->company_id)->count();
echo "Company wallets: $wallets\n";

// Test 4: Referrals
echo "\n=== REFERRALS ===\n";
try {
    $refs = Referral::where('company_id', $user->company_id)->count();
    echo "Referrals: $refs\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
