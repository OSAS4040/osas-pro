<?php
// Debug test - call contracts directly via artisan
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
        CURLOPT_VERBOSE        => false,
    ]);
    if ($data && in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true)];
}

$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'Password123!']);
$token = $r['body']['token'] ?? '';
echo "Token obtained: " . ($token ? 'YES' : 'NO') . "\n";

// Test policies (should work)
$r = req('GET', "$base/governance/policies", [], $token);
echo "Policies: HTTP " . $r['status'] . "\n";

// Test contracts (broken)
$r = req('GET', "$base/governance/contracts", [], $token);
echo "Contracts: HTTP " . $r['status'] . "\n";

// Test audit-logs (in same group)
$r = req('GET', "$base/governance/audit-logs", [], $token);
echo "Audit Logs: HTTP " . $r['status'] . "\n";

// Test alert-rules  
$r = req('GET', "$base/governance/alert-rules", [], $token);
echo "Alert Rules: HTTP " . $r['status'] . "\n";

// Test contracts-expiring
$r = req('GET', "$base/governance/contracts-expiring", [], $token);
echo "Contracts-expiring: HTTP " . $r['status'] . "\n";

// Manual test with bootstrap to check if contracts route resolves
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$router = $app->make('router');
$routes = $router->getRoutes();

// Find contracts route
$found = false;
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'governance/contracts') && !str_contains($route->uri(), '{')) {
        echo "\nRoute found in router: " . implode('|', $route->methods()) . " " . $route->uri() . "\n";
        echo "Action: " . $route->getActionName() . "\n";
        $found = true;
    }
}
if (!$found) echo "\nContracts route NOT found in router!\n";
