<?php
/**
 * test_governance.php — اختبار شامل للمرحلة 4 (الحوكمة والسياسات)
 */

$base  = 'http://saas_nginx/api/v1';
$pass  = 0;
$fail  = 0;
$fails = [];

function req(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $headers
        ),
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}

function pass(string $label): void { global $pass; $pass++; echo "\e[32m✅ PASS\e[0m  {$label}\n"; }
function fail(string $label, string $reason = ''): void {
    global $fail, $fails; $fail++;
    $short = strlen($reason) > 120 ? substr($reason, 0, 120) . '…' : $reason;
    $fails[] = "  ❌ {$label} — {$short}";
    echo "\e[31m❌ FAIL\e[0m  {$label}" . ($short ? " — {$short}" : '') . "\n";
}
function section(string $t): void { echo "\n── {$t} ──\n"; }

// ── 1. تسجيل الدخول ────────────────────────────────────────
section('1. تسجيل الدخول');
$r = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
if ($r['code'] === 200 && !empty($r['body']['token'])) {
    pass('تسجيل دخول owner');
    $token = $r['body']['token'];
} else {
    fail('تسجيل دخول owner', $r['raw']);
    exit(1);
}
$auth = ["Authorization: Bearer $token"];

// ── 2. السياسات ─────────────────────────────────────────────
section('2. إنشاء وإدارة السياسات');

$r = req('POST', "$base/governance/policies", [
    'code'      => 'discount.max',
    'operator'  => 'lte',
    'value'     => [30],
    'action'    => 'require_approval',
    'is_active' => true,
], $auth);
if ($r['code'] <= 201 && isset($r['body']['data']['id'])) {
    pass('إنشاء سياسة discount.max (≤30%)');
    $policyId = $r['body']['data']['id'];
} else {
    fail('إنشاء سياسة', $r['raw']);
    $policyId = null;
}

$r = req('POST', "$base/governance/policies", [
    'code'      => 'credit.max_amount',
    'operator'  => 'lte',
    'value'     => [5000],
    'action'    => 'block',
    'is_active' => true,
], $auth);
$r['code'] <= 201 ? pass('إنشاء سياسة credit.max_amount (≤5000)') : fail('إنشاء سياسة الائتمان', $r['raw']);

$r = req('GET', "$base/governance/policies", [], $auth);
$count = count($r['body']['data'] ?? []);
($r['code'] === 200 && $count >= 2) ? pass("قراءة السياسات ($count سياسة)") : fail('قراءة السياسات', $r['raw']);

// ── 3. تقييم السياسة ────────────────────────────────────────
section('3. تقييم السياسات (Policy Engine)');

$r = req('POST', "$base/governance/policies/evaluate", ['code' => 'discount.max', 'value' => 20], $auth);
($r['code'] === 200 && $r['body']['passed'] === true) ? pass('تقييم: خصم 20% → مقبول') : fail('تقييم خصم 20%', $r['raw']);

$r = req('POST', "$base/governance/policies/evaluate", ['code' => 'discount.max', 'value' => 45], $auth);
($r['code'] === 200 && $r['body']['passed'] === false && $r['body']['action'] === 'require_approval')
    ? pass('تقييم: خصم 45% → يحتاج موافقة') : fail('تقييم خصم 45%', $r['raw']);

$r = req('POST', "$base/governance/policies/evaluate", ['code' => 'credit.max_amount', 'value' => 3000], $auth);
($r['code'] === 200 && $r['body']['passed'] === true) ? pass('تقييم: ائتمان 3000 → مقبول') : fail('تقييم ائتمان 3000', $r['raw']);

$r = req('POST', "$base/governance/policies/evaluate", ['code' => 'credit.max_amount', 'value' => 8000], $auth);
($r['code'] === 200 && $r['body']['passed'] === false && $r['body']['action'] === 'block')
    ? pass('تقييم: ائتمان 8000 → محجوب') : fail('تقييم ائتمان 8000', $r['raw']);

// ── 4. سجل التدقيق ──────────────────────────────────────────
section('4. سجل التدقيق (Audit Log)');
$r = req('GET', "$base/governance/audit-logs", [], $auth);
$logCount = count($r['body']['data'] ?? []);
($r['code'] === 200) ? pass("قراءة سجل التدقيق ($logCount سجل)") : fail('قراءة سجل التدقيق', $r['raw']);

$r = req('GET', "$base/governance/audit-logs?action=policy.saved", [], $auth);
($r['code'] === 200) ? pass('فلتر سجل التدقيق بالفعل (policy.saved)') : fail('فلتر سجل التدقيق', $r['raw']);

// ── 5. Approval Workflows ────────────────────────────────────
section('5. سير الموافقات (Approval Workflows)');
$r = req('GET', "$base/governance/workflows", [], $auth);
($r['code'] === 200) ? pass('قراءة قائمة الموافقات') : fail('قراءة قائمة الموافقات', $r['raw']);

// ── 6. قواعد التنبيه ─────────────────────────────────────────
section('6. قواعد التنبيه (Alert Rules)');
$r = req('POST', "$base/governance/alert-rules", [
    'code'      => 'discount.unusual',
    'channel'   => 'in_app',
    'condition' => ['threshold' => 40],
    'is_active' => true,
], $auth);
($r['code'] <= 201 && isset($r['body']['data']['id'])) ? pass('إنشاء قاعدة تنبيه') : fail('إنشاء قاعدة تنبيه', $r['raw']);

$r = req('GET', "$base/governance/alert-rules", [], $auth);
($r['code'] === 200) ? pass('قراءة قواعد التنبيه') : fail('قراءة قواعد التنبيه', $r['raw']);

// ── 7. إشعارات المستخدم ─────────────────────────────────────
section('7. إشعارات المستخدم');
$r = req('GET', "$base/governance/alerts/me", [], $auth);
($r['code'] === 200 && isset($r['body']['unread_count'])) ? pass('قراءة إشعاراتي') : fail('قراءة الإشعارات', $r['raw']);

$r = req('POST', "$base/governance/alerts/mark-read", ['ids' => []], $auth);
($r['code'] === 200 && isset($r['body']['marked'])) ? pass('تعليم الإشعارات كمقروءة') : fail('تعليم الإشعارات', $r['raw']);

// ── حذف السياسة ─────────────────────────────────────────────
if ($policyId) {
    section('8. حذف السياسة');
    $r = req('DELETE', "$base/governance/policies/$policyId", [], $auth);
    ($r['code'] === 200) ? pass('حذف سياسة discount.max') : fail('حذف السياسة', $r['raw']);
}

// ── الملخص ──────────────────────────────────────────────────
echo "\n" . str_repeat('=', 40) . "\n   ملخص اختبار المرحلة 4 — الحوكمة\n" . str_repeat('=', 40) . "\n";
echo "المجموع : " . ($pass + $fail) . " اختبار\n";
echo "\e[32mناجح    : $pass\e[0m\n";
if ($fail > 0) {
    echo "\e[31mفاشل    : $fail\e[0m\n\nالاختبارات الفاشلة:\n" . implode("\n", $fails) . "\n";
} else {
    echo "\e[32m\n✅ جميع الاختبارات ناجحة!\e[0m\n";
}
