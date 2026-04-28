<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = User::query()->where('email', 'simulation.owner@demo.local')->firstOrFail();
$token = $u->createToken('debug')->plainTextToken;
$base = rtrim((string) (getenv('BULK_DEBUG_BASE_URL') ?: 'http://nginx'), '/');
$url = $base.'/api/v1/work-orders/bulk';
$r = Http::withHeaders(['Accept' => 'application/json'])
    ->withToken($token)
    ->post($url, [
        'vehicle_ids' => [8, 9, 10],
        'service_code' => 'oil_change',
    ]);

echo $r->status().PHP_EOL;
echo $r->body().PHP_EOL;
