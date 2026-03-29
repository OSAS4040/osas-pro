<?php
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== المستخدمون ===\n";
foreach (\App\Models\User::limit(10)->get(['email', 'name']) as $u) {
    echo $u->email . " | " . $u->name . "\n";
}

// Try password reset for owner
$user = \App\Models\User::where('email', 'owner@demo.sa')->first();
if ($user) {
    $user->password = bcrypt('Password123!');
    $user->save();
    echo "\nتم إعادة كلمة مرور owner@demo.sa إلى Password123!\n";
}
