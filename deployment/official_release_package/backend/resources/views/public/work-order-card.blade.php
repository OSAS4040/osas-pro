<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>أمر عمل {{ $order->order_number }}</title>
    <style>
        body { font-family: system-ui, "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 520px; margin: 24px auto; padding: 0 16px; }
        .card {
            background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(15, 118, 110, 0.12);
            border: 1px solid #e2e8f0; overflow: hidden;
        }
        .head {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            color: #fff; padding: 20px 22px;
        }
        .head h1 { margin: 0; font-size: 1.15rem; font-weight: 700; }
        .head p { margin: 6px 0 0; opacity: 0.92; font-size: 0.85rem; }
        .body { padding: 20px 22px; }
        dl { margin: 0; display: grid; gap: 12px; }
        .row { display: flex; justify-content: space-between; gap: 12px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        .row:last-child { border-bottom: none; padding-bottom: 0; }
        dt { color: #64748b; font-weight: 600; }
        dd { margin: 0; text-align: left; font-weight: 500; }
        .foot { padding: 14px 22px; background: #f8fafc; font-size: 0.75rem; color: #64748b; text-align: center; line-height: 1.5; }
    </style>
</head>
<body>
@php
    $issuer = \App\Support\BrandDisplayNames::companyTradeNameAr($order->company);
    $branch = $order->branch?->name_ar ?: $order->branch?->name;
    $statusAr = match ($order->status->value) {
        'draft' => 'مسودة',
        'pending_manager_approval' => 'بانتظار الاعتماد',
        'approved' => 'معتمد',
        'cancellation_requested' => 'طلب إلغاء',
        'in_progress' => 'قيد التنفيذ',
        'on_hold' => 'معلّق',
        'completed' => 'مكتمل',
        'delivered' => 'مُسلَّم',
        'cancelled' => 'ملغى',
        default => $order->status->value,
    };
@endphp
<div class="wrap">
    <div class="card">
        <div class="head">
            <h1>التحقق من أمر العمل</h1>
            <p>{{ $issuer ?? 'مقدّم خدمة' }}</p>
        </div>
        <div class="body">
            <dl>
                <div class="row"><dt>رقم الأمر</dt><dd>{{ $order->order_number }}</dd></div>
                <div class="row"><dt>الحالة</dt><dd>{{ $statusAr }}</dd></div>
                <div class="row"><dt>العميل</dt><dd>{{ $order->customer?->name ?? '—' }}</dd></div>
                <div class="row"><dt>اللوحة</dt><dd dir="ltr" style="text-align:right;">{{ $order->vehicle?->plate_number ?? '—' }}</dd></div>
                <div class="row"><dt>الفرع</dt><dd>{{ $branch ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="foot">
            هذه الصفحة تعرض بياناتاً أساسية للتحقق فقط. التفاصيل المالية والفنية متاحة داخل النظام للمخوّلين.
        </div>
    </div>
</div>
</body>
</html>
