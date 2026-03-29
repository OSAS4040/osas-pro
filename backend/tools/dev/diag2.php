<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$issues = [];

// 1. Check employees under /api/v1/employees (not workshop)
$count = App\Models\Employee::count();
echo "Employees total: $count\n";

// 2. Check wallets route - /api/v1/wallets
// Search for what routes handle GET /wallets
$routes = app('router')->getRoutes();
$walletRoutes = [];
foreach ($routes as $r) {
    $uri = $r->uri();
    if (strpos($uri, 'wallet') !== false) {
        $walletRoutes[] = $r->methods()[0] . ' ' . $uri;
    }
}
echo "\nWallet Routes:\n";
foreach ($walletRoutes as $wr) echo "  $wr\n";

// 3. Check referral route
$referralRoutes = [];
foreach ($routes as $r) {
    $uri = $r->uri();
    if (strpos($uri, 'referral') !== false || strpos($uri, 'loyalty') !== false) {
        $referralRoutes[] = $r->methods()[0] . ' ' . $uri;
    }
}
echo "\nReferral/Loyalty Routes:\n";
foreach ($referralRoutes as $rr) echo "  $rr\n";

// 4. Check subscription response for /subscription
$user = App\Models\User::where('email','owner@demo.sa')->first();
$sub = App\Models\Subscription::where('company_id', $user->company_id)->first();
$plan = App\Models\Plan::where('slug', $sub->plan ?? 'professional')->first();
echo "\nSubscription check:\n";
echo "  sub->plan (field): " . ($sub->plan ?? 'NULL') . "\n";
echo "  Plan found: " . ($plan ? $plan->slug : 'NOT FOUND') . "\n";
echo "  Plan features: " . json_encode($plan->features ?? null) . "\n";
