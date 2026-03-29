<?php
// Use nginx container hostname
$base = 'http://saas_nginx/api/v1';

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
$resp = json_decode($body, true);
$token = $resp['token'] ?? '';
echo 'Token: ' . ($token ? 'GOT_TOKEN' : 'NONE') . PHP_EOL;

if ($token) {
    $ch2 = curl_init($base . '/vehicles');
    curl_setopt_array($ch2, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => 1,
    ]);
    curl_exec($ch2);
    echo 'Vehicles: HTTP ' . curl_getinfo($ch2, CURLINFO_HTTP_CODE) . PHP_EOL;

    $ch3 = curl_init($base . '/customers');
    curl_setopt_array($ch3, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => 1,
    ]);
    curl_exec($ch3);
    echo 'Customers: HTTP ' . curl_getinfo($ch3, CURLINFO_HTTP_CODE) . PHP_EOL;

    $ch4 = curl_init($base . '/quotes');
    curl_setopt_array($ch4, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => 1,
    ]);
    curl_exec($ch4);
    echo 'Quotes: HTTP ' . curl_getinfo($ch4, CURLINFO_HTTP_CODE) . PHP_EOL;
}

echo 'Done';
