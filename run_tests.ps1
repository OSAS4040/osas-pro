$base = "http://localhost/api/v1"
$pass = 0; $fail = 0; $token = ""

function Test-Pass($name) { Write-Host "PASS  $name" -ForegroundColor Green; $global:pass++ }
function Test-Fail($name, $reason) { Write-Host "FAIL  $name -- $reason" -ForegroundColor Red; $global:fail++ }

function Req($method, $path, $bodyObj = $null) {
    $url = "$global:base$path"
    $h = @{"Accept"="application/json"; "Content-Type"="application/json"}
    if ($global:token) { $h["Authorization"] = "Bearer $global:token" }
    $args = @("-s", "-X", $method)
    foreach ($k in $h.Keys) { $args += "-H"; $args += "${k}: $($h[$k])" }
    if ($bodyObj) {
        $tmp = "$env:TEMP\req_body.json"
        $bodyObj | ConvertTo-Json -Compress | Set-Content $tmp -Encoding UTF8
        $args += "-d"; $args += "@$tmp"
    }
    $args += $url
    $resp = & curl.exe @args 2>&1
    try { return $resp | ConvertFrom-Json } catch { return @{raw=$resp} }
}

Write-Host "`n========================================"
Write-Host "   اختبار شامل لنظام SaaS POS"
Write-Host "========================================`n"

# 1. Health Check
Write-Host "-- 1. فحص الصحة --"
$r = Req "GET" "/health"
if ($r.status -eq "healthy") { Test-Pass "Health Check" } else { Test-Fail "Health Check" $r }
if ($r.checks.database -eq "ok") { Test-Pass "Database Connection: ok" } else { Test-Fail "Database" $r.checks.database }
if ($r.checks.redis -eq "ok") { Test-Pass "Redis Connection: ok" } else { Test-Fail "Redis" $r.checks.redis }

# 2. Auth
Write-Host "`n-- 2. المصادقة --"
$r = Req "POST" "/auth/login" @{email="owner@demo.sa"; password="password"}
if ($r.token) {
    $global:token = $r.token
    Test-Pass "تسجيل الدخول - $($r.user.email)"
} else {
    Test-Fail "تسجيل الدخول" ($r | ConvertTo-Json -Compress)
}

if (-not $global:token) { Write-Host "لا يمكن المتابعة بدون Token." -ForegroundColor Red; exit 1 }

$r2 = Req "POST" "/auth/login" @{email="wrong@test.com"; password="wrong"}
if (-not $r2.token) { Test-Pass "رفض بيانات خاطئة" } else { Test-Fail "رفض بيانات خاطئة" "قَبل بيانات خاطئة" }

# 3. Products
Write-Host "`n-- 3. المنتجات --"
$r = Req "GET" "/products?per_page=5"
if ($r.data) { Test-Pass "قراءة قائمة المنتجات" } else { Test-Fail "قراءة قائمة المنتجات" ($r | ConvertTo-Json -Compress) }

$sku = "TEST-PS-$(Get-Random -Maximum 9999)"
$r = Req "POST" "/products" @{
    name="منتج تجريبي PS"; sku=$sku; barcode="PS123456"; 
    sale_price=100; cost_price=60; tax_rate=15; 
    is_active=$true; is_service=$false; unit_id=1
}
if ($r.data.id) {
    $productId = $r.data.id
    Test-Pass "إنشاء منتج: $($r.data.name) ID:$productId"
} else {
    Test-Fail "إنشاء منتج" ($r | ConvertTo-Json -Compress)
    $productId = $null
}

$r = Req "GET" "/products?search=تجريبي"
if ($r.data) { Test-Pass "البحث في المنتجات" } else { Test-Fail "البحث" ($r | ConvertTo-Json -Compress) }

if ($productId) {
    $r = Req "PUT" "/products/$productId" @{name="منتج معدّل"; sale_price=120}
    if ($r.data.id) { Test-Pass "تعديل المنتج" } else { Test-Fail "تعديل المنتج" ($r | ConvertTo-Json -Compress) }
}

