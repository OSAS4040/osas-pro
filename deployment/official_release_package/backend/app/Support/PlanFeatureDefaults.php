<?php

namespace App\Support;

/**
 * نسخ متطابقة مع Database\Seeders\PlanSeeder — للاستخدام عند غياب صف plan أو عمود features.
 */
final class PlanFeatureDefaults
{
    /**
     * @return array<string, bool>
     */
    public static function associativeForSlug(string $slug): array
    {
        return match ($slug) {
            'trial' => [
                'pos'          => true,
                'invoices'     => true,
                'work_orders'  => false,
                'fleet'        => false,
                'reports'      => false,
                'api_access'   => false,
                'zatca'        => false,
            ],
            'basic' => [
                'pos'          => true,
                'invoices'     => true,
                'work_orders'  => true,
                'fleet'        => false,
                'reports'      => true,
                'api_access'   => false,
                'zatca'        => false,
            ],
            'professional' => [
                'pos'          => true,
                'invoices'     => true,
                'work_orders'  => true,
                'fleet'        => true,
                'reports'      => true,
                'api_access'   => true,
                'zatca'        => true,
            ],
            'enterprise' => [
                'pos'               => true,
                'invoices'          => true,
                'work_orders'       => true,
                'fleet'             => true,
                'reports'           => true,
                'api_access'        => true,
                'zatca'             => true,
                'dedicated_support' => true,
                'sla'               => true,
            ],
            default => self::associativeForSlug('professional'),
        };
    }
}
