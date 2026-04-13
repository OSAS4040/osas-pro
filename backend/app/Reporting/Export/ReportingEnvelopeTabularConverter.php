<?php

declare(strict_types=1);

namespace App\Reporting\Export;

/**
 * Converts a ReportingApiEnvelope-shaped array into a rectangular 2D string grid for CSV/XLSX/PDF.
 * When {@see $envelope} contains {@code data.items} as a list of associative rows, those rows are
 * emitted as a dedicated wide table after scalar metadata.
 */
final class ReportingEnvelopeTabularConverter
{
    /**
     * @param  array<string, mixed>  $envelope
     * @return list<list<string>>
     */
    public function toRows(array $envelope): array
    {
        $rows = [];
        $rows[] = ['Key', 'Value'];

        $report = is_array($envelope['report'] ?? null) ? $envelope['report'] : [];
        $meta = is_array($envelope['meta'] ?? null) ? $envelope['meta'] : [];
        $traceId = (string) ($envelope['trace_id'] ?? '');

        foreach ($this->flattenScalarish($report, 'report') as [$k, $v]) {
            $rows[] = [$k, $v];
        }
        foreach ($this->flattenScalarish($meta, 'meta') as [$k, $v]) {
            $rows[] = [$k, $v];
        }
        $rows[] = ['trace_id', $traceId];

        $data = is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
        $items = null;
        if (isset($data['items']) && is_array($data['items']) && $this->isListOfAssoc($data['items'])) {
            $items = $data['items'];
            unset($data['items']);
        }

        $rows[] = [];
        $rows[] = ['__section__', 'data'];

        foreach ($this->flattenScalarish($data, 'data') as [$k, $v]) {
            $rows[] = [$k, $v];
        }

        if ($items !== null && $items !== []) {
            $rows[] = [];
            $rows[] = ['__section__', 'data.items'];
            $first = $items[0];
            if (! is_array($first)) {
                return $this->padRows($rows);
            }
            $headers = array_keys($first);
            $rows[] = $headers;
            foreach ($items as $it) {
                if (! is_array($it)) {
                    continue;
                }
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $this->scalarToCell($it[$h] ?? null);
                }
                $rows[] = $line;
            }
        }

        return $this->padRows($rows);
    }

    /**
     * @param  list<list<string>>  $rows
     * @return list<list<string>>
     */
    private function padRows(array $rows): array
    {
        $max = 0;
        foreach ($rows as $r) {
            $max = max($max, count($r));
        }
        if ($max < 2) {
            $max = 2;
        }

        return array_map(static function (array $r) use ($max): array {
            $vals = array_values($r);

            return array_pad($vals, $max, '');
        }, $rows);
    }

    /**
     * @return list{array{0:string,1:string}}
     */
    private function flattenScalarish(mixed $node, string $prefix): array
    {
        if (! is_array($node)) {
            return [[$prefix, $this->scalarToCell($node)]];
        }
        if ($node === []) {
            return [[$prefix, '[]']];
        }
        if (array_is_list($node)) {
            $json = json_encode($node, JSON_UNESCAPED_UNICODE);

            return [[$prefix, $json === false ? '' : $json]];
        }

        $out = [];
        foreach ($node as $k => $v) {
            $p = $prefix.'.'.$k;
            if (is_array($v) && $v !== [] && ! array_is_list($v)) {
                foreach ($this->flattenScalarish($v, $p) as $pair) {
                    $out[] = $pair;
                }
            } elseif (is_array($v)) {
                $json = json_encode($v, JSON_UNESCAPED_UNICODE);
                $out[] = [$p, $json === false ? '' : $json];
            } else {
                $out[] = [$p, $this->scalarToCell($v)];
            }
        }

        return $out;
    }

    private function scalarToCell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');
        }

        return (string) $v;
    }

    private function isListOfAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        if (! array_is_list($arr)) {
            return false;
        }
        $first = $arr[0];

        return is_array($first) && ($first === [] || ! array_is_list($first));
    }
}
