<?php
/**
 * test_workshop.php — اختبار شامل للمرحلة 5 (تشغيل الورشة)
 */
$base  = 'http://saas_nginx/api/v1';
$pass = 0; $fail = 0; $fails = [];

function req(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json','Accept: application/json'], $headers)]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}
function pass(string $l): void { global $pass; $pass++; echo "\e[32m✅ PASS\e[0m  $l\n"; }
function fail(string $l, string $r = ''): void {
    global $fail, $fails; $fail++;
    $s = strlen($r) > 120 ? substr($r,0,120).'…' : $r;
    $fails[] = "  ❌ $l — $s"; echo "\e[31m❌ FAIL\e[0m  $l" . ($s ? " — $s" : '') . "\n";
}
function section(string $t): void { echo "\n── $t ──\n"; }

// ── 1. تسجيل الدخول ──
section('1. تسجيل الدخول');
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'Password123!']);
if ($r['code'] === 200 && !empty($r['body']['token'])) { pass('تسجيل دخول owner'); $token = $r['body']['token']; }
else { fail('تسجيل دخول', $r['raw']); exit(1); }
$auth = ["Authorization: Bearer $token"];

// ── 2. إنشاء موظف ──
section('2. إدارة الموظفين');
$r = req('POST', "$base/workshop/employees", [
    'name'        => 'أحمد الفني',
    'phone'       => '0501234567',
    'position'    => 'Technician',
    'department'  => 'Workshop',
    'hire_date'   => '2024-01-01',
    'base_salary' => 3500,
    'skills'      => ['oil_change', 'alignment', 'brakes'],
], $auth);
if ($r['code'] === 201 && !empty($r['body']['data']['id'])) {
    pass('إنشاء موظف: أحمد الفني');
    $empId = $r['body']['data']['id'];
    $empNo = $r['body']['data']['employee_number'];
    echo "   رقم الموظف: $empNo\n";
} else { fail('إنشاء موظف', $r['raw']); $empId = null; exit(1); }

$r = req('POST', "$base/workshop/employees", [
    'name'     => 'محمد الفني',
    'position' => 'Technician',
    'skills'   => ['oil_change','tire_rotation'],
], $auth);
$r['code'] === 201 ? pass('إنشاء موظف: محمد الفني') && ($empId2 = $r['body']['data']['id']) : fail('موظف 2', $r['raw']);
$empId2 = $r['body']['data']['id'] ?? null;

$r = req('GET', "$base/workshop/employees", [], $auth);
$count = $r['body']['total'] ?? count($r['body']['data'] ?? []);
$r['code'] === 200 ? pass("قراءة قائمة الموظفين ($count موظف)") : fail('قراءة الموظفين', $r['raw']);

$r = req('PUT', "$base/workshop/employees/$empId", ['base_salary' => 4000, 'status' => 'active'], $auth);
$r['code'] === 200 ? pass('تحديث بيانات الموظف') : fail('تحديث الموظف', $r['raw']);

// ── 3. الحضور ──
section('3. تسجيل الحضور');
$r = req('POST', "$base/workshop/attendance/check-in", [
    'employee_id' => $empId,
    'latitude'    => 24.7136,
    'longitude'   => 46.6753,
    'device_id'   => 'DEVICE-TEST-001',
], $auth);
$r['code'] === 201 ? pass('تسجيل حضور: أحمد الفني') : fail('تسجيل الحضور', $r['raw']);

$r = req('GET', "$base/workshop/attendance/$empId/today", [], $auth);
($r['code'] === 200 && $r['body']['data']['status'] === 'checked_in')
    ? pass('حالة الحضور: checked_in') : fail('حالة الحضور', $r['raw']);

$r = req('POST', "$base/workshop/attendance/check-out", ['employee_id' => $empId], $auth);
$r['code'] === 201 ? pass('تسجيل انصراف: أحمد الفني') : fail('تسجيل الانصراف', $r['raw']);

$r = req('GET', "$base/workshop/attendance/$empId/today", [], $auth);
($r['code'] === 200 && $r['body']['data']['status'] === 'checked_out')
    ? pass('حالة الانصراف: checked_out ✓') : fail('حالة الانصراف', $r['raw']);

