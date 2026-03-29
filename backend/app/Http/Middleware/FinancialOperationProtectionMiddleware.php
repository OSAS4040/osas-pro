<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary protection layer: requires tenant context and Idempotency-Key on mutating
 * financial API calls (wallet, invoices, payments, POS, inventory, external invoices, fleet wallet, ledger/COA).
 */
class FinancialOperationProtectionMiddleware
{
    private const FINANCIAL_PATH_PREFIXES = [
        'api/v1/wallet',
        'api/v1/wallets',
        'api/v1/invoices',
        'api/v1/payments',
        'api/v1/pos',
        'api/v1/inventory',
        'api/v1/external/v1/invoices',
        'api/v1/fleet-portal/wallet',
        'api/v1/ledger',
        'api/v1/chart-of-accounts',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->has('tenant_company_id')) {
            return response()->json([
                'message'  => 'Tenant context required.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        if ($this->requiresIdempotency($request) && ! $request->header('Idempotency-Key')) {
            return response()->json([
                'message'  => 'Idempotency-Key header is required.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        return $next($request);
    }

    private function requiresIdempotency(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        $path = $request->path();

        foreach (self::FINANCIAL_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
