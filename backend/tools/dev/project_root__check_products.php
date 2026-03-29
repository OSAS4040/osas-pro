<?php
// Direct test via artisan - bypass nginx
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/v1/governance/policies', 'GET');
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo substr($response->getContent(), 0, 300) . "\n";

$kernel->terminate($request, $response);
