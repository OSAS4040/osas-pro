<?php
/**
 * test_final.php — الاختبار الشامل النهائي (المراحل 6+7 + عملية تجريبية)
 */
$base = 'http://saas_nginx/api/v1';
$pass = 0; $fail = 0; $fails = [];

function req(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>strtoupper($method),
        CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$headers)]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code'=>$code,'body'=>json_decode($raw,true)??[],'raw'=>$raw];
}
function pass(string $l): void { global $pass; $pass++; echo "\e[32m✅ PASS\e[0m  $l\n"; }
function fail(string $l, string $r=''): void {
    global $fail,$fails; $fail++;
    $s=strlen($r)>120?substr($r,0,120).'…':$r;
    $fails[]="  ❌ $l — $s"; echo "\e[31m❌ FAIL\e[0m  $l".($s?" — $s":'')."\n";
}
function section(string $t): void { echo "\n── $t ──\n"; }

// ─── تسجيل الدخول ────────────────────────────────────────────────────
section('تسجيل الدخول');
$r = req('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
if ($r['code']===200 && !empty($r['body']['token'])) { pass('owner@demo.sa'); $token=$r['body']['token']; }
else { fail('تسجيل الدخول',$r['raw']); exit(1); }
$auth=["Authorization: Bearer $token"];

// ─── المرحلة 7: SaaS ─────────────────────────────────────────────────
section('المرحلة 7 — SaaS والباقات');

$r = req('GET',"$base/plans");
($r['code']===200 && count($r['body']['data']??[])>=3)
    ? pass('الباقات: '.implode(', ', array_column($r['body']['data'],'slug')))
    : fail('قراءة الباقات',$r['raw']);

$r = req('GET',"$base/subscription",[],  $auth);
($r['code']===200) ? pass('الاشتراك الحالي: '.$r['body']['data']['subscription']['plan'])
                  : fail('الاشتراك الحالي',$r['raw']);

$r = req('GET',"$base/subscription/usage",[],  $auth);
if ($r['code']===200) {
    pass('حدود الاستخدام | فروع: '.$r['body']['usage']['branches'].'/'.$r['body']['limits']['max_branches'].
         ' | مستخدمون: '.$r['body']['usage']['users'].'/'.$r['body']['limits']['max_users']);
} else { fail('حدود الاستخدام',$r['raw']); }

// ─── المرحلة 6: Bays ─────────────────────────────────────────────────
section('المرحلة 6 — الرافعات (Bays/Lifts)');

// إنشاء رافعات
foreach ([
    ['code'=>'L01','name'=>'رافعة 1','type'=>'lift','capabilities'=>['oil_change','brakes']],
    ['code'=>'L02','name'=>'رافعة 2','type'=>'lift','capabilities'=>['alignment']],
    ['code'=>'W01','name'=>'منطقة غسيل','type'=>'wash','capabilities'=>['wash']],
] as $bay) {
    $r = req('POST',"$base/bays",$bay,$auth);
    ($r['code']===201 || $r['code']===422)
        ? pass("رافعة {$bay['code']}: جاهزة")
        : fail("رافعة {$bay['code']}",$r['raw']);
}

$r = req('GET',"$base/bays",[],  $auth);
$bays    = $r['body']['data'] ?? [];
$bayId   = $bays[0]['id'] ?? null;
$bayId2  = $bays[1]['id'] ?? null;
($r['code']===200 && count($bays)>0) ? pass('قراءة الرافعات ('.count($bays).')') : fail('قراءة الرافعات',$r['raw']);

// تغيير الحالة
if ($bayId) {
    $r = req('PATCH',"$base/bays/$bayId/status",['status'=>'maintenance'],$auth);
    ($r['code']===200 && ($r['body']['data']['status']??'')===('maintenance'))
        ? pass('حالة → maintenance') : fail('تغيير الحالة',$r['raw']);
    $r = req('PATCH',"$base/bays/$bayId/status",['status'=>'available'],$auth);
    $r['code']===200 ? pass('حالة → available') : fail('إعادة الحالة',$r['raw']);
}

// ─── المرحلة 6: الحجوزات ─────────────────────────────────────────────
section('المرحلة 6 — الحجوزات');
// وقت فريد بالثانية لتجنب التعارض في كل تشغيل
$secOfDay    = intval(date('G'))*3600 + intval(date('i'))*60 + intval(date('s'));
$slotTotalMin= 7*60 + ($secOfDay % (11*60));
$slotHour    = intval($slotTotalMin / 60);
$slotMin     = (($slotTotalMin % 60) < 30) ? 0 : 30;
$testDay     = date('Y-m-d', strtotime('+14 days'));
$slotStart   = sprintf('%02d:%02d:00', $slotHour, $slotMin);
$slotConflict= sprintf('%02d:%02d:00', $slotHour, $slotMin === 0 ? 15 : 45);
$bookingId   = null;

if ($bayId) {
    // حجز أول
    $r = req('POST',"$base/bookings",[
        'bay_id'           => $bayId,
        'starts_at'        => "$testDay $slotStart",
        'duration_minutes' => 60,
        'service_type'     => 'تغيير زيت وفلتر',
        'source'           => 'manual',
    ],$auth);
    if ($r['code']===201) { pass("حجز L01 $slotStart"); $bookingId=$r['body']['data']['id']; }
    else { fail('إنشاء حجز',$r['raw']); }

    // حجز متعارض — يجب الرفض
    $r = req('POST',"$base/bookings",[
        'bay_id'           => $bayId,
        'starts_at'        => "$testDay $slotConflict",
        'duration_minutes' => 60,
        'service_type'     => 'تعارض',
    ],$auth);
    (isset($r['body']['message']) && str_contains($r['body']['message'],'محجوزة'))
        ? pass('رفض التعارض ✓')
        : fail('يجب رفض التعارض',$r['raw']);
}

// حجز رافعة 2 نفس الوقت — يجب النجاح
if ($bayId2) {
    $r = req('POST',"$base/bookings",[
        'bay_id'           => $bayId2,
        'starts_at'        => "$testDay $slotStart",
        'duration_minutes' => 90,
        'service_type'     => 'ضبط إطارات',
    ],$auth);
    $r['code']===201 ? pass("حجز L02 $slotStart (رافعة مختلفة) ✓") : fail('حجز L02',$r['raw']);
}

// قائمة الحجوزات
$r = req('GET',"$base/bookings?date=$testDay",[],  $auth);
$bc = $r['body']['total'] ?? count($r['body']['data']??[]);
$r['code']===200 ? pass("حجوزات الغد: $bc") : fail('قائمة الحجوزات',$r['raw']);

// فحص التوفر
$r = req('POST',"$base/bookings/availability",[
    'branch_id'        => 1,
    'starts_at'        => "$testDay 14:00:00",
    'duration_minutes' => 60,
    'capability'       => 'oil_change',
],$auth);
($r['code']===200) ? pass('فحص التوفر: '.($r['body']['available']?'رافعة متاحة':'لا يوجد توفر')) : fail('فحص التوفر',$r['raw']);

// الخريطة الحرارية
$r = req('GET',"$base/bays/heatmap?date=$testDay",[],  $auth);
($r['code']===200 && !empty($r['body']['data'])) ? pass('Heatmap ✓ — '.count($r['body']['data']).' رافعة') : fail('Heatmap',$r['raw']);

// ─── عملية تجريبية كاملة (End-to-End) ───────────────────────────────
section('عملية تجريبية كاملة — fleet_contact → موافقة → ورشة');

$rLogin = req('POST',"$base/auth/login",['email'=>'fleet.contact@demo.sa','password'=>'Password123!']);
if ($rLogin['code']!==200) { fail('fleet_contact login'); goto summary; }
$fc = ["Authorization: Bearer {$rLogin['body']['token']}"];
pass('fleet_contact: تسجيل الدخول');

$r = req('GET',"$base/fleet-portal/dashboard",[],  $fc);
$r['code']===200 ? pass('fleet_contact: Dashboard') : fail('fleet_contact dashboard',$r['raw']);

$r = req('GET',"$base/fleet-portal/vehicles",[],  $fc);
$vid = $r['body']['data'][0]['id'] ?? null;
($r['code']===200 && $vid) ? pass('fleet_contact: مركبات ('.count($r['body']['data']??[]).')') : fail('المركبات',$r['raw']);

// شحن رصيد
$idempKey = 'e2e-topup-'.time();
$r = req('POST',"$base/fleet-portal/wallet/top-up",[
    'amount'          => 2000,
    'note'            => 'شحن تجريبي E2E',
    'idempotency_key' => $idempKey,
], array_merge($fc, ["Idempotency-Key: $idempKey"]));
($r['code']===200||$r['code']===201) ? pass('fleet_contact: شحن 2000 ر.س') : fail('شحن الرصيد',$r['raw']);

// إنشاء طلب خدمة
if ($vid) {
    $r = req('POST',"$base/fleet-portal/work-orders",[
        'vehicle_id'         => $vid,
        'customer_complaint' => 'E2E: تغيير زيت وفحص شامل',
        'payment_method'     => 'wallet',
    ],$fc);
    if ($r['code']===201) {
        pass('fleet_contact: طلب خدمة #'.($r['body']['data']['order_number']??'?'));
        $woId = $r['body']['data']['id'] ?? null;
    } else { fail('طلب خدمة',$r['raw']); $woId=null; }
}

// تسجيل دخول fleet_manager وعرض لوحة التحكم
$rFM = req('POST',"$base/auth/login",['email'=>'fleet.manager@demo.sa','password'=>'Password123!']);
if ($rFM['code']===200) {
    $fm = ["Authorization: Bearer {$rFM['body']['token']}"];
    pass('fleet_manager: تسجيل الدخول');
    $r = req('GET',"$base/work-orders?approval_status=pending",[],  $fm);
    $pending = $r['body']['total'] ?? count($r['body']['data']??[]);
    $r['code']===200 ? pass("fleet_manager: طلبات معلقة=$pending") : fail('fleet_manager قائمة',$r['raw']);
} else { fail('fleet_manager login'); }

// التحقق من لوحة المركبة في الورشة
$r = req('POST',"$base/fleet/verify-plate",['plate_number'=>'أ ب ج 1234'],$auth);
$verdict = is_array($r['body']['verdict']) ? json_encode($r['body']['verdict'], JSON_UNESCAPED_UNICODE) : ($r['body']['verdict'] ?? 'ok');
$r['code']===200 ? pass('ورشة: التحقق من اللوحة → '.$verdict) : fail('verify-plate',$r['raw']);

// ─── التحقق من الاختبارات السابقة ────────────────────────────────────
section('مراجعة المراحل السابقة');
foreach ([
    ['GET',"$base/health",[],[],'Health Check'],
    ['GET',"$base/invoices",[],  $auth,'الفواتير'],
    ['GET',"$base/work-orders",[],  $auth,'أوامر العمل'],
    ['GET',"$base/governance/policies",[],  $auth,'السياسات'],
    ['GET',"$base/workshop/employees",[],  $auth,'الموظفون'],
    ['GET',"$base/governance/audit-logs",[],  $auth,'سجل التدقيق'],
] as [$m,$u,$b,$h,$label]) {
    $r = req($m,$u,$b,$h);
    $r['code']===200 ? pass($label) : fail($label,$r['raw']);
}

summary:
echo "\n" . str_repeat('=',50) . "\n";
echo "   الاختبار الشامل النهائي — المراحل 1 إلى 7\n";
echo str_repeat('=',50) . "\n";
$total = $pass + $fail;
echo "المجموع : $total اختبار\n";
echo "\e[32mناجح    : $pass\e[0m\n";
if ($fail>0) {
    echo "\e[31mفاشل    : $fail\e[0m\n\nالاختبارات الفاشلة:\n".implode("\n",$fails)."\n";
} else {
    echo "\e[32m\n✅ جميع المراحل (1-7) تعمل بشكل كامل!\e[0m\n";
}
