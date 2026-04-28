<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Company;
use App\Models\WalletTopUpRequest;
use App\Support\ArabicPdfText;
use App\Support\DompdfArabicFont;
use App\Support\Wallet\WalletTreasuryAccountsResolver;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use DomainException;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

final class WalletTransferInstructionsPdfService
{
    public function streamPdf(WalletTopUpRequest $request): Response
    {
        $request->loadMissing(['customer:id,name,phone', 'requester:id,name']);
        $company = Company::query()->findOrFail((int) $request->company_id);

        $accounts = WalletTreasuryAccountsResolver::forCompany($company);
        if ($accounts === []) {
            throw new DomainException(
                'لم يُضبط أي حساب بنكي لاستلام التحويلات. أضف «حسابات شحن المحافظ» من الإعدادات > إعدادات الفاتورة، أو عبّئ اسم البنك والآيبان في معلومات الشركة.'
            );
        }

        $customerName = (string) ($request->customer?->name ?? '—');
        $reference = (string) ($request->reference_number ?? $request->uuid);
        $targetLabel = $request->target === 'fleet' ? 'محفظة أسطول' : 'محفظة فردية';

        /** @var PdfWrapper $pdf */
        $pdf = app('dompdf.wrapper');
        $arabicFontOk = DompdfArabicFont::registerNotoNaskhArabic($pdf, 'WalletTransferPdf');

        $tz = (string) ($company->timezone ?: 'Asia/Riyadh');
        $issuedAt = now()->timezone($tz)->format('Y-m-d H:i');

        $pdf->loadView('pdf.wallet-transfer-instructions-ar', [
            'company' => $company,
            'request' => $request,
            'accounts' => $accounts,
            'customerName' => $customerName,
            'customerPhone' => (string) ($request->customer?->phone ?? ''),
            'requesterName' => (string) ($request->requester?->name ?? ''),
            'reference' => $reference,
            'targetLabel' => $targetLabel,
            'amount' => (string) $request->amount,
            'currency' => (string) $request->currency,
            'issuedAt' => $issuedAt,
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

        $filename = 'wallet-transfer-'.$request->uuid.'.pdf';

        return $pdf->stream($filename);
    }
}
