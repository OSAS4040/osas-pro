<?php
chdir('/var/www');
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = DB::table('users')->select('id','name','email','role')->orderBy('role')->get();
foreach ($users as $u) {
    printf("%-22s | %-38s | %s\n", $u->role, $u->email, $u->name);
}
