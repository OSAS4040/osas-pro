<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = \App\Models\User::where('email', 'fleet.contact@demo.sa')->first();
echo "canLogin=" . ($u->canLogin() ? 'true' : 'false') . "\n";
echo "role=" . $u->role->value . "\n";
echo "status=" . $u->status->value . "\n";
echo "is_active=" . ($u->is_active ? 'true' : 'false') . "\n";
echo "customer_id=" . $u->customer_id . "\n";

// Test login via the service
$sub = \App\Models\Subscription::where('company_id', 1)->first();
echo "subscription=" . ($sub ? $sub->status->value : 'none') . "\n";

// Test formatUser
$ctrl = new \App\Http\Controllers\Api\V1\Auth\AuthController();
$ref = new ReflectionMethod($ctrl, 'getUserPermissions');
$ref->setAccessible(true);
$perms = $ref->invoke($ctrl, $u->role);
echo "permissions=" . json_encode($perms) . "\n";
