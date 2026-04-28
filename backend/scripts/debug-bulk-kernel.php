<?php

use App\Models\User;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = User::query()->where('email', 'simulation.owner@demo.local')->firstOrFail();
$token = $u->createToken('debug')->plainTextToken;
$body = json_encode([
    'vehicle_ids' => [8, 9, 10],
    'service_code' => 'oil_change',
], JSON_THROW_ON_ERROR);

$request = Request::create(
    '/api/v1/work-orders/bulk',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'CONTENT_TYPE' => 'application/json',
    ],
    $body,
);

$response = $kernel->handle($request);
echo $response->getStatusCode().PHP_EOL;
echo $response->getContent().PHP_EOL;
$kernel->terminate($request, $response);
