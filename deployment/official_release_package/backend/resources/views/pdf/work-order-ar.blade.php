<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        /* Tight layout so typical work orders fit one A4 page (Dompdf). */
        @page { margin: 12px 16px; }
        * { box-sizing: border-box; }
        body {
            font-family: @if(!empty($useArabicPdfFont)) "Noto Naskh Arabic", "noto naskh arabic", @endif "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            direction: rtl;
            text-align: right;
            /* لا تستخدم unicode-bidi: embed على الجذر — يتعارض مع spans الداخلية في Dompdf 3 */
            unicode-bidi: normal;
            line-height: 1.35;
        }
        /* عزل اتجاه كل مقطع عربي/لاتيني — يُولَّد من ArabicPdfText::asDompdfHtml */
        .dompdf-ar-shape {
            vertical-align: baseline;
            direction: rtl;
            text-align: right;
            unicode-bidi: isolate;
        }
        .dompdf-ar-ascii {
            vertical-align: baseline;
            direction: ltr;
            unicode-bidi: isolate;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 8px;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 6px;
        }
        .header-left { display: table-cell; width: 28%; vertical-align: middle; text-align: right; }
        .header-mid { display: table-cell; width: 44%; vertical-align: middle; text-align: center; }
        .header-right { display: table-cell; width: 28%; vertical-align: middle; text-align: left; }
        .title { font-size: 18px; font-weight: 700; color: #0f766e; margin: 0; }
        .sub-ref { font-size: 10px; color: #64748b; margin-top: 2px; }
        .qr-wrap { text-align: left; }
        .qr-wrap img { width: 72px; height: 72px; }
        .qr-caption { font-size: 8px; color: #64748b; max-width: 90px; margin-top: 2px; line-height: 1.35; }
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 11px;
            color: #fff;
            margin: 4px 0 6px 0;
        }
        .badge-approved { background: #059669; }
        .badge-pending { background: #d97706; }
        .badge-progress { background: #2563eb; }
        .badge-done { background: #0d9488; }
        .badge-cancel { background: #64748b; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 10px; }
        table.meta td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
        }
        table.meta td.k { width: 22%; background: #f1f5f9; font-weight: 700; color: #334155; font-size: 10px; }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f766e;
            margin: 7px 0 3px 0;
            padding-bottom: 2px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 3px; font-size: 10px; }
        table.lines th {
            background: #0f766e;
            color: #fff;
            padding: 3px 2px;
            font-weight: 700;
            font-size: 8px;
            line-height: 1.25;
        }
        table.lines td { border: 1px solid #e2e8f0; padding: 4px; }
        table.lines tr:nth-child(even) td { background: #f8fafc; }
        .signatures { width: 100%; margin-top: 10px; border-top: 1px dashed #94a3b8; padding-top: 6px; }
        .sig-col { display: table-cell; width: 50%; vertical-align: top; padding: 0 6px; }
        .sig-row { display: table; width: 100%; }
        .sig-box { border: 1px solid #cbd5e1; min-height: 40px; margin-top: 4px; border-radius: 3px; background: #fafafa; }
        .issuer {
            margin-top: 8px;
            padding: 8px 10px;
            border: 1px solid #0f766e;
            border-radius: 3px;
            background: linear-gradient(180deg, #f0fdfa 0%, #ffffff 100%);
            direction: rtl;
            text-align: right;
            unicode-bidi: isolate;
        }
        .issuer h3 {
            margin: 0 0 6px 0;
            font-size: 11px;
            color: #0f766e;
            direction: rtl;
            text-align: right;
            font-weight: 700;
        }
        .issuer-grid { width: 100%; font-size: 9px; color: #334155; line-height: 1.45; direction: rtl; }
        .issuer-row { display: table; width: 100%; table-layout: fixed; }
        .issuer-cell { display: table-cell; width: 50%; padding: 3px 0 3px 8px; vertical-align: top; text-align: right; }
        .issuer-address-line { margin: 5px 0 0 0; font-size: 9px; color: #334155; line-height: 1.45; text-align: right; }
        .muted { color: #64748b; font-size: 9px; }
        .footer-note { margin-top: 6px; font-size: 8px; color: #94a3b8; text-align: center; line-height: 1.5; }
        .footer-note .verify-url { display: block; margin-top: 2px; word-break: break-all; }
        .ltr { direction: ltr; unicode-bidi: plaintext; text-align: left; font-family: "DejaVu Sans", sans-serif; }
        .center { text-align: center; }
    </style>
</head>
<body>
@php
    /** @var callable(string|null):\Illuminate\Support\HtmlString $shape */
    $s = $shape;
    $company = $order->company;
    $branch = $order->branch;
    $customer = $order->customer;
    $vehicle = $order->vehicle;
    $branchLabel = $branch?->name_ar ?: $branch?->name ?: '—';
    $custName = $customer?->name ?? '—';
    $plate = $vehicle?->plate_number ?? '—';
    $vehLabel = trim(implode(' ', array_filter([$vehicle?->make, $vehicle?->model, $vehicle?->year ? (string) $vehicle->year : ''])));
    $vehLabel = str_replace('كامير', 'كامري', $vehLabel);
    $badgeClass = match ($order->status) {
        \App\Enums\WorkOrderStatus::Approved => 'badge-approved',
        \App\Enums\WorkOrderStatus::PendingManagerApproval, \App\Enums\WorkOrderStatus::Draft => 'badge-pending',
        \App\Enums\WorkOrderStatus::InProgress, \App\Enums\WorkOrderStatus::OnHold => 'badge-progress',
        \App\Enums\WorkOrderStatus::Completed, \App\Enums\WorkOrderStatus::Delivered => 'badge-done',
        default => 'badge-cancel',
    };
@endphp

<div class="header">
    <div class="header-left">
        @if ($company?->logo_url)
            <img src="{{ $company->logo_url }}" alt="" style="max-height:40px;max-width:100px;"/>
        @else
            <div style="font-size:13px;font-weight:bold;color:#0f766e;">{{ $s($issuerDisplayAr) }}</div>
        @endif
    </div>
    <div class="header-mid">
        <p class="title">{{ $s('أمر عمل') }}</p>
        <div class="sub-ref">{{ $order->order_number }}</div>
        @if ($order->work_order_number)
            <div class="sub-ref">{{ $s('مرجع داخلي: '.$order->work_order_number) }}</div>
        @endif
    </div>
    <div class="header-right qr-wrap">
        <img src="{{ $qrDataUri }}" alt="QR"/>
        <div class="qr-caption">{{ $s('امسح للتحقق من صحة الأمر في النظام') }}</div>
    </div>
</div>

<div style="text-align:center;">
    <span class="badge {{ $badgeClass }}">{{ $s($statusLabel) }}</span>
</div>

<table class="meta">
    <tr>
        <td class="k">{{ $s('الجهة / العميل') }}</td>
        <td>{{ $s($custName) }}</td>
        <td class="k">{{ $s('يوم الإصدار') }}</td>
        <td>{{ $s($weekdayAr) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $s('التاريخ') }}</td>
        <td>{{ $s($createdFormatted) }}</td>
        <td class="k">{{ $s('مقدّم الخدمة') }}</td>
        <td>{{ $s($branchLabel) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $s('ملاحظة') }}</td>
        <td colspan="3">{{ $s('صلاحية التعميد ثلاثة أيام من تاريخ إنشاء الأمر ما لم يُتفق خلاف ذلك.') }}</td>
    </tr>
</table>

<div class="section-title">{{ $s('بيانات المركبة') }}</div>
<table class="meta">
    <tr>
        <td class="k">{{ $s('نوع المركبة') }}</td>
        <td>{{ $s($vehLabel !== '' ? $vehLabel : '—') }}</td>
        <td class="k">{{ $s('رقم اللوحة') }}</td>
        <td class="ltr">{{ $plate }}</td>
    </tr>
    <tr>
        <td class="k">{{ $s('العداد (كم)') }}</td>
        <td>{{ $order->odometer_reading ?? $order->mileage_in ?? '—' }}</td>
        <td class="k">{{ $s('السائق') }}</td>
        <td>{{ $s(trim(($order->driver_name ?? '').' '.($order->driver_phone ?? '')) ?: '—') }}</td>
    </tr>
</table>

@if ($order->customer_complaint)
    <div class="section-title">{{ $s('وصف الطلب') }}</div>
    <p style="margin:2px 0 4px 0;line-height:1.4;">{{ $s($order->customer_complaint) }}</p>
@endif

<div class="section-title">{{ $s('البنود والخدمات') }}</div>
<table class="lines">
    <thead>
    <tr>
        <th style="width:5%;">#</th>
        <th>{{ $s('المنتج / الخدمة') }}</th>
        <th style="width:14%;">{{ $s('الكمية') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($order->items as $i => $line)
        @php
            $lineLabel = trim((string) ($line->name ?? ''));
            if ($lineLabel === '') {
                $lineLabel = $line->product?->name_ar
                    ?: $line->product?->name
                    ?: $line->service?->name_ar
                    ?: $line->service?->name
                    ?: '—';
            }
        @endphp
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td style="text-align:right;">{{ $s($lineLabel) }}</td>
            <td class="center">{{ $line->quantity }}</td>
        </tr>
    @empty
        <tr><td colspan="3" style="text-align:center;color:#94a3b8;">{{ $s('لا توجد بنود') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="signatures">
    <div class="sig-row">
        <div class="sig-col">
            <strong>{{ $s('مندوب الجهة العميلة') }}</strong>
            <div class="muted">{{ $s('الاسم — التوقيع — التاريخ') }}</div>
            <div class="sig-box"></div>
        </div>
        <div class="sig-col">
            <strong>{{ $s('مندوب مقدّم الخدمة') }}</strong>
            <div class="muted">{{ $s('الاسم — التوقيع — التاريخ') }}</div>
            <div class="sig-box"></div>
        </div>
    </div>
</div>

<div class="issuer">
    <h3>{{ $s('بيانات الجهة المصدّرة للأمر (مقدّم الخدمة)') }}</h3>
    <div class="issuer-grid">
        <div class="issuer-row">
            <div class="issuer-cell">{{ $s('الاسم التجاري: '.$issuerDisplayAr) }}</div>
            <div class="issuer-cell">{{ $s('الفرع: '.$branchLabel) }}</div>
        </div>
        <div class="issuer-row">
            <div class="issuer-cell">{{ $s('السجل التجاري: '.($company?->cr_number ?: '—')) }}</div>
            <div class="issuer-cell">{{ $s('الرقم الضريبي: '.($company?->tax_number ?: '—')) }}</div>
        </div>
        <div class="issuer-row">
            <div class="issuer-cell">{{ $s('الهاتف: '.($branch?->phone ?: $company?->phone ?: '—')) }}</div>
            <div class="issuer-cell">{{ $s('البريد: '.($company?->email ?: '—')) }}</div>
        </div>
        <p class="issuer-address-line">{{ $s('العنوان: '.(trim(implode(' — ', array_filter([$branch?->address, $branch?->city, $company?->address, $company?->city]))) ?: '—')) }}</p>
    </div>
</div>

<p class="footer-note">{{ $s('رابط التحقق:') }}<br/><span class="ltr verify-url">{{ e($verifyUrl) }}</span></p>
</body>
</html>
