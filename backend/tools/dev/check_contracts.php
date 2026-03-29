<?php
$base = 'http://saas_nginx/api/v1';

function req(string $method, string $url, array $data = [], string $token = null): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    if (in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
        $headers[] = 'Idempotency-Key: test-' . uniqid('', true);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($data && in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true)];
}

// Login
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'Password123!']);
echo "Login: HTTP " . $r['status'] . "\n";
$token = $r['body']['token'] ?? '';

if (!$token) { echo "No token! Response: " . json_encode($r['body']) . "\n"; exit(1); }

// Direct contract request with verbose output
$r = req('GET', "$base/governance/contracts", [], $token);
echo "Contracts: HTTP " . $r['status'] . "\n";
echo "Response: " . json_encode($r['body']) . "\n";

// Check if contracts table exists
echo "\n--- DB Check ---\n";
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "contracts table: " . (\Illuminate\Support\Facades\Schema::hasTable('contracts') ? 'exists' : 'MISSING') . "\n";
