<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('t4')->plainTextToken;
// Test via php internal request instead of nginx
$ch = curl_init("http://127.0.0.1:80/api/v1/plugins");
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Accept: application/json","Authorization: Bearer $token","Host: saas_nginx"]]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "via 127.0.0.1 Status: $code\n";
echo substr($body,0,100)."\n";
// try artisan route directly
echo "\nRoute list check:\n";
$routes = app('router')->getRoutes();
foreach($routes as $r) {
    if(str_contains($r->uri(),'plugins') && !str_contains($r->uri(),'{')) {
        echo $r->methods()[0] . " " . $r->uri() . " => " . $r->getActionName() . "\n";
    }
}