$r = req('GET', "$base/workshop/attendance/$empId/month?year=" . date('Y') . "&month=" . date('n'), [], $auth);
$r['code'] === 200 ? pass('تقرير الحضور الشهري') : fail('تقرير الحضور', $r['raw']);

// ── 4. المهام ──
section('4. إدارة المهام (Task Engine)');
$r = req('POST', "$base/workshop/tasks", [
    'title'             => 'تغيير زيت - بي ام دبليو',
    'type'              => 'service',
    'priority'          => 'high',
    'assigned_to'       => $empId,
    'estimated_minutes' => 30,
    'due_at'            => date('Y-m-d H:i:s', strtotime('+2 hours')),
], $auth);
if ($r['code'] === 201 && !empty($r['body']['data']['id'])) {
    pass('إنشاء مهمة يدوية');
    $taskId = $r['body']['data']['id'];
} else { fail('إنشاء مهمة', $r['raw']); $taskId = null; }

$r = req('POST', "$base/workshop/tasks", [
    'title'       => 'فحص فرامل - كامري',
    'type'        => 'inspection',
    'priority'    => 'normal',
    'auto_assign' => true,
    'skill'       => 'brakes',
], $auth);
($r['code'] === 201 && !empty($r['body']['data']['assigned_to']))
    ? pass('إنشاء مهمة بالتعيين التلقائي (auto_assign) — assigned to: ' . ($r['body']['data']['assigned_to'] ?? '?'))
    : fail('auto_assign', $r['raw']);

if ($taskId) {
    $r = req('PATCH', "$base/workshop/tasks/$taskId/status", ['action' => 'start'], $auth);
    ($r['code'] === 200 && $r['body']['data']['status'] === 'in_progress')
        ? pass('تحديث حالة المهمة → in_progress') : fail('تحديث حالة المهمة', $r['raw']);

    $r = req('PATCH', "$base/workshop/tasks/$taskId/status", ['action' => 'complete', 'notes' => 'اكتمل بنجاح', 'actual_minutes' => 25], $auth);
    ($r['code'] === 200 && $r['body']['data']['status'] === 'completed')
        ? pass('إكمال المهمة + وقت فعلي') : fail('إكمال المهمة', $r['raw']);
}

$r = req('GET', "$base/workshop/tasks/stats", [], $auth);
($r['code'] === 200 && isset($r['body']['data']['completed']))
    ? pass('إحصائيات المهام: ' . json_encode($r['body']['data']))
    : fail('إحصائيات المهام', $r['raw']);

// ── 5. العمولات ──
section('5. نظام العمولات');
$r = req('POST', "$base/workshop/commission-rules", [
    'applies_to' => 'invoice',
    'rate'       => 5.0,
    'min_amount' => 100,
    'is_active'  => true,
], $auth);
$r['code'] <= 201 ? pass('إنشاء قاعدة عمولة (5% على الفواتير ≥100)') : fail('قاعدة العمولة', $r['raw']);

$r = req('POST', "$base/workshop/commission-rules", [
    'employee_id' => $empId,
    'applies_to'  => 'invoice',
    'rate'        => 8.0,
    'min_amount'  => 500,
    'is_active'   => true,
], $auth);
$r['code'] <= 201 ? pass('إنشاء قاعدة عمولة خاصة بالموظف (8%)') : fail('قاعدة عمولة الموظف', $r['raw']);

$r = req('GET', "$base/workshop/commissions", [], $auth);
$r['code'] === 200 ? pass('قراءة سجل العمولات') : fail('سجل العمولات', $r['raw']);

// ── ملخص ──
echo "\n" . str_repeat('=',40) . "\n   ملخص اختبار المرحلة 5 — الورشة\n" . str_repeat('=',40) . "\n";
echo "المجموع : " . ($pass+$fail) . " اختبار\n";
echo "\e[32mناجح    : $pass\e[0m\n";
if ($fail > 0) { echo "\e[31mفاشل    : $fail\e[0m\n\nالفاشلة:\n" . implode("\n",$fails) . "\n"; }
else { echo "\e[32m\n✅ جميع الاختبارات ناجحة!\e[0m\n"; }
