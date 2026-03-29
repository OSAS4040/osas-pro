<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'        => 'nullable|integer',
            'customer_type'      => 'nullable|in:b2c,b2b',
            'items'              => 'required|array|min:1',
            'items.*.name'       => 'required|string',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate'   => 'nullable|numeric|min:0|max:100',
            'payment'            => 'nullable|array',
            'payment.method'     => 'required_with:payment|string',
            'payment.amount'     => 'required_with:payment|numeric|min:0',
        ]);

        $apiKey = $request->attributes->get('api_key');
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        $invoice = $this->invoiceService->createInvoice(
            data:      $data,
            companyId: $apiKey->company_id,
            branchId:  $apiKey->branch_id ?? 1,
            userId:    $apiKey->created_by_user_id,
        );

        return response()->json(['data' => $invoice, 'trace_id' => app('trace_id')], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $invoice = Invoice::where('uuid', $uuid)
            ->with(['items', 'payments'])
            ->firstOrFail();

        return response()->json(['data' => $invoice, 'trace_id' => app('trace_id')]);
    }
}
