<?php

declare(strict_types=1);

namespace App\Reporting\Operations;

/**
 * SPA-relative routes for drill-down (no external URLs).
 */
final class OperationFeedEntityRouteResolver
{
    public function resolve(string $feedType, int $entityId, ?int $linkId): ?string
    {
        return match ($feedType) {
            'work_order' => '/work-orders/'.$entityId,
            'invoice'    => '/invoices/'.$entityId,
            'ticket'     => '/support',
            'payment'    => $linkId !== null && $linkId > 0
                ? '/invoices/'.$linkId
                : null,
            default => null,
        };
    }
}
