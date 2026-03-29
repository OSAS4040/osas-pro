<?php
// Test subscription + portal APIs
$base = 'http://localhost/api/v1';

function req($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($body, true)];
}

function check($label, $res, $expectCode = 200) {
    $ok = $res['code'] === $expectCode;
    echo ($ok ? "\033[32m✅ PASS\033[0m" : "\033[31m❌ FAIL\033[0m") . "  $label";
    if (!$ok) echo " — HTTP {$res['code']} — " . json_encode($res['body']);
    echo "\n";
    return $ok;
}

// Login as owner
$login = req("$base/auth/login", 'POST', ['email' => 'owner@demo.sa', 'password' => 'password']);
$token = $login['body']['token'] ?? null;
echo "\n── Staff Login ──\n";
check('owner login', $login);

// Subscription endpoint
echo "\n── Subscription API ──\n";
$sub = req("$base/subscription", 'GET', null, $token);
check('GET /subscription', $sub);
if ($sub['code'] === 200) {
    $plan = $sub['body']['data']['plan'] ?? $sub['body']['plan'] ?? null;
    echo "   plan slug = " . ($plan['slug'] ?? '?') . "\n";
    echo "   features  = " . json_encode($plan['features'] ?? []) . "\n";
}

// Plans endpoint
$plans = req("$base/plans", 'GET', null, $token);
echo "\n── Plans API ──\n";
check('GET /plans', $plans);

// Fleet login
echo "\n── Fleet Portal ──\n";
$fleet = req("$base/auth/login", 'POST', ['email' => 'fleet.contact@demo.sa', 'password' => 'password']);
$fToken = $fleet['body']['token'] ?? null;
check('fleet_contact login', $fleet);

if ($fToken) {
    $dash = req("$base/fleet-portal/dashboard", 'GET', null, $fToken);
    check('fleet dashboard', $dash);
    
    $veh = req("$base/vehicles", 'GET', null, $fToken);
    check('fleet vehicles', $veh);
}

// Check portal role info
echo "\n── Role Validation ──\n";
$me = req("$base/auth/me", 'GET', null, $token);
check('GET /auth/me (owner)', $me);
echo "   role = " . ($me['body']['data']['role'] ?? '?') . "\n";

if ($fToken) {
    $fMe = req("$base/auth/me", 'GET', null, $fToken);
    check('GET /auth/me (fleet_contact)', $fMe);
    echo "   role = " . ($fMe['body']['data']['role'] ?? '?') . "\n";
}

echo "\n";
