<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$user = null;
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('t5')->plainTextToken;

$request = Illuminate\Http\Request::create('/api/v1/plugins', 'GET');
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo substr($response->getContent(), 0, 300) . "\n";
$kernel->terminate($request, $response);
