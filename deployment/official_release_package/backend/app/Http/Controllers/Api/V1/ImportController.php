<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function importVehicles(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120']);

        $company  = auth()->user()->company_id;
        $rows     = $this->parseCsv($request->file('file'));
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
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120']);

        $company  = auth()->user()->company_id;
        $rows     = $this->parseCsv($request->file('file'));
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

    private function parseCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $path    = $file->getRealPath();
        $content = file_get_contents($path);
        // Handle BOM
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines   = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
        $lines   = array_values($lines);
        if (count($lines) < 2) return [];

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $vals = str_getcsv($line);
            $row  = [];
            foreach ($headers as $k => $h) {
                $row[$h] = isset($vals[$k]) ? trim($vals[$k]) : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
