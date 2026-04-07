<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VehicleDocument;
use App\Services\IntelligentReading\InvoiceOcrEnricher;
use App\Services\IntelligentReading\KsaPlateNormalizer;
use App\Services\IntelligentReading\VehicleDocumentClassifier;
use App\Services\IntelligentReading\VehiclePlateResolver;
use App\Support\Media\TenantUploadDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OcrController extends Controller
{
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

        $rawText = $this->runTesseractRaw($imgData, 'eng+ara', 7);
        $normalized = null;
        if ($rawText !== null) {
            $normalized = KsaPlateNormalizer::normalize($rawText);
        }

        $plateDisplay = $normalized['display'] ?? '';
        $method = $normalized ? 'ocr' : ($rawText ? 'ocr_unparsed' : 'unavailable');

        if (! $normalized && $rawText) {
            $normalized = KsaPlateNormalizer::normalize(preg_replace('/\s+/u', ' ', $rawText) ?? '');
            $plateDisplay = $normalized['display'] ?? '';
            $method = $normalized ? 'ocr' : $method;
        }

        $payload = [
            'plate' => $plateDisplay,
            'plate_normalized' => $normalized,
            'success' => (bool) $normalized,
            'error' => $normalized ? null : ($rawText ? 'لم يُستخرج نمط لوحة سعودي واضح — أدخل الرقم يدوياً.' : 'محرك OCR غير متاح على الخادم (Tesseract).'),
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

        $results = [];
        foreach ($request->input('images') as $idx => $base64) {
            $imgData = base64_decode((string) $base64, true);
            if (! $imgData) {
                $results[] = ['index' => $idx, 'error' => 'صورة غير صالحة', 'success' => false];
                continue;
            }

            // PSM 6 = كتلة نصية موحّدة — مناسب للفواتير وإيصالات POS (ليس PSM 7 سطراً واحداً)
            $text = $this->runTesseractRaw($imgData, 'ara+eng', 6) ?? '';
            if ($text === '' || trim($text) === '') {
                $results[] = [
                    'index' => $idx,
                    'error' => 'لم يُستخرج نص من الصورة. جرّب صورة أوضح، تكبير أعلى، أو PDF. تحقق من تثبيت Tesseract على الخادم.',
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
                \Illuminate\Validation\Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
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
                $text = strtoupper($this->runTesseractRaw($bin, 'ara+eng', 6) ?? '');
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

    /**
     * @param  int  $psm  Tesseract page segmentation (7 = سطر واحد للوحات، 6 = كتلة للفواتير)
     */
    private function runTesseractRaw(string $imgData, string $lang = 'eng+ara', int $psm = 7): ?string
    {
        $tmpIn = sys_get_temp_dir().'/'.Str::random(12).'.jpg';
        $tmpOut = sys_get_temp_dir().'/'.Str::random(12);
        file_put_contents($tmpIn, $imgData);

        $bin = $this->findTesseractBinary();
        if (! $bin) {
            @unlink($tmpIn);

            return null;
        }

        $psm = max(0, min(13, $psm));
        $cmd = sprintf(
            '%s %s %s -l %s --oem 3 --psm %d 2>/dev/null',
            escapeshellcmd($bin),
            escapeshellarg($tmpIn),
            escapeshellarg($tmpOut),
            escapeshellarg($lang),
            $psm
        );
        shell_exec($cmd);
        @unlink($tmpIn);

        $outFile = $tmpOut.'.txt';
        if (! file_exists($outFile)) {
            return null;
        }

        $text = (string) file_get_contents($outFile);
        @unlink($outFile);

        return trim($text) !== '' ? trim($text) : null;
    }

    private function findTesseractBinary(): ?string
    {
        $which = PHP_OS_FAMILY === 'Windows'
            ? trim((string) shell_exec('where tesseract 2>nul'))
            : trim((string) shell_exec('which tesseract 2>/dev/null'));

        if ($which !== '' && is_executable(explode("\n", $which)[0])) {
            return explode("\n", $which)[0];
        }

        foreach (['/usr/bin/tesseract', '/usr/local/bin/tesseract'] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * @return array{supplier_name: ?string, invoice_number: ?string, invoice_date: ?string, total: ?float, vat_amount: ?float, vat_number: ?string, subtotal: ?float}
     */
    private function parseInvoiceText(string $text): array
    {
        $t = $text;
        $upper = strtoupper($t);

        $result = [
            'supplier_name' => null,
            'invoice_number' => null,
            'invoice_date' => null,
            'total' => null,
            'vat_amount' => null,
            'vat_number' => null,
            'subtotal' => null,
        ];

        if (preg_match('/(?:FROM|SUPPLIER|البائع|المورد|الشركة)[:\s]*(.{3,120})/u', $t, $m)) {
            $result['supplier_name'] = trim($m[1]);
        }
        if (! $result['supplier_name'] && preg_match('/^(.{3,80})$/m', trim($t), $m)) {
            $result['supplier_name'] = trim($m[1]);
        }

        if (preg_match('/(?:invoice\s*(?:no|number|#)|فاتورة\s*رقم|رقم\s*الفاتورة)[:\s]*([A-Z0-9\-\/]+)/iu', $t, $m)) {
            $result['invoice_number'] = trim($m[1]);
        }

        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/', $t, $m)) {
            $result['invoice_date'] = $m[3].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/(?:SUBTOTAL|المجموع\s*الفرعي|الإجمالي\s*قبل)[:\s]*([0-9,]+\.?\d{0,2})/iu', $t, $m)) {
            $result['subtotal'] = (float) str_replace(',', '', $m[1]);
        }

        if (preg_match('/(?:^|\s)(?:TOTAL|المجموع|الإجمالي|NET\s*AMOUNT|AMOUNT\s*DUE|الصافي|المدفوع)[:\s]*([0-9٬,]+\.?\d{0,2})/im', $upper, $m)) {
            $result['total'] = (float) str_replace([',', '٬'], '', $m[1]);
        }
        if ($result['total'] === null && preg_match('/([\d٫.,]+)\s*(?:SAR|ريال|ر\.س|﷼)/u', $t, $m)) {
            $arabicIndic = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.'];
            $num = strtr(str_replace([',', '٬'], '', $m[1]), $arabicIndic);
            if (is_numeric($num)) {
                $result['total'] = (float) $num;
            }
        }

        if (preg_match('/(?:VAT|ضريبة\s*القيمة|ضريبة|ض\.ق\.م)[:\s]*([0-9٬,]+\.?\d{0,2})/iu', $t, $m)) {
            $result['vat_amount'] = (float) str_replace([',', '٬'], '', $m[1]);
        }

        if (preg_match('/(?:VAT\s*(?:No|Number|Reg)|الرقم\s*الضريبي)[:\s]*(\d{10,15})/iu', $t, $m)) {
            $result['vat_number'] = $m[1];
        }

        return $result;
    }
}
