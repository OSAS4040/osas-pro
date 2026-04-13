<?php
$base = 'http://saas_nginx/api/v1';

function req2($method, $url, $data = [], $token = null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_POSTFIELDS     => $data ? json_encode($data) : null,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($body, true), 'raw' => $body];
}

$r = req2('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
echo "Login: HTTP {$r['code']}\n";
$token = $r['body']['token'] ?? null;
echo "Token: " . substr($token, 0, 30) . "...\n\n";

$r = req2('GET', "$base/customers?per_page=5", [], $token);
echo "Customers: HTTP {$r['code']}\n";
echo substr($r['raw'], 0, 500) . "\n\n";

$r = req2('GET', "$base/vehicles?per_page=5", [], $token);
echo "Vehicles: HTTP {$r['code']}\n";
echo substr($r['raw'], 0, 500) . "\n\n";
