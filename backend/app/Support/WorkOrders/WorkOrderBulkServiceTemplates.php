<?php

declare(strict_types=1);

namespace App\Support\WorkOrders;

/**
 * Minimal line templates for bulk OSAS load tests (pending technician assignment).
 */
final class WorkOrderBulkServiceTemplates
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function linesFor(string $serviceCode): array
    {
        $code = strtolower(trim($serviceCode));

        return match ($code) {
            'oil_change' => [
                [
                    'item_type' => 'labor',
                    'name' => 'Oil change',
                    'quantity' => 1,
                    'unit_price' => 80,
                    'tax_rate' => 15,
                ],
            ],
            'tire_rotation' => [
                [
                    'item_type' => 'labor',
                    'name' => 'Tire rotation',
                    'quantity' => 1,
                    'unit_price' => 40,
                    'tax_rate' => 15,
                ],
            ],
            default => [
                [
                    'item_type' => 'labor',
                    'name' => 'Bulk service: '.$serviceCode,
                    'quantity' => 1,
                    'unit_price' => 50,
                    'tax_rate' => 15,
                ],
            ],
        };
    }
}
