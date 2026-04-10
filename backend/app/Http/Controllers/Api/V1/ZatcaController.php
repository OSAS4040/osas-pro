<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ZatcaLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ZatcaController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $simulation = (bool) config('zatca.simulation_mode', true);

        $logs = ZatcaLog::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $clearancePending = ZatcaLog::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'data' => [
                'simulation_mode'      => $simulation,
                'integration_active'   => false,
                'phase2_active'        => false,
                'csid_valid'           => false,
                'cr_valid'             => false,
                'pending_clearance'    => $clearancePending,
                'last_sync'            => null,
                'recent_logs'          => $logs->map(fn (ZatcaLog $l) => $this->logSummary($l))->values()->all(),
            ],
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
        if (! config('zatca.simulation_mode', true)) {
            return response()->json([
                'message'  => 'تكامل ZATCA الإنتاجي غير مهيأ بعد.',
                'code'     => 'ZATCA_NOT_CONFIGURED',
                'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
            ], 501);
        }

        $data = $request->validate(['invoice_id' => 'required|integer']);
        $invoice = Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($data['invoice_id']);

        $traceId = app()->bound('trace_id') ? (string) app('trace_id') : (string) Str::uuid();

        $log = ZatcaLog::create([
            'uuid'              => (string) Str::uuid(),
            'company_id'        => (int) $request->user()->company_id,
            'reference_type'    => Invoice::class,
            'reference_id'      => $invoice->id,
            'action'            => 'clearance_simulation',
            'status'            => 'simulated',
            'request_payload'   => ['invoice_id' => $invoice->id],
            'response_payload'  => [
                'invoice_uuid' => $invoice->uuid,
                'invoice_hash' => $invoice->invoice_hash,
            ],
            'trace_id'          => $traceId,
        ]);

        return response()->json([
            'data' => [
                'log'             => $this->logSummary($log),
                'simulation_mode' => true,
            ],
            'message' => 'تم تسجيل العملية محلياً (وضع محاكاة — لم يُرسل إلى منصة هيئة الزكاة والضريبة).',
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function logSummary(ZatcaLog $l): array
    {
        return [
            'id'               => $l->id,
            'action'           => $l->action,
            'status'           => $l->status,
            'reference_type'   => $l->reference_type,
            'reference_id'     => $l->reference_id,
            'created_at'       => $l->created_at?->toIso8601String(),
        ];
    }
}
