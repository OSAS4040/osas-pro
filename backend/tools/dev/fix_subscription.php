<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plan = Plan::where('slug', 'professional')->first();
$company = Company::where('email', 'demo@autocenter.sa')->first();

if (! $company) {
    echo "Company not found\n";
    exit(1);
}
if (! $plan) {
    echo "Plan not found\n";
    exit(1);
}

$sub = Subscription::where('company_id', $company->id)->first();
if (! $sub) {
    echo "Subscription not found\n";
    exit(1);
}

$sub->update([
    'features' => $plan->features,
    'max_branches' => $plan->max_branches,
    'max_users' => $plan->max_users,
    'status' => 'active',
    'plan' => 'professional',
]);

echo 'Updated subscription features: '.json_encode($plan->features)."\n";
echo 'Plan slug: '.$plan->slug."\n";
