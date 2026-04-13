<?php
$ch = curl_init('http://localhost/api/v1/auth/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['email' => 'owner@demo.sa', 'password' => 'Password123!']),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: $code\n$raw\n";
