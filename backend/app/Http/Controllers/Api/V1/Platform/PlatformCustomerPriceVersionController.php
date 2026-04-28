<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformCustomerPriceVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformCustomerPriceVersionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);
        $rows = PlatformCustomerPriceVersion::query()
            ->where('company_id', $data['company_id'])
            ->where('customer_id', $data['customer_id'])
            ->orderByDesc('version_no')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }
}
