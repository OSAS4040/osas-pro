<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$company = Company::first();
$branch = Branch::where('company_id', $company->id)->first();

$demos = [
    ['name' => 'Customer Demo', 'email' => 'customer@demo.sa', 'role' => 'customer'],
];
foreach ($demos as $d) {
    $u = User::firstOrCreate(['email' => $d['email']], [
        'uuid' => Str::uuid(),
        'name' => $d['name'],
        'role' => $d['role'],
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'password' => Hash::make('Password123!'),
        'status' => 'active',
        'is_active' => true,
    ]);
    $status = $u->wasRecentlyCreated ? 'Created' : 'Already exists';
    echo "$status: {$d['email']}\n";
}
echo "Done.\n";
