<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PlatformCustomerPriceVersion;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $customer = $this->resolvePortalCustomer($user);

        $vehicles = $customer ? Vehicle::where('company_id', $companyId)
            ->where('customer_id', $customer->id)->count() : 0;
        $invoices = $customer ? Invoice::where('company_id', $companyId)
            ->where('customer_id', $customer->id)->count() : 0;
        $bookings = $customer ? Booking::where('company_id', $companyId)
            ->where('customer_id', $customer->id)->count() : 0;

        return response()->json([
            'data' => [
                'customer' => $customer,
                'stats' => ['vehicles' => $vehicles, 'invoices' => $invoices, 'bookings' => $bookings],
            ],
        ]);
    }

    /**
     * اطلاع العميل على نسخ الأسعار المعتمدة (سعر البيع فقط — بدون تكاليف مزود).
     */
    public function pricing(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->role->isCustomer()) {
            return response()->json([
                'message' => 'هذه الخاصية متاحة لحسابات العملاء فقط.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $customer = $this->resolvePortalCustomer($user);
        if ($customer === null) {
            return response()->json([
                'data' => ['versions' => []],
                'trace_id' => app('trace_id'),
            ]);
        }

        $versions = PlatformCustomerPriceVersion::query()
            ->where('company_id', $user->company_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('version_no')
            ->limit(100)
            ->get(['uuid', 'version_no', 'is_reference', 'activated_at', 'sell_snapshot', 'contract_id', 'root_contract_id'])
            ->map(static function (PlatformCustomerPriceVersion $v): array {
                return [
                    'uuid' => $v->uuid,
                    'version_no' => (int) $v->version_no,
                    'is_reference' => (bool) $v->is_reference,
                    'activated_at' => $v->activated_at?->toIso8601String(),
                    'sell_snapshot' => $v->sell_snapshot,
                    'contract_id' => $v->contract_id,
                    'root_contract_id' => $v->root_contract_id,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => ['versions' => $versions],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function resolvePortalCustomer(User $user): ?Customer
    {
        $companyId = $user->company_id;
        if ($companyId === null || (int) $companyId <= 0) {
            return null;
        }

        if ($user->customer_id !== null && (int) $user->customer_id > 0) {
            return Customer::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $user->customer_id)
                ->first();
        }

        return Customer::query()
            ->where('company_id', $companyId)
            ->where('email', $user->email)
            ->first();
    }
}