# 4. Inventory
Write-Host "`n-- 4. المخزون --"
$r = Req "GET" "/inventory?per_page=5"
if ($r.data) { Test-Pass "قراءة المخزون" } else { Test-Fail "قراءة المخزون" ($r | ConvertTo-Json -Compress) }

$r = Req "GET" "/inventory?low_stock=1"
if ($r.data) { Test-Pass "فلتر المخزون المنخفض" } else { Test-Fail "فلتر المخزون المنخفض" ($r | ConvertTo-Json -Compress) }

$r = Req "GET" "/units"
if ($r.data) { Test-Pass "قراءة وحدات القياس" } else { Test-Fail "وحدات القياس" ($r | ConvertTo-Json -Compress) }

# 5. Customers
Write-Host "`n-- 5. العملاء --"
$r = Req "GET" "/customers?per_page=5"
if ($r.data) { Test-Pass "قراءة العملاء" } else { Test-Fail "قراءة العملاء" ($r | ConvertTo-Json -Compress) }

# 6. Suppliers
Write-Host "`n-- 6. الموردون --"
$r = Req "GET" "/suppliers?per_page=5"
if ($r.data) { Test-Pass "قراءة الموردين" } else { Test-Fail "قراءة الموردين" ($r | ConvertTo-Json -Compress) }

# 7. Work Orders
Write-Host "`n-- 7. أوامر العمل --"
$r = Req "GET" "/work-orders?per_page=5"
if ($r.data) { Test-Pass "قراءة أوامر العمل" } else { Test-Fail "أوامر العمل" ($r | ConvertTo-Json -Compress) }

# 8. Invoices
Write-Host "`n-- 8. الفواتير --"
$r = Req "GET" "/invoices?per_page=5"
if ($r.data) { Test-Pass "قراءة الفواتير" } else { Test-Fail "الفواتير" ($r | ConvertTo-Json -Compress) }

# 9. POS
Write-Host "`n-- 9. نقطة البيع (POS) --"
$prods = Req "GET" "/products?per_page=1&is_active=1"
$p = if ($prods.data.data) {$prods.data.data[0]} elseif ($prods.data[0]) {$prods.data[0]} else {$null}
if ($p) {
    $price = if ($p.sale_price) { [double]$p.sale_price } else { 10 }
    $posData = @{
        customer_id=$null; customer_type="b2c"; discount_amount=0
        items=@(@{name=$p.name; item_type="part"; product_id=$p.id; service_id=$null; unit_price=$price; tax_rate=15; quantity=1})
        payment=@{method="cash"; amount=($price * 1.15)}
    }
    $r = Req "POST" "/pos/sale" $posData
    if ($r.data.id) {
        $invId = $r.data.id; $invNum = $r.data.invoice_number
        Test-Pass "عملية بيع ناجحة - فاتورة: $invNum"
        $r2 = Req "GET" "/invoices/$invId"
        if ($r2.data.id) { Test-Pass "عرض الفاتورة #$invId" } else { Test-Fail "عرض فاتورة" ($r2 | ConvertTo-Json -Compress) }
    } else {
        Test-Fail "عملية POS" ($r | ConvertTo-Json -Compress)
    }
} else {
    Test-Fail "عملية POS" "لا توجد منتجات نشطة"
}

# 10. Reports
Write-Host "`n-- 10. التقارير --"
$r = Req "GET" "/reports/sales?from=2024-01-01&to=2026-12-31"
if ($r.data) { Test-Pass "تقرير المبيعات" } else { Test-Fail "تقرير المبيعات" ($r | ConvertTo-Json -Compress) }

# 11. Logout
Write-Host "`n-- 11. تسجيل الخروج --"
$r = Req "POST" "/auth/logout"
if ($null -ne $r) { Test-Pass "تسجيل الخروج" } else { Test-Fail "تسجيل الخروج" $r }

Write-Host "`n========================================"
Write-Host "   ملخص النتائج"
Write-Host "========================================"
Write-Host "المجموع : $($global:pass + $global:fail)"
Write-Host "ناجح    : $global:pass" -ForegroundColor Green
Write-Host "فاشل    : $global:fail" -ForegroundColor Red
