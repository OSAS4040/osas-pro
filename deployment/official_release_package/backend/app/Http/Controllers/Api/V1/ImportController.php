<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ImportController extends Controller
{
    public function importProducts(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120']);

        $company  = auth()->user()->company_id;
        $userId = auth()->id();
        $rows     = $this->parseXlsx($request->file('file'));
        $imported = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? $row['الاسم'] ?? ''));
            if ($name === '') {
                $errors[] = "السطر " . ($i + 2) . ": اسم المنتج مطلوب";
                continue;
            }

            try {
                $sku = trim((string) ($row['sku'] ?? $row['SKU'] ?? ''));
                $barcode = trim((string) ($row['barcode'] ?? $row['الباركود'] ?? ''));
                $productType = strtolower(trim((string) ($row['product_type'] ?? 'physical')));
                if (!in_array($productType, ['physical', 'service', 'consumable', 'labor'], true)) {
                    $productType = 'physical';
                }
                $lookup = ['company_id' => $company, 'name' => $name];
                if ($sku !== '') $lookup['sku'] = $sku;
                elseif ($barcode !== '') $lookup['barcode'] = $barcode;

                Product::updateOrCreate(
                    $lookup,
                    [
                        'name' => $name,
                        'name_ar' => $row['name_ar'] ?? null,
                        'sku' => $sku !== '' ? $sku : null,
                        'barcode' => $barcode !== '' ? $barcode : null,
                        'product_type' => $productType,
                        'sale_price' => (float) ($row['sale_price'] ?? $row['price'] ?? 0),
                        'tax_rate' => (float) ($row['tax_rate'] ?? 0),
                        'is_active' => (bool) (int) ($row['is_active'] ?? 1),
                        'company_id' => $company,
                        'created_by_user_id' => $userId,
                    ]
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "السطر " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'imported' => $imported,
            'errors'   => $errors,
            'message'  => "تم استيراد {$imported} منتج بنجاح" . (count($errors) ? " مع " . count($errors) . " خطأ" : ""),
        ]);
    }

    /**
     * استيراد خدمات متعددة من Excel (.xlsx) مع الأسعار والضريبة.
     * أعمدة مقترحة: name, name_ar, code, base_price, tax_rate, estimated_minutes, is_active, description
     * (أو بالعربية: الاسم، الاسم بالعربية، الرمز، السعر، نسبة الضريبة، الدقائق، نشط، الوصف)
     */
    public function importServices(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120']);

        $company = auth()->user()->company_id;
        $userId = auth()->id();
        $rows = $this->parseXlsx($request->file('file'));
        $imported = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? $row['الاسم'] ?? ''));
            if ($name === '') {
                $errors[] = 'السطر ' . ($i + 2) . ': اسم الخدمة مطلوب';

                continue;
            }

            try {
                $codeRaw = trim((string) ($row['code'] ?? $row['الرمز'] ?? ''));
                $code = $codeRaw !== '' ? $codeRaw : null;

                $estimated = $row['estimated_minutes'] ?? $row['الدقائق'] ?? null;
                $estimatedMinutes = $estimated === null || $estimated === '' ? null : (int) $estimated;

                $payload = [
                    'name' => $name,
                    'name_ar' => ($nar = trim((string) ($row['name_ar'] ?? $row['الاسم_بالعربية'] ?? $row['الاسم بالعربي'] ?? ''))) !== '' ? $nar : null,
                    'code' => $code,
                    'description' => ($d = trim((string) ($row['description'] ?? $row['الوصف'] ?? ''))) !== '' ? $d : null,
                    'base_price' => (float) ($row['base_price'] ?? $row['السعر'] ?? $row['price'] ?? 0),
                    'tax_rate' => (float) ($row['tax_rate'] ?? $row['نسبة_الضريبة'] ?? $row['الضريبة'] ?? 15),
                    'estimated_minutes' => $estimatedMinutes !== null && $estimatedMinutes > 0 ? $estimatedMinutes : null,
                    'is_active' => (bool) (int) ($row['is_active'] ?? $row['نشط'] ?? 1),
                    'company_id' => $company,
                    'created_by_user_id' => $userId,
                ];

                if ($code !== null) {
                    Service::updateOrCreate(
                        ['company_id' => $company, 'code' => $code],
                        $payload,
                    );
                } else {
                    $existing = Service::where('company_id', $company)->where('name', $name)->first();
                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        Service::create($payload);
                    }
                }
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'السطر ' . ($i + 2) . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'imported' => $imported,
            'errors' => $errors,
            'message' => "تم استيراد {$imported} خدمة بنجاح" . (count($errors) ? ' مع ' . count($errors) . ' خطأ' : ''),
        ]);
    }

    public function importVehicles(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120']);

        $company  = auth()->user()->company_id;
        $rows     = $this->parseXlsx($request->file('file'));
        $imported = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $plate = strtoupper(trim($row['plate_number'] ?? $row['رقم اللوحة'] ?? ''));
            if (!$plate) { $errors[] = "السطر " . ($i + 2) . ": رقم اللوحة مطلوب"; continue; }

            try {
                Vehicle::updateOrCreate(
                    ['plate_number' => $plate, 'company_id' => $company],
                    [
                        'make'       => $row['make']  ?? $row['الشركة'] ?? null,
                        'model'      => $row['model'] ?? $row['الموديل'] ?? null,
                        'year'       => (int) ($row['year'] ?? $row['السنة'] ?? date('Y')),
                        'color'      => $row['color'] ?? $row['اللون'] ?? null,
                        'vin'        => $row['vin']   ?? $row['رقم الهيكل'] ?? null,
                        'company_id' => $company,
                    ]
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "السطر " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'imported' => $imported,
            'errors'   => $errors,
            'message'  => "تم استيراد {$imported} مركبة بنجاح" . (count($errors) ? " مع " . count($errors) . " خطأ" : ""),
        ]);
    }

    public function importEmployees(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120']);

        $company  = auth()->user()->company_id;
        $rows     = $this->parseXlsx($request->file('file'));
        $imported = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $name = trim($row['name'] ?? $row['الاسم'] ?? '');
            if (!$name) { $errors[] = "السطر " . ($i + 2) . ": الاسم مطلوب"; continue; }

            try {
                $nid = trim((string) ($row['national_id'] ?? $row['رقم الهوية'] ?? ''));
                if ($nid === '') {
                    $nid = 'import-'.Str::lower(Str::random(12));
                }
                Employee::updateOrCreate(
                    ['national_id' => $nid, 'company_id' => $company],
                    [
                        'name'        => $name,
                        'position'    => $row['position'] ?? $row['role'] ?? $row['الوظيفة'] ?? $row['التخصص'] ?? null,
                        'phone'       => $row['phone'] ?? $row['الجوال'] ?? null,
                        'email'       => $row['email'] ?? $row['البريد'] ?? null,
                        'department'  => $row['department'] ?? $row['القسم'] ?? null,
                        'base_salary' => (float) ($row['base_salary'] ?? $row['salary'] ?? $row['الراتب'] ?? 0),
                        'hire_date'   => $row['hire_date'] ?? $row['تاريخ التعيين'] ?? now()->toDateString(),
                        'status'      => 'active',
                        'company_id'  => $company,
                    ]
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "السطر " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'imported' => $imported,
            'errors'   => $errors,
            'message'  => "تم استيراد {$imported} موظف بنجاح",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function productsTemplate()
    {
        return $this->streamXlsxTemplate('products_template.xlsx', [[
            'name', 'name_ar', 'sku', 'barcode', 'product_type', 'sale_price', 'tax_rate', 'is_active',
        ]]);
    }

    public function vehiclesTemplate()
    {
        return $this->streamXlsxTemplate('vehicles_template.xlsx', [[
            'plate_number', 'make', 'model', 'year', 'color', 'vin',
        ]]);
    }

    private function streamXlsxTemplate(string $filename, array $rows)
    {
        return response()->streamDownload(function () use ($rows): void {
            $writer = new XlsxWriter();
            $writer->openToFile('php://output');
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function parseXlsx(\Illuminate\Http\UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (!$path) return [];
        $reader = new XlsxReader();
        $reader->open($path);
        $rows = [];
        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $sheetRow) {
                $cells = array_map(static fn ($c) => trim((string) $c->getValue()), $sheetRow->getCells());
                if (!$headers) {
                    $headers = array_values(array_filter($cells, static fn ($v) => $v !== ''));
                    continue;
                }
                if (!$headers) continue;
                $row = [];
                $hasAny = false;
                foreach ($headers as $k => $h) {
                    $value = $cells[$k] ?? '';
                    if ($value !== '') $hasAny = true;
                    $row[$h] = $value;
                }
                if ($hasAny) {
                    $rows[] = $row;
                }
            }
            break;
        }
        $reader->close();
        return $rows;
    }
}
