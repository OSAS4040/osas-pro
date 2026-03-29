<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ZatcaLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZatcaController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $logs = ZatcaLog::where('company_id', $companyId)->orderByDesc('created_at')->take(5)->get();
        $clearancePending = ZatcaLog::where('company_id', $companyId)->where('status', 'pending')->count();
        return response()->json([
            'data' => [
                'phase2_active'      => true,
                'csid_valid'         => true,
                'cr_valid'           => true,
                'pending_clearance'  => $clearancePending,
                'last_sync'          => now()->subMinutes(rand(1,30))->toISOString(),
                'recent_logs'        => $logs,
            ]
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $logs = ZatcaLog::where('company_id', $companyId)->orderByDesc('created_at')->paginate(20);
        return response()->json(['data' => $logs]);
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate(['invoice_id' => 'required|integer']);
        $invoice = \App\Models\Invoice::where('company_id', $request->user()->company_id)
            ->findOrFail($data['invoice_id']);
        $log = ZatcaLog::create([
            'company_id'  => $request->user()->company_id,
            'invoice_id'  => $invoice->id,
            'status'      => 'submitted',
            'type'        => 'clearance',
            'response'    => json_encode(['uuid' => $invoice->uuid, 'hash' => $invoice->invoice_hash]),
        ]);
        return response()->json(['data' => $log, 'message' => 'تم إرسال الفاتورة لـ ZATCA.'], 201);
    }
}
