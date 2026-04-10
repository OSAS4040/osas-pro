<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 18px 20px; }
        * { box-sizing: border-box; }
        body {
            font-family: @if(!empty($useArabicPdfFont)) "Noto Naskh Arabic", "noto naskh arabic", @endif "DejaVu Sans", sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            direction: rtl;
            text-align: right;
            unicode-bidi: normal;
            line-height: 1.45;
        }
        .dompdf-ar-shape, .dompdf-ar-ascii { vertical-align: baseline; }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            border-bottom: 2px solid #4338ca;
            padding-bottom: 12px;
        }
        .header-left { display: table-cell; width: 28%; vertical-align: middle; text-align: right; }
        .header-mid { display: table-cell; width: 44%; vertical-align: middle; text-align: center; }
        .header-right { display: table-cell; width: 28%; vertical-align: middle; text-align: left; }
        .title { font-size: 24px; font-weight: 700; color: #4338ca; margin: 0; }
        .sub-ref { font-size: 12px; color: #64748b; margin-top: 4px; }
        .qr-wrap { text-align: left; }
        .qr-wrap img { width: 92px; height: 92px; }
        .qr-caption { font-size: 9px; color: #64748b; max-width: 110px; margin-top: 4px; line-height: 1.4; }
        .badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            color: #fff;
            margin: 10px 0 14px 0;
        }
        .badge-paid { background: #059669; }
        .badge-partial { background: #d4990b; }
        .badge-pending { background: #d97706; }
        .badge-draft { background: #64748b; }
        .badge-cancel { background: #64748b; }
        .badge-refund { background: #7c3aed; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11px; }
        table.meta td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
        }
        table.meta td.k { width: 22%; background: #f1f5f9; font-weight: 700; color: #334155; font-size: 11px; }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #4338ca;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 10px; }
        table.lines th {
            background: #4338ca;
            color: #fff;
            padding: 7px 5px;
            font-weight: 700;
        }
        table.lines td { border: 1px solid #e2e8f0; padding: 6px 4px; }
        table.lines tr:nth-child(even) td { background: #f8fafc; }
        .totals-table { width: 100%; max-width: 340px; margin-top: 14px; margin-left: 0; border-collapse: collapse; font-size: 12px; }
        .totals-table th { text-align: right; padding: 6px 10px; background: #eef2ff; border: 1px solid #c7d2fe; }
        .totals-table td { text-align: left; direction: ltr; unicode-bidi: plaintext; padding: 6px 10px; border: 1px solid #e2e8f0; font-family: "DejaVu Sans", sans-serif; }
        .totals-table tr.grand th, .totals-table tr.grand td { font-weight: 700; background: #e0e7ff; }
        .issuer {
            margin-top: 22px;
            padding: 12px 14px;
            border: 1px solid #4338ca;
            border-radius: 4px;
            background: linear-gradient(180deg, #f5f3ff 0%, #ffffff 100%);
        }
        .issuer h3 { margin: 0 0 8px 0; font-size: 12px; color: #4338ca; }
        .issuer-grid { width: 100%; font-size: 10px; color: #334155; line-height: 1.65; }
        .muted { color: #64748b; font-size: 10px; }
        .footer-note { margin-top: 14px; font-size: 9px; color: #94a3b8; text-align: center; }
        .ltr { direction: ltr; unicode-bidi: plaintext; text-align: left; font-family: "DejaVu Sans", sans-serif; }
        .center { text-align: center; }
        .notes { margin-top: 10px; font-size: 11px; line-height: 1.5; color: #334155; }
    </style>
</head>
<body>
@php
    /** @var callable(string|null):\Illuminate\Support\HtmlString $shape */
    $s = $shape;
    $branch = $invoice->branch;
    $customer = $invoice->customer;
    $vehicle = $invoice->vehicle;
    $branchLabel = $branch?->name_ar ?: $branch?->name ?: '—';
    $custName = $customer?->name ?? '—';
    $plate = $vehicle?->plate_number ?? '—';
    $vehLabel = trim(implode(' ', array_filter([$vehicle?->make, $vehicle?->model, $vehicle?->year ? (string) $vehicle->year : ''])));
    $due = $invoice->due_at ? \Illuminate\Support\Carbon::parse($invoice->due_at)->translatedFormat('j F Y') : '—';
    $currency = $invoice->currency ?: 'SAR';
    $currLabel = $currency === 'SAR' ? 'ر.س' : $currency;
    $badgeClass = match ($invoice->status) {
        \App\Enums\InvoiceStatus::Paid => 'badge-paid',
        \App\Enums\InvoiceStatus::PartialPaid => 'badge-partial',
        \App\Enums\InvoiceStatus::Pending => 'badge-pending',
        \App\Enums\InvoiceStatus::Draft => 'badge-draft',
        \App\Enums\InvoiceStatus::Refunded => 'badge-refund',
        default => 'badge-cancel',
    };
    $typeAr = match ($invoice->type) {
        'refund' => 'مرتجع',
        'proforma' => 'عرض سعر / مبدئية',
        default => 'فاتورة بيع',
    };
@endphp

<div class="header">
    <div class="header-left">
        @if ($company?->logo_url)
            <img src="{{ $company->logo_url }}" alt="" style="max-height:48px;max-width:120px;"/>
        @else
            <div style="font-size:15px;font-weight:bold;color:#4338ca;">{{ $s($issuerDisplayAr) }}</div>
        @endif
    </div>
    <div class="header-mid">
        <p class="title">{{ $s('فاتورة') }}</p>
        <div class="sub-ref ltr" dir="ltr">{{ $invoice->invoice_number }}</div>
        <div class="sub-ref">{{ $s($typeAr) }}</div>
    </div>
    <div class="header-right qr-wrap">
        <img src="{{ $qrDataUri }}" alt="QR"/>
        <div class="qr-caption">{{ $s('رمز الاستجابة السريعة للفاتورة الإلكترونية') }}</div>
    </div>
</div>

<div style="text-align:center;">
    <span class="badge {{ $badgeClass }}">{{ $s($statusLabel) }}</span>
</div>

<table class="meta">
    <tr>
        <td class="k">{{ $s('العميل') }}</td>
        <td>{{ $s($custName) }}</td>
        <td class="k">{{ $s('يوم الإصدار') }}</td>
        <td>{{ $s($weekdayAr) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $s('تاريخ الإصدار') }}</td>
        <td>{{ $s($issuedFormatted) }}</td>
        <td class="k">{{ $s('تاريخ الاستحقاق') }}</td>
        <td>{{ $s($due) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $s('الفرع') }}</td>
        <td>{{ $s($branchLabel) }}</td>
        <td class="k">{{ $s('رقم اللوحة') }}</td>
        <td class="ltr">{{ $plate }}</td>
    </tr>
    @if ($vehLabel !== '')
    <tr>
        <td class="k">{{ $s('المركبة') }}</td>
        <td colspan="3">{{ $s($vehLabel) }}</td>
    </tr>
    @endif
</table>

<div class="section-title">{{ $s('البنود') }}</div>
<table class="lines">
    <thead>
    <tr>
        <th style="width:5%;">#</th>
        <th>{{ $s('البند') }}</th>
        <th style="width:9%;">{{ $s('الكمية') }}</th>
        <th style="width:11%;">{{ $s('السعر') }}</th>
        <th style="width:8%;">{{ $s('ض.%') }}</th>
        <th style="width:11%;">{{ $s('الضريبة') }}</th>
        <th style="width:12%;">{{ $s('الإجمالي') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($invoice->items as $i => $line)
        @php
            $lineAmt = $line->line_total ?? $line->total ?? '0';
        @endphp
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $s($line->name) }}</td>
            <td class="center">{{ $line->quantity }}</td>
            <td class="ltr">{{ number_format((float) $line->unit_price, 2) }}</td>
            <td class="center">{{ number_format((float) $line->tax_rate, 2) }}%</td>
            <td class="ltr">{{ number_format((float) $line->tax_amount, 2) }}</td>
            <td class="ltr">{{ number_format((float) $lineAmt, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" style="text-align:center;color:#94a3b8;">{{ $s('لا توجد بنود') }}</td></tr>
    @endforelse
    </tbody>
</table>

<table class="totals-table" align="right">
    <tr>
        <th>{{ $s('المجموع الفرعي') }} <span class="muted" dir="ltr">Subtotal</span></th>
        <td>{{ number_format((float) $invoice->subtotal, 2) }} {{ $currLabel }}</td>
    </tr>
    @if ((float) $invoice->discount_amount > 0)
    <tr>
        <th>{{ $s('الخصم') }} <span class="muted" dir="ltr">Discount</span></th>
        <td>-{{ number_format((float) $invoice->discount_amount, 2) }} {{ $currLabel }}</td>
    </tr>
    @endif
    <tr>
        <th>{{ $s('ضريبة القيمة المضافة') }} <span class="muted" dir="ltr">VAT</span></th>
        <td>{{ number_format((float) $invoice->tax_amount, 2) }} {{ $currLabel }}</td>
    </tr>
    <tr class="grand">
        <th>{{ $s('الإجمالي') }} <span class="muted" dir="ltr">Total</span></th>
        <td>{{ number_format((float) $invoice->total, 2) }} {{ $currLabel }}</td>
    </tr>
    <tr>
        <th>{{ $s('المدفوع') }} <span class="muted" dir="ltr">Paid</span></th>
        <td>{{ number_format((float) $invoice->paid_amount, 2) }} {{ $currLabel }}</td>
    </tr>
    <tr>
        <th>{{ $s('المتبقي') }} <span class="muted" dir="ltr">Due</span></th>
        <td>{{ number_format((float) $invoice->due_amount, 2) }} {{ $currLabel }}</td>
    </tr>
</table>

@if ($invoice->notes)
    <div class="section-title">{{ $s('ملاحظات') }}</div>
    <p class="notes">{{ $s($invoice->notes) }}</p>
@endif

<div class="issuer">
    <h3>{{ $s('بيانات المُصدِر') }}</h3>
    <table class="issuer-grid" style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:50%;padding:2px 0;">{{ $s('الاسم التجاري: '.$issuerDisplayAr) }}</td>
            <td style="width:50%;padding:2px 0;">{{ $s('الفرع: '.$branchLabel) }}</td>
        </tr>
        <tr>
            <td style="padding:2px 0;">{{ $s('السجل التجاري: '.($company?->cr_number ?: '—')) }}</td>
            <td style="padding:2px 0;">{{ $s('الرقم الضريبي: '.($company?->tax_number ?: '—')) }}</td>
        </tr>
        <tr>
            <td style="padding:2px 0;">{{ $s('الهاتف: '.($branch?->phone ?: $company?->phone ?: '—')) }}</td>
            <td style="padding:2px 0;">{{ $s('البريد: '.($company?->email ?: '—')) }}</td>
        </tr>
    </table>
</div>

<p class="footer-note">{{ $s('صُدرت عبر نظام أسس برو · Osas Pro') }}</p>
</body>
</html>
