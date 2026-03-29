<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = \App\Models\Company::first();
$branch  = \App\Models\Branch::where('company_id', $company->id)->first();

$demos = [
    ['name'=>'Customer Demo', 'email'=>'customer@demo.sa', 'role'=>'customer'],
];
foreach ($demos as $d) {
    $u = \App\Models\User::firstOrCreate(['email'=>$d['email']], [
        'uuid'       => \Illuminate\Support\Str::uuid(),
        'name'       => $d['name'],
        'role'       => $d['role'],
        'company_id' => $company->id,
        'branch_id'  => $branch->id,
        'password'   => \Illuminate\Support\Facades\Hash::make('Password123!'),
        'status'     => 'active',
        'is_active'  => true,
    ]);
    $status = $u->wasRecentlyCreated ? 'Created' : 'Already exists';
    echo "$status: {$d['email']}\n";
}
echo "Done.\n";
