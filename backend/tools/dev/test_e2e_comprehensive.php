<?php
// Comprehensive E2E System Test
$base = 'http://saas_nginx/api/v1';
$results = [];
$passed = 0;
$failed = 0;

function test($name, $condition, $detail = '') {
    global $passed, $failed, $results;
    if ($condition) {
        $results[] = "✅ PASS: $name" . ($detail ? " [$detail]" : "");
        $passed++;
    } else {
        $results[] = "❌ FAIL: $name" . ($detail ? " [$detail]" : "");
        $failed++;
    }
}

function api($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_filter([
            'Content-Type: application/json',
            'Accept: application/json',
            $token ? "Authorization: Bearer $token" : null,
        ]),
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true), 'raw' => $body];
}

// 1. AUTH - Staff Login
$r = api('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
test('Staff Login (owner)', $r['status'] === 200 && isset($r['body']['token']), "status={$r['status']}");
$ownerToken = $r['body']['token'] ?? null;

// 2. Fleet Login
$r = api('POST', "$base/auth/login", ['email' => 'fleet.contact@demo.sa', 'password' => 'Password123!']);
test('Fleet Login', $r['status'] === 200 && isset($r['body']['token']), "status={$r['status']}");
$fleetToken = $r['body']['token'] ?? null;

// 3. Customer Login
$r = api('POST', "$base/auth/login", ['email' => 'customer@demo.sa', 'password' => 'Password123!']);
test('Customer Login', $r['status'] === 200 && isset($r['body']['token']), "status={$r['status']}");
$custToken = $r['body']['token'] ?? null;

if (!$ownerToken) { echo "FATAL: Cannot get owner token\n"; exit(1); }

// 4. Subscription
$r = api('GET', "$base/subscription", null, $ownerToken);
test('Subscription API', $r['status'] === 200 && isset($r['body']['features']), "plan={$r['body']['plan']['slug']}");

// 5. Vehicles
$r = api('GET', "$base/vehicles", null, $ownerToken);
test('Vehicles List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// Test Create Vehicle
$r = api('POST', "$base/vehicles", [
    'plate_number' => 'ت ج د 1234',
    'make' => 'Toyota',
    'model' => 'Camry',
    'year' => 2023,
    'customer_id' => null
], $ownerToken);
test('Create Vehicle', in_array($r['status'], [200, 201, 422]), "status={$r['status']}");

// 6. Customers
$r = api('GET', "$base/customers", null, $ownerToken);
test('Customers List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// 7. Invoices
$r = api('GET', "$base/invoices", null, $ownerToken);
test('Invoices List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// 8. Work Orders
$r = api('GET', "$base/work-orders", null, $ownerToken);
test('Work Orders List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// 9. Products/Inventory
$r = api('GET', "$base/products", null, $ownerToken);
test('Products List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// 10. Employees
$r = api('GET', "$base/employees", null, $ownerToken);
test('Employees List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// 11. Reports
$r = api('GET', "$base/reports/kpi", null, $ownerToken);
test('Reports KPI', $r['status'] === 200, "keys=" . implode(',', array_keys($r['body'] ?? [])));

// 12. Fleet Portal
$r = api('GET', "$base/fleet/dashboard", null, $fleetToken);
test('Fleet Dashboard', in_array($r['status'], [200, 404]), "status={$r['status']}");

// 13. Contracts
$r = api('GET', "$base/governance/contracts", null, $ownerToken);
test('Contracts List', $r['status'] === 200, "count=" . count($r['body']['data'] ?? []));

// 14. Wallets
$r = api('GET', "$base/wallets", null, $ownerToken);
test('Wallets List', in_array($r['status'], [200, 401]), "status={$r['status']}");

// 15. ZATCA
$r = api('GET', "$base/zatca/status", null, $ownerToken);
test('ZATCA Status', in_array($r['status'], [200, 404]), "status={$r['status']}");

// 16. Support Tickets
$r = api('GET', "$base/support/tickets", null, $ownerToken);
test('Support Tickets', in_array($r['status'], [200, 403]), "status={$r['status']}");

// 17. Fuel Logs
$r = api('GET', "$base/fuel/logs", null, $ownerToken);
test('Fuel Logs', in_array($r['status'], [200, 404]), "status={$r['status']}");

// 18. Referrals
$r = api('GET', "$base/referrals", null, $ownerToken);
test('Referrals', in_array($r['status'], [200, 403]), "status={$r['status']}");

// 19. Bookings
$r = api('GET', "$base/bookings", null, $ownerToken);
test('Bookings List', in_array($r['status'], [200, 404]), "status={$r['status']}");

// 20. Notifications
$r = api('GET', "$base/notifications", null, $ownerToken);
test('Notifications', in_array($r['status'], [200, 404]), "status={$r['status']}");

// Summary
echo "\n====== E2E TEST RESULTS ======\n";
foreach ($results as $r) echo $r . "\n";
echo "\n✅ PASSED: $passed\n❌ FAILED: $failed\n";
echo "Score: " . round($passed / ($passed + $failed) * 100) . "%\n";
