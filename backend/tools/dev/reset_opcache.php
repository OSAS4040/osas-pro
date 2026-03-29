<?php
// Reset opcache and test contracts
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset\n";
} else {
    echo "OPcache not available\n";
}

// Now test via HTTP
$base = 'http://saas_nginx/api/v1';
function req(string $method, string $url, array $data = [], string $token = null): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    if (in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
        $headers[] = 'Idempotency-Key: test-' . uniqid('', true);
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15]);
    if ($data && in_array(strtoupper($method), ['POST','PUT','PATCH'])) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true)];
}
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'Password123!']);
$token = $r['body']['token'] ?? '';
$r = req('GET', "$base/governance/contracts", [], $token);
echo "Contracts after opcache reset: HTTP " . $r['status'] . "\n";
echo substr(json_encode($r['body']), 0, 200) . "\n";
