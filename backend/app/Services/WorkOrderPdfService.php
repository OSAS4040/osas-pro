<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Support\ArabicPdfText;
use App\Support\BrandDisplayNames;
use App\Support\DompdfArabicFont;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

final class WorkOrderPdfService
{
    /**
     * رابط الواجهة العامة لبطاقة التحقق (QR / مشاركة).
     *
     * يُفضّل ضبط APP_PUBLIC_URL في الإنتاج (النطاق الذي يراه العميل) لأن APP_URL قد يشير
     * إلى اسم خدمة داخلي (مثل nginx) داخل Docker.
     */
    public function publicCardUrl(WorkOrder $order): string
    {
        return rtrim($this->publicBaseUrl(), '/').'/public/work-orders/'.$order->uuid;
    }

    private function publicBaseUrl(): string
    {
        $explicit = trim((string) config('app_urls.public_base', ''));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        return rtrim((string) config('app.url', 'http://localhost'), '/');
    }

    public function render(WorkOrder $order): string
    {
        $order->loadMissing([
            'customer', 'vehicle', 'company', 'branch',
            'items.product', 'items.service',
            'assignedTechnician', 'createdBy',
        ]);

        $verifyUrl = $this->publicCardUrl($order);
        $qrDataUri = $this->buildQrDataUri($verifyUrl);

        $created = $order->created_at ? Carbon::parse($order->created_at)->timezone(config('app.timezone', 'UTC')) : Carbon::now();

        /** @var PdfWrapper $pdf */
        $pdf = app('dompdf.wrapper');

        $arabicFontOk = DompdfArabicFont::registerNotoNaskhArabic($pdf, 'WorkOrderPdf');

        $issuerAr = BrandDisplayNames::companyTradeNameAr($order->company);
        $statusAr = $this->statusLabelAr($order->status);
        $weekdayAr = $this->weekdayAr($created);
        $createdFormatted = $created->copy()->locale('ar')->translatedFormat('l j F Y');

        $linesGrandTotal = 0.0;
        foreach ($order->items as $line) {
            $linesGrandTotal += (float) $line->total;
        }

        $pdf->loadView('pdf.work-order-ar', [
            'order' => $order,
            'qrDataUri' => $qrDataUri,
            'verifyUrl' => $verifyUrl,
            'issuerDisplayAr' => $issuerAr,
            'statusLabel' => $statusAr,
            'createdFormatted' => $createdFormatted,
            'weekdayAr' => $weekdayAr,
            'linesGrandTotal' => $linesGrandTotal,
            'useArabicPdfFont' => $arabicFontOk,
            'shape' => static fn (?string $text): HtmlString => ArabicPdfText::asDompdfHtml($text),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('chroot', realpath(base_path()) ?: base_path());
        $pdf->setOption('isFontSubsettingEnabled', false);

        if (! $arabicFontOk) {
            $pdf->setOption('defaultFont', 'DejaVu Sans');
        }

        return $pdf->output();
    }

    private function buildQrDataUri(string $url): string
    {
        $qr = QrCode::create($url)
            ->setSize(132)
            ->setMargin(6)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium);

        $svg = (new SvgWriter())->write($qr)->getString();

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function weekdayAr(Carbon $d): string
    {
        $map = [
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
        ];
        $en = $d->locale('en')->dayName;

        return $map[$en] ?? $en;
    }

    private function statusLabelAr(WorkOrderStatus $status): string
    {
        return match ($status) {
            WorkOrderStatus::Draft => 'مسودة',
            WorkOrderStatus::PendingManagerApproval => 'بانتظار اعتماد المدير',
            WorkOrderStatus::Approved => 'معتمد',
            WorkOrderStatus::CancellationRequested => 'طلب إلغاء',
            WorkOrderStatus::InProgress => 'قيد التنفيذ',
            WorkOrderStatus::OnHold => 'معلّق',
            WorkOrderStatus::Completed => 'مكتمل',
            WorkOrderStatus::Delivered => 'مُسلَّم',
            WorkOrderStatus::Cancelled => 'ملغى',
        };
    }
}
