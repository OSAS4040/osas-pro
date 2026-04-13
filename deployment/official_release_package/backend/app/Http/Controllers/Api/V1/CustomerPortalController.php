<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Invoice;
use App\Models\Booking;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $customer = \App\Models\Customer::where('company_id', $companyId)
            ->where('email', $user->email)->first();
        
        $vehicles = $customer ? Vehicle::where('company_id', $companyId)
            ->where('customer_id', $customer->id)->count() : 0;
        $invoices = $customer ? Invoice::where('company_id', $companyId)
            ->where('customer_id', $customer->id)->count() : 0;
        $bookings = $customer ? Booking::where('company_id', $companyId)
            ->where('customer_id', $customer->id)->count() : 0;
        
        return response()->json([
            'data' => [
                'customer'  => $customer,
                'stats'     => ['vehicles' => $vehicles, 'invoices' => $invoices, 'bookings' => $bookings],
            ]
        ]);
    }
}
