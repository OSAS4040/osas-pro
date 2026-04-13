<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\ArabicPdfText;
use App\Support\BrandDisplayNames;
use App\Support\DompdfArabicFont;
use App\Support\ZatcaInvoiceTlv;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

final class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'items.product', 'items.service',
            'customer', 'vehicle', 'branch', 'company', 'createdBy',
        ]);

        $company = $invoice->company;
        $issuerAr = BrandDisplayNames::companyTradeNameAr($company);
        $qrDataUri = $this->invoiceQrDataUri($invoice, $company, $issuerAr);

        $issued = $invoice->issued_at ?? $invoice->created_at;
        $issuedCarbon = $issued ? Carbon::parse($issued)->timezone(config('app.timezone', 'UTC')) : Carbon::now();
        $issuedFormatted = $issuedCarbon->translatedFormat('j F Y');
        $weekdayAr = $this->weekdayAr($issuedCarbon);

        /** @var PdfWrapper $pdf */
        $pdf = app('dompdf.wrapper');

        $arabicFontOk = DompdfArabicFont::registerNotoNaskhArabic($pdf, 'InvoicePdf');

        $statusAr = $this->statusLabelAr($invoice->status);

        $pdf->loadView('pdf.invoice-ar', [
            'invoice' => $invoice,
            'company' => $company,
            'qrDataUri' => $qrDataUri,
            'issuerDisplayAr' => $issuerAr,
            'statusLabel' => $statusAr,
            'issuedFormatted' => $issuedFormatted,
            'weekdayAr' => $weekdayAr,
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

    private function invoiceQrDataUri(Invoice $invoice, ?\App\Models\Company $company, string $issuerAr): string
    {
        $stored = $invoice->getAttribute('zatca_qr_code');
        if (is_string($stored) && $stored !== '') {
            $t = trim($stored);
            if (str_starts_with($t, 'data:')) {
                return $t;
            }
            // Assume raw base64 TLV payload
            return $this->tlvBase64ToQrDataUri($t);
        }

        $tlv = ZatcaInvoiceTlv::base64Payload($invoice, $company, $issuerAr);

        return $this->tlvBase64ToQrDataUri($tlv);
    }

    private function tlvBase64ToQrDataUri(string $tlvBase64): string
    {
        $qr = QrCode::create($tlvBase64)
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

    private function statusLabelAr(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Draft => 'مسودة',
            InvoiceStatus::Pending => 'قيد الانتظار',
            InvoiceStatus::PartialPaid => 'مدفوعة جزئياً',
            InvoiceStatus::Paid => 'مدفوعة',
            InvoiceStatus::Cancelled => 'ملغاة',
            InvoiceStatus::Refunded => 'مستردة',
        };
    }
}
