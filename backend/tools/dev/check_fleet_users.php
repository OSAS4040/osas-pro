<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$users = User::whereIn('role', ['fleet_contact', 'fleet_manager', 'customer'])->get(['email', 'role', 'name']);
if ($users->isEmpty()) {
    echo "No fleet/customer users found. Creating...\n";
    $company = Company::first();
    $branch = Branch::where('company_id', $company->id)->first();
    $demo = [
        ['name' => 'Fleet Contact',  'email' => 'fleet@demo.sa',    'role' => 'fleet_contact'],
        ['name' => 'Fleet Manager',  'email' => 'fleet.mgr@demo.sa', 'role' => 'fleet_manager'],
        ['name' => 'Customer Demo',  'email' => 'customer@demo.sa', 'role' => 'customer'],
    ];
    foreach ($demo as $d) {
        User::firstOrCreate(['email' => $d['email']], array_merge($d, [
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'password' => Hash::make('Password123!'),
            'status' => 'active', 'is_active' => true,
        ]));
        echo "Created: {$d['email']}\n";
    }
} else {
    foreach ($users as $u) {
        echo $u->email.' | '.($u->role instanceof UserRole ? $u->role->value : $u->role)."\n";
    }
}
