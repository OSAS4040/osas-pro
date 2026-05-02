<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$u = User::where('email', 'fleet.contact@demo.sa')->first();
echo 'canLogin='.($u->canLogin() ? 'true' : 'false')."\n";
echo 'role='.$u->role->value."\n";
echo 'status='.$u->status->value."\n";
echo 'is_active='.($u->is_active ? 'true' : 'false')."\n";
echo 'customer_id='.$u->customer_id."\n";

// Test login via the service
$sub = Subscription::where('company_id', 1)->first();
echo 'subscription='.($sub ? $sub->status->value : 'none')."\n";

// Test formatUser
$ctrl = new AuthController;
$ref = new ReflectionMethod($ctrl, 'getUserPermissions');
$ref->setAccessible(true);
$perms = $ref->invoke($ctrl, $u->role);
echo 'permissions='.json_encode($perms)."\n";
