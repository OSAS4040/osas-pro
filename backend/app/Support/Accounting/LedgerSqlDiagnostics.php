<?php

namespace App\Support\Accounting;

use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Extracts SQLSTATE / constraint / driver codes from an exception chain for ledger alerts.
 */
final class LedgerSqlDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function fromThrowable(?Throwable $e): array
    {
        if ($e === null) {
            return [];
        }

        $out = [
            'chain' => [],
        ];

        $cursor = $e;
        $depth = 0;
        while ($cursor !== null && $depth < 12) {
            $entry = [
                'class'   => $cursor::class,
                'message' => mb_substr($cursor->getMessage(), 0, 800),
            ];

            if ($cursor instanceof QueryException) {
                $entry['sqlstate'] = method_exists($cursor, 'getSqlState')
                    ? $cursor->getSqlState()
                    : (string) (($cursor->errorInfo ?? [])[0] ?? '');
                $entry['sql'] = mb_substr((string) $cursor->getSql(), 0, 500);
                $bindings = $cursor->getBindings();
                if ($bindings !== []) {
                    $entry['bindings_count'] = count($bindings);
                }
            }

            if ($cursor instanceof PDOException) {
                $info = $cursor->errorInfo ?? [];
                $entry['pdo_sqlstate'] = $info[0] ?? null;
                $entry['pdo_driver_code'] = $info[1] ?? null;
            }

            $constraint = self::parsePostgresConstraint($cursor->getMessage());
            if ($constraint !== null) {
                $entry['constraint_name'] = $constraint;
            }

            $out['chain'][] = $entry;
            $cursor = $cursor->getPrevious();
            $depth++;
        }

        $flat = self::flattenPrimary($out['chain']);

        return array_merge($out, $flat);
    }

    /**
     * @param  list<array<string, mixed>>  $chain
     * @return array<string, mixed>
     */
    private static function flattenPrimary(array $chain): array
    {
        foreach ($chain as $entry) {
            if (isset($entry['sqlstate']) && $entry['sqlstate'] !== null && $entry['sqlstate'] !== '') {
                return [
                    'primary_sqlstate'   => $entry['sqlstate'],
                    'primary_constraint' => $entry['constraint_name'] ?? null,
                ];
            }
            if (isset($entry['pdo_sqlstate']) && $entry['pdo_sqlstate'] !== null && $entry['pdo_sqlstate'] !== '') {
                return [
                    'primary_sqlstate'   => $entry['pdo_sqlstate'],
                    'primary_constraint' => $entry['constraint_name'] ?? null,
                ];
            }
        }

        $last = $chain[0] ?? [];

        return [
            'primary_sqlstate'   => $last['sqlstate'] ?? $last['pdo_sqlstate'] ?? null,
            'primary_constraint' => $last['constraint_name'] ?? null,
        ];
    }

    private static function parsePostgresConstraint(string $message): ?string
    {
        if (preg_match('/unique constraint "([^"]+)"/i', $message, $m)) {
            return $m[1];
        }
        if (preg_match('/duplicate key value violates unique constraint "([^"]+)"/i', $message, $m)) {
            return $m[1];
        }

        return null;
    }
}
