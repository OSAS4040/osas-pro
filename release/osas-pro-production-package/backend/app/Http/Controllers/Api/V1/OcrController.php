<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VehicleDocument;
use App\Services\IntelligentReading\InvoiceOcrEnricher;
use App\Services\IntelligentReading\KsaPlateNormalizer;
use App\Services\IntelligentReading\VehicleDocumentClassifier;
use App\Services\IntelligentReading\VehiclePlateResolver;
use App\Services\Ocr\TesseractOcrRunner;
use App\Support\Media\TenantUploadDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OcrController extends Controller
{
    public function __construct(
        private readonly TesseractOcrRunner $tesseract,
    ) {}

    /**
     * استخراج لوحة من صورة + اختياري: حلّ المركبة في النظام (preview فقط — لا حفظ).
     */
    public function scanPlate(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|string',
            'resolve_vehicle' => 'sometimes|boolean',
        ]);

        $base64 = $request->input('image');
        $imgData = base64_decode((string) $base64, true);

        if (! $imgData || strlen($imgData) < 100) {
            return response()->json([
                'plate' => '',
                'plate_normalized' => null,
                'success' => false,
                'error' => 'بيانات الصورة غير صالحة أو ناقصة.',
                'method' => 'invalid',
                'raw_ocr_text' => null,
                'vehicle' => null,
            ], 422);
        }

        $lang = (string) config('ocr.default_lang_plate', 'eng+ara');
        $ocr = $this->tesseract->runRaw($imgData, $lang, 7);
        $rawText = $ocr['code'] === TesseractOcrRunner::CODE_OK ? $ocr['text'] : null;

        $normalized = null;
        if ($rawText !== null) {
            $normalized = KsaPlateNormalizer::normalize($rawText);
        }

        if (! $normalized && $rawText) {
            $normalized = KsaPlateNormalizer::normalize(preg_replace('/\s+/u', ' ', $rawText) ?? '');
        }

        $plateDisplay = $normalized['display'] ?? '';

        if ($normalized) {
            $method = 'ocr';
        } elseif ($rawText) {
            $method = 'ocr_unparsed';
        } elseif ($ocr['code'] === TesseractOcrRunner::CODE_ENGINE_MISSING || $ocr['code'] === TesseractOcrRunner::CODE_DISABLED) {
            $method = 'unavailable';
        } else {
            $method = 'ocr_failed';
        }

        $payload = [
            'plate' => $plateDisplay,
            'plate_normalized' => $normalized,
            'success' => (bool) $normalized,
            'error' => $this->plateUserError($normalized, $rawText, $ocr['code']),
            'method' => $method,
            'raw_ocr_text' => $rawText ? Str::limit($rawText, 4000, '…') : null,
            'vehicle' => null,
        ];

        if ($request->boolean('resolve_vehicle') && $normalized) {
            $payload['vehicle'] = VehiclePlateResolver::resolve(
                (int) $request->user()->company_id,
                $normalized['display'],
            );
        }

        return response()->json($payload);
    }

    /**
     * فاتورة مشتريات: حقول + بنود + مطابقة منتجات (للمراجعة قبل الحفظ).
     */
    public function scanInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|string',
            'match_products' => 'sometimes|boolean',
        ]);

        $matchProducts = $request->boolean('match_products', true);
        $companyId = (int) $request->user()->company_id;
        $lang = (string) config('ocr.default_lang_document', 'ara+eng');

        $results = [];
        foreach ($request->input('images') as $idx => $base64) {
            $imgData = base64_decode((string) $base64, true);
            if (! $imgData) {
                $results[] = ['index' => $idx, 'error' => 'صورة غير صالحة', 'success' => false];

                continue;
            }

            $ocr = $this->tesseract->runRaw($imgData, $lang, 6);
            $text = $ocr['code'] === TesseractOcrRunner::CODE_OK ? ($ocr['text'] ?? '') : '';

            if ($text === '' || trim($text) === '') {
                $results[] = [
                    'index' => $idx,
                    'error' => $this->invoiceEmptyOcrMessage($ocr['code']),
                    'success' => false,
                    'line_items' => [],
                ];

                continue;
            }
            $parsed = $this->parseInvoiceText($text);
            $lineItems = InvoiceOcrEnricher::extractLineItems($text);
            if ($matchProducts && $lineItems !== []) {
                $lineItems = InvoiceOcrEnricher::matchProducts($companyId, $lineItems);
            }

            $results[] = array_merge([
                'index' => $idx,
                'success' => true,
                'raw_text' => Str::limit($text, 8000, '…'),
                'line_items' => $lineItems,
            ], $parsed);
        }

        return response()->json(['results' => $results]);
    }

    /**
     * مستند مركبة: تصنيف + استخراج + أرشفة الملف — يتطلب مراجعة حقول التواريخ قبل الاعتماد النهائي.
     */
    public function scanVehicleDocument(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'confirm' => 'sometimes|boolean',
            'document_type' => 'sometimes|in:insurance,registration,technical,license,other',
            'title' => 'sometimes|string|max:160',
            'expiry_date' => 'nullable|date',
        ]);

        $vehicleId = (int) $data['vehicle_id'];
        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $text = '';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $bin = (string) file_get_contents($file->getRealPath() ?: '');
            if ($bin !== '') {
                $lang = (string) config('ocr.default_lang_document', 'ara+eng');
                $ocr = $this->tesseract->runRaw($bin, $lang, 6);
                if ($ocr['code'] === TesseractOcrRunner::CODE_OK && ($ocr['text'] ?? '') !== '') {
                    $text = strtoupper($ocr['text']);
                }
            }
        }

        $classified = VehicleDocumentClassifier::classify($text !== '' ? $text : strtoupper($file->getClientOriginalName()));
        if ($request->filled('document_type')) {
            $classified['type'] = $request->string('document_type')->toString();
        }
        if ($request->filled('title')) {
            $classified['title'] = $request->string('title')->toString();
        }
        if ($request->filled('expiry_date')) {
            $classified['expiry_date'] = $request->date('expiry_date')->format('Y-m-d');
        }

        $docType = $classified['type'];
        if (! in_array($docType, ['insurance', 'registration', 'technical', 'license', 'other'], true)) {
            $docType = 'other';
        }

        if (! $request->boolean('confirm')) {
            return response()->json([
                'preview' => true,
                'classification' => $classified,
                'raw_ocr_sample' => $text !== '' ? Str::limit($text, 1200, '…') : null,
                'message' => 'معاينة — راجع التصنيف والتواريخ ثم أعد الإرسال مع confirm=true لأرشفة الملف.',
            ]);
        }

        $path = $file->store("companies/{$user->company_id}/vehicle-docs/{$vehicleId}", TenantUploadDisk::name());

        $noteTail = $text !== '' ? Str::limit($text, 500) : ($ext === 'pdf' ? 'PDF — راجع يدوياً' : '');
        $doc = VehicleDocument::create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicleId,
            'uploaded_by' => $user->id,
            'document_type' => $docType,
            'title' => $classified['title'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'expiry_date' => $classified['expiry_date'],
            'alert_days_before' => 30,
            'notes' => trim('مرجع: '.($classified['reference'] ?? '—').' | جودة OCR: '.$classified['confidence'].PHP_EOL.$noteTail),
        ]);

        return response()->json([
            'preview' => false,
            'data' => $doc,
            'classification' => $classified,
            'expiry_alert' => $classified['expiry_date'] !== null,
        ], 201);
    }

    private function plateUserError(?array $normalized, ?string $rawText, string $ocrCode): ?string
    {
        if ($normalized) {
            return null;
        }
        if ($rawText) {
            return 'لم يُستخرج نمط لوحة سعودي واضح — أدخل الرقم يدوياً.';
        }
        if ($ocrCode === TesseractOcrRunner::CODE_ENGINE_MISSING || $ocrCode === TesseractOcrRunner::CODE_DISABLED) {
            return 'محرك OCR غير متاح على الخادم (Tesseract).';
        }
        if ($ocrCode === TesseractOcrRunner::CODE_IMAGE_TOO_LARGE) {
            return 'حجم الصورة كبير جداً. صغّر الصورة أو قلّل الدقة وحاول مرة أخرى، أو أدخل اللوحة يدوياً.';
        }

        return 'تعذّر قراءة اللوحة تلقائياً — أدخل الرقم يدوياً.';
    }

    private function invoiceEmptyOcrMessage(string $ocrCode): string
    {
        if ($ocrCode === TesseractOcrRunner::CODE_ENGINE_MISSING || $ocrCode === TesseractOcrRunner::CODE_DISABLED) {
            return 'محرك OCR غير متاح على الخادم (Tesseract). راجع تثبيت الحزم في حاوية التطبيق أو اتصل بالمسؤول.';
        }
        if ($ocrCode === TesseractOcrRunner::CODE_IMAGE_TOO_LARGE) {
            return 'حجم الصورة يتجاوز الحد المسموح. صغّر الصورة أو قلّل الدقة ثم أعد المحاولة، أو أدخل البيانات يدوياً.';
        }

        return 'لم يُستخرج نص من الصورة. جرّب صورة أوضح أو تكبيراً أعلى، أو أدخل البيانات يدوياً.';
    }

    /**
     * @return array{supplier_name: ?string, invoice_number: ?string, invoice_date: ?string, total: ?float, vat_amount: ?float, vat_number: ?string, subtotal: ?float}
     */
    private function parseInvoiceText(string $text): array
    {
        $t = preg_replace("/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u", '', $text) ?? $text;
        $upper = mb_strtoupper($t);

        $result = [
            'supplier_name' => null,
            'invoice_number' => null,
            'invoice_date' => null,
            'total' => null,
            'vat_amount' => null,
            'vat_number' => null,
            'subtotal' => null,
        ];

        if (preg_match('/(?:FROM|SUPPLIER|VENDOR|البائع|المورد|الشركة|بائع|مورد)[:\s]*(.{3,120})/u', $t, $m)) {
            $c = $this->cleanSupplierNameCandidate($m[1]);
            if ($c !== null) {
                $result['supplier_name'] = $c;
            }
        }
        if (! $result['supplier_name']) {
            $result['supplier_name'] = $this->guessSupplierNameFromFirstLines($t);
        }

        if (preg_match('/(?:invoice\s*(?:no|number|#)|فاتورة\s*رقم|رقم\s*الفاتورة|INV[\s.#\-]*|Simplified\s*Tax\s*Invoice\s*No)[:\s#]*([A-Z0-9\-\/]+)/iu', $t, $m)) {
            $result['invoice_number'] = trim($m[1], " \t\n\r\0\x0B#:-");
        }

        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/', $t, $m)) {
            $result['invoice_date'] = $m[3].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        if (! $result['invoice_date'] && preg_match('/(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $t, $m)) {
            $result['invoice_date'] = $m[1].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[3], 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/(?:SUBTOTAL|المجموع\s*الفرعي|الإجمالي\s*قبل|قبل\s*الضريبة)[:\s]*([0-9٠-٩,٬]+\.?\d{0,2})/iu', $t, $m)) {
            $parsed = $this->parseMoneyToken($m[1]);
            if ($parsed !== null) {
                $result['subtotal'] = $parsed;
            }
        }

        if ($result['total'] === null) {
            if (preg_match(
                '/(?:^|\R)\s*(?:GRAND\s*)?TOTAL|المجموع\s*الكلي|الإجمالي\s*النهائي|المبلغ\s*الإجمالي|الصافي\s*الإجمالي|Invoice\s*Total|Amount\s*Due|NET\s*AMOUNT|Balance\s*Due|الصافي|المدفوع|المجموع\s*KSA|المجموع|الإجمالي)\s*[:\s]*([0-9٠-٩,٬]+\.?\d{0,2})\s*(?:SAR|ريال|ر\.?\s*س|﷼)?/iu',
                $t,
                $m
            )) {
                $parsed = $this->parseMoneyToken($m[1]);
                if ($parsed !== null && $parsed > 0) {
                    $result['total'] = $parsed;
                }
            }
        }

        if ($result['total'] === null && preg_match('/(?:^|\s)(?:TOTAL|المجموع|الإجمالي|NET\s*AMOUNT|AMOUNT\s*DUE|الصافي|المدفوع)[:\s]*([0-9٠-٩,٬]+\.?\d{0,2})/im', $upper, $m)) {
            $parsed = $this->parseMoneyToken($m[1]);
            if ($parsed !== null) {
                $result['total'] = $parsed;
            }
        }

        if ($result['total'] === null) {
            $guess = $this->extractLargestPlausibleSarAmount($t);
            if ($guess !== null) {
                $result['total'] = $guess;
            }
        }

        if (preg_match('/(?:VAT|ضريبة\s*القيمة|ضريبة\s*مضافة|ضريبة|ض\.ق\.م|ض\.\s*ق\.?\s*م)[:\s]*([0-9٠-٩,٬]+\.?\d{0,2})/iu', $t, $m)) {
            $parsed = $this->parseMoneyToken($m[1]);
            if ($parsed !== null) {
                $result['vat_amount'] = $parsed;
            }
        }

        if ($result['vat_amount'] === null && $result['subtotal'] !== null && $result['total'] !== null && $result['total'] > $result['subtotal']) {
            $result['vat_amount'] = round($result['total'] - $result['subtotal'], 2);
        }

        if (preg_match('/(?:VAT\s*(?:No|Number|Reg|ID)|الرقم\s*الضريبي|TRN|TIN)[:\s#]*(\d{10,15})/iu', $t, $m)) {
            $result['vat_number'] = $m[1];
        }

        return $result;
    }

    private function cleanSupplierNameCandidate(string $raw): ?string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if (mb_strlen($s) < 3) {
            return null;
        }
        if (preg_match('/^[\p{P}\p{S}\s]+$/u', $s)) {
            return null;
        }

        return Str::limit($s, 200, '');
    }

    private function guessSupplierNameFromFirstLines(string $text): ?string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) < 6) {
                continue;
            }
            if (preg_match('/\b(QR|ZATCA|invoice|فاتورة|tax|ضريبة|date|تاريخ|total|المجموع)\b/iu', $line)) {
                continue;
            }
            if (preg_match('/^[\d\s.\-\/:]+$/', $line)) {
                continue;
            }
            if (preg_match('/^[\p{P}\p{S}\s£€$]+$/u', $line)) {
                continue;
            }

            return Str::limit($line, 200, '');
        }

        return null;
    }

    private function parseMoneyToken(string $raw): ?float
    {
        $arabicIndic = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.'];
        $num = strtr(str_replace([',', '٬', ' '], '', $raw), $arabicIndic);
        if ($num === '' || ! is_numeric($num)) {
            return null;
        }

        return (float) $num;
    }

    private function extractLargestPlausibleSarAmount(string $text): ?float
    {
        $best = null;
        if (preg_match_all('/([\d٠-٩]+(?:[٫.,][\d٠-٩]+)?)\s*(?:SAR|ريال|ر\.?\s*س|﷼)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $v = $this->parseMoneyToken($m[1]);
                if ($v !== null && $v > 0 && $v < 50000000 && ($best === null || $v > $best)) {
                    $best = $v;
                }
            }
        }

        return $best;
    }
}
