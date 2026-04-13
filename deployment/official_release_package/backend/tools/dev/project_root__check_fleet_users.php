<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$users = \App\Models\User::whereIn('role', ['fleet_contact','fleet_manager','customer'])->get(['email','role','name']);
if ($users->isEmpty()) {
    echo "No fleet/customer users found. Creating...\n";
    $company = \App\Models\Company::first();
    $branch  = \App\Models\Branch::where('company_id', $company->id)->first();
    $demo = [
        ['name'=>'Fleet Contact',  'email'=>'fleet@demo.sa',    'role'=>'fleet_contact'],
        ['name'=>'Fleet Manager',  'email'=>'fleet.mgr@demo.sa','role'=>'fleet_manager'],
        ['name'=>'Customer Demo',  'email'=>'customer@demo.sa', 'role'=>'customer'],
    ];
    foreach ($demo as $d) {
        \App\Models\User::firstOrCreate(['email'=>$d['email']], array_merge($d, [
            'uuid'=>\Illuminate\Support\Str::uuid(),
            'company_id'=>$company->id,
            'branch_id'=>$branch->id,
            'password'=>\Illuminate\Support\Facades\Hash::make('Password123!'),
            'status'=>'active','is_active'=>true,
        ]));
        echo "Created: {$d['email']}\n";
    }
} else {
    foreach ($users as $u) echo $u->email.' | '.($u->role instanceof \App\Enums\UserRole ? $u->role->value : $u->role)."\n";
}
