<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plan    = App\Models\Plan::where('slug', 'professional')->first();
$company = App\Models\Company::where('email', 'demo@autocenter.sa')->first();

if (!$company) { echo "Company not found\n"; exit(1); }
if (!$plan)    { echo "Plan not found\n"; exit(1); }

$sub = App\Models\Subscription::where('company_id', $company->id)->first();
if (!$sub)     { echo "Subscription not found\n"; exit(1); }

$sub->update([
    'features'     => $plan->features,
    'max_branches' => $plan->max_branches,
    'max_users'    => $plan->max_users,
    'status'       => 'active',
    'plan'         => 'professional',
]);

echo "Updated subscription features: " . json_encode($plan->features) . "\n";
echo "Plan slug: " . $plan->slug . "\n";
