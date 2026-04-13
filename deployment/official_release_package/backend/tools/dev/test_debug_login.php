<?php
$base = 'http://localhost/api/v1';

// Try login and show full response
$ch = curl_init($base . '/auth/login');
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode(['email' => 'owner@demo.sa', 'password' => 'password']),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => 1,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo 'Login HTTP: ' . $code . PHP_EOL;
echo 'Response: ' . $body . PHP_EOL;

// Try health check
$ch5 = curl_init($base . '/health');
curl_setopt($ch5, CURLOPT_RETURNTRANSFER, 1);
$hbody = curl_exec($ch5);
echo 'Health HTTP: ' . curl_getinfo($ch5, CURLINFO_HTTP_CODE) . PHP_EOL;
echo 'Health: ' . $hbody . PHP_EOL;

// List users in DB
try {
    require '/var/www/vendor/autoload.php';
    $app = require_once '/var/www/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $users = \App\Models\User::take(5)->get(['id','email','role']);
    echo 'Users: ' . $users->toJson() . PHP_EOL;
} catch (\Throwable $e) {
    echo 'DB Error: ' . $e->getMessage() . PHP_EOL;
}
