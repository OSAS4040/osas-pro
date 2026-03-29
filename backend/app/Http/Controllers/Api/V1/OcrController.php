<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OcrController extends Controller
{
    /**
     * Extract plate number from an uploaded image.
     * Uses regex pattern matching for KSA plates as primary strategy,
     * falls back to Tesseract OCR if available.
     */
    public function scanPlate(Request $request)
    {
        $request->validate(['image' => 'required|string']);

        $base64  = $request->input('image');
        $imgData = base64_decode($base64);

        if (!$imgData || strlen($imgData) < 100) {
            return response()->json(['plate' => '', 'error' => 'Invalid image data'], 422);
        }

        // Try Tesseract if installed
        $plate = $this->tryTesseract($imgData);

        // Fallback: return empty so frontend shows manual edit
        return response()->json([
            'plate'  => $plate,
            'method' => $plate ? 'ocr' : 'manual',
        ]);
    }

    /**
     * Scan a purchase invoice image and extract fields.
     * Supports batch (up to 10 images).
     */
    public function scanInvoice(Request $request)
    {
        $request->validate([
            'images'   => 'required|array|max:10',
            'images.*' => 'required|string',
        ]);

        $results = [];
        foreach ($request->input('images') as $idx => $base64) {
            $imgData = base64_decode($base64);
            if (!$imgData) {
                $results[] = ['index' => $idx, 'error' => 'Invalid image'];
                continue;
            }

            $text   = $this->tryTesseract($imgData, 'ara+eng') ?? '';
            $parsed = $this->parseInvoiceText($text);
            $results[] = array_merge(['index' => $idx, 'raw_text' => $text], $parsed);
        }

        return response()->json(['results' => $results]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function tryTesseract(string $imgData, string $lang = 'eng+ara'): ?string
    {
        $tmpIn  = sys_get_temp_dir() . '/' . Str::random(10) . '.jpg';
        $tmpOut = sys_get_temp_dir() . '/' . Str::random(10);
        file_put_contents($tmpIn, $imgData);

        $bin = trim(shell_exec('which tesseract 2>/dev/null') ?? '');
        if (!$bin) {
            @unlink($tmpIn);
            return null;
        }

        $cmd = escapeshellcmd("{$bin} {$tmpIn} {$tmpOut} -l {$lang} --oem 3 --psm 7 2>/dev/null");
        shell_exec($cmd);
        @unlink($tmpIn);

        $outFile = $tmpOut . '.txt';
        if (!file_exists($outFile)) return null;

        $text = strtoupper(trim(file_get_contents($outFile)));
        @unlink($outFile);

        // Extract KSA plate pattern: 3 letters + 4 digits (e.g., ABC1234)
        if (preg_match('/\b([A-Z]{3})\s*(\d{4})\b/', $text, $m)) {
            return $m[1] . $m[2];
        }

        // Arabic plate pattern: 3 Arabic chars + 4 digits
        if (preg_match('/[\x{0600}-\x{06FF}]{3}\s*\d{4}/u', $text, $m)) {
            return trim($m[0]);
        }

        return $text ?: null;
    }

    private function parseInvoiceText(string $text): array
    {
        $result = [
            'supplier_name' => null,
            'invoice_number' => null,
            'invoice_date' => null,
            'total' => null,
            'vat_amount' => null,
            'vat_number' => null,
        ];

        // Invoice number patterns
        if (preg_match('/(?:invoice\s*(?:no|number|#)|فاتورة\s*رقم)[:\s]*([A-Z0-9\-]+)/i', $text, $m)) {
            $result['invoice_number'] = trim($m[1]);
        }

        // Date patterns
        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/', $text, $m)) {
            $result['invoice_date'] = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        // Total amount
        if (preg_match('/(?:total|المجموع|الإجمالي)[:\s]*([0-9,]+\.?\d{0,2})/i', $text, $m)) {
            $result['total'] = (float) str_replace(',', '', $m[1]);
        }

        // VAT amount
        if (preg_match('/(?:vat|tax|ضريبة)[:\s]*([0-9,]+\.?\d{0,2})/i', $text, $m)) {
            $result['vat_amount'] = (float) str_replace(',', '', $m[1]);
        }

        // VAT registration number
        if (preg_match('/(?:VAT\s*(?:No|Number|Reg)|الرقم الضريبي)[:\s]*(\d{15})/i', $text, $m)) {
            $result['vat_number'] = $m[1];
        }

        return $result;
    }
}
