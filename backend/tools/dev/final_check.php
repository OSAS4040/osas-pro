<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$fleetUser = App\Models\User::where('email','fleet.contact@demo.sa')->first();
if ($fleetUser) {
    $role = $fleetUser->role instanceof \UnitEnum ? $fleetUser->role->value : (string)$fleetUser->role;
    echo "Fleet user exists: role=$role\n";
    echo "Fleet user company_id=" . $fleetUser->company_id . "\n";
}

// Check fleet portal route accessible
$r = App\Models\User::where('email','owner@demo.sa')->first();
echo "Owner company: " . $r->company_id . "\n";

// Check wallets
$wallets = App\Models\CustomerWallet::where('company_id', $r->company_id)->count();
echo "Wallets: $wallets\n";

// Check employees
$emps = App\Models\Employee::where('company_id', $r->company_id)->count();
echo "Employees: $emps\n";
