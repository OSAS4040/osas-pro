<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\External\StoreExternalInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class ExternalInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function store(StoreExternalInvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();

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
