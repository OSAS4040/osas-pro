<?php
$ch = curl_init('http://172.19.0.8/api/v1/auth/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['email' => 'owner@demo.sa', 'password' => 'Password123!']),
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: $code\n";
echo "Body: $body\n";
$decoded = json_decode($body, true);
echo "Token path data.token: " . ($decoded['data']['token'] ?? 'NOT FOUND') . "\n";
echo "Token path token: " . ($decoded['token'] ?? 'NOT FOUND') . "\n";
echo "Token path data.access_token: " . ($decoded['data']['access_token'] ?? 'NOT FOUND') . "\n";
