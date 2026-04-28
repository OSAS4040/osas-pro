<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تعليمات تحويل — شحن محفظة</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 8px 0; color: #0f766e; }
        .muted { color: #64748b; font-size: 10px; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; background: #f8fafc; }
        .label { font-weight: bold; color: #334155; }
        table.accounts { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.accounts th, table.accounts td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: right; vertical-align: top; }
        table.accounts th { background: #ecfeff; color: #0f766e; font-size: 10px; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 10px; word-break: break-all; }
        .warn { background: #fffbeb; border: 1px solid #fcd34d; padding: 8px; border-radius: 4px; font-size: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>تعليمات التحويل البنكي — شحن محفظة عميل</h1>
    <p class="muted">صادر عن: {!! $shape($company->name_ar ?: $company->name) !!} — {{ $issuedAt }}</p>

    <div class="box">
        <p><span class="label">اسم العميل الطالب (صاحب المحفظة):</span> {!! $shape($customerName) !!}</p>
        @if($customerPhone !== '')
            <p><span class="label">جوال العميل:</span> <span class="mono">{{ $customerPhone }}</span></p>
        @endif
        @if($requesterName !== '')
            <p><span class="label">مقدّم الطلب من المنشأة:</span> {!! $shape($requesterName) !!}</p>
        @endif
        <p><span class="label">المبلغ المطلوب شحنه:</span> {{ $amount }} {{ $currency }}</p>
        <p><span class="label">نوع المحفظة:</span> {{ $targetLabel }}</p>
        <p><span class="label">رقم المرجع (أدخله في تفاصيل التحويل):</span> <span class="mono">{{ $reference }}</span></p>
        <p><span class="label">رقم الطلب الداخلي:</span> <span class="mono">#{{ $request->id }} — {{ $request->uuid }}</span></p>
    </div>

    <p class="label" style="margin-top: 12px;">حسابات الاستلام المعتمدة لدى المنشأة</p>
    <table class="accounts">
        <thead>
            <tr>
                <th>البنك</th>
                <th>رقم الحساب</th>
                <th>الآيبان IBAN</th>
                <th>ملاحظة مستفيد (اختياري)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($accounts as $acc)
                <tr>
                    <td>{!! $shape($acc['bank_name']) !!}</td>
                    <td class="mono">{{ $acc['account_number'] ?? '—' }}</td>
                    <td class="mono">{{ $acc['iban'] ?? '—' }}</td>
                    <td>{!! isset($acc['beneficiary_label']) && $acc['beneficiary_label'] ? $shape($acc['beneficiary_label']) : '—' !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="warn">
        يرجى التأكد من إدخال <strong>رقم المرجع</strong> أعلاه في حقل «الملاحظات / الغرض» أو ما يعادله عند التحويل من البنك، ثم رفع إيصال التحويل في طلب الشحن داخل النظام. لا يُضاف الرصيد إلا بعد مطابقة المبلغ والمراجعة وفق سياسة المنشأة.
    </div>
</body>
</html>
