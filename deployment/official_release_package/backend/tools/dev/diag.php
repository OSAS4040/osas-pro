<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test 1: Subscription
$user = App\Models\User::where('email','owner@demo.sa')->first();
$sub = App\Models\Subscription::where('company_id', $user->company_id)->with('plan')->first();
echo "=== SUBSCRIPTION ===\n";
if ($sub) {
    echo "Plan: " . ($sub->plan ? $sub->plan->slug : 'NULL plan relation') . "\n";
    echo "Status: " . $sub->status . "\n";
    echo "Features: " . $sub->plan->features ?? 'N/A';
} else {
    echo "NO subscription found for company_id: " . $user->company_id . "\n";
    // Check available subscriptions
    $subs = App\Models\Subscription::all();
    echo "All subscriptions: " . $subs->count() . "\n";
}

// Test 2: Employees
echo "\n=== EMPLOYEES ===\n";
$empCount = App\Models\Employee::count();
echo "Total employees: $empCount\n";
$workshopEmp = App\Models\Employee::where('company_id', $user->company_id)->count();
echo "Company employees: $workshopEmp\n";

// Test 3: Wallets route
echo "\n=== WALLETS ===\n";
$wallets = App\Models\Wallet::where('company_id', $user->company_id)->count();
echo "Company wallets: $wallets\n";

// Test 4: Referrals
echo "\n=== REFERRALS ===\n";
try {
    $refs = App\Models\Referral::where('company_id', $user->company_id)->count();
    echo "Referrals: $refs\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
