<?php

declare(strict_types=1);

namespace App\Reporting\Export;

use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV / XLSX / PDF for a normalized row grid (read-only).
 */
final class ReportingExportStreamResponse
{
    /**
     * @param  list<list<string>>  $rows
     */
    public static function make(string $reportId, string $format, array $rows): Response
    {
        $stem = Str::slug(str_replace('.', '-', $reportId), '-');
        $stamp = now()->format('Ymd_His');

        return match ($format) {
            'csv' => self::csv($stem.'_'.$stamp.'.csv', $rows),
            'xlsx' => self::xlsx($stem.'_'.$stamp.'.xlsx', $rows),
            'pdf' => self::pdf($stem.'_'.$stamp.'.pdf', $rows),
            default => throw new \InvalidArgumentException('Unsupported format: '.$format),
        };
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private static function csv(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn (string $c): string => $c, $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private static function xlsx(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            foreach ($rows as $row) {
                $cells = [];
                foreach ($row as $c) {
                    $cells[] = $c;
                }
                $writer->addRow(Row::fromValues($cells));
            }
            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private static function pdf(string $filename, array $rows): StreamedResponse
    {
        $max = max(1, (int) config('reporting.export.pdf_max_rows', 400));
        if (count($rows) > $max) {
            $rows = array_merge(array_slice($rows, 0, $max), [['…', 'truncated (pdf_max_rows)', (string) $max]]);
        }

        return response()->streamDownload(function () use ($rows): void {
            /** @var PdfWrapper $pdf */
            $pdf = app(PdfWrapper::class);
            echo $pdf->loadView('reporting.export_table', ['rows' => $rows])->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
