<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Enums\WorkOrderStatus;
use App\Exceptions\LedgerPostingFailedException;
use App\Intelligence\Events\InvoiceCreated;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WorkOrder;
use App\Support\Accounting\FinancialGlMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        private readonly WalletService      $walletService,
        private readonly InventoryService   $inventoryService,
        private readonly IdempotencyService $idempotency,
        private readonly LedgerService      $ledger,
        private readonly IntelligentEventEmitter $intelligentEvents,
        private readonly PaymentService     $paymentService,
    ) {}

    /**
     * Generic invoice creation (B2B, proforma, manual).
     */
    public function createInvoice(array $data, int $companyId, int $branchId, int $userId): Invoice
    {
        $invoice = DB::transaction(function () use ($data, $companyId, $branchId, $userId) {
            $traceId = trim((string) (app('trace_id') ?? '')) ?: Str::uuid()->toString();

            [$counter, $invoiceNumber, $previousHash, $invoiceHash] =
                $this->generateSequence($companyId, $data);

            [$subtotal, $taxAmount, $itemsData] = $this->buildItems($data['items'], $companyId);

            $discount = (float) ($data['discount_amount'] ?? 0);
            $total    = round($subtotal + $taxAmount - $discount, 4);

            $invoice = Invoice::create([
                'uuid'                  => Str::uuid(),
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'customer_id'           => $data['customer_id'] ?? null,
                'vehicle_id'            => $data['vehicle_id'] ?? null,
                'created_by_user_id'    => $userId,
                'invoice_number'        => $invoiceNumber,
                'type'                  => $data['type'] ?? 'sale',
                'source_type'           => $data['source_type'] ?? null,
                'source_id'             => $data['source_id'] ?? null,
                'status'                => InvoiceStatus::Pending,
                'customer_type'         => $data['customer_type'] ?? 'b2c',
                'subtotal'              => $subtotal,
                'discount_amount'       => $discount,
                'tax_amount'            => $taxAmount,
                'total'                 => $total,
                'paid_amount'           => 0,
                'due_amount'            => $total,
                'currency'              => $data['currency'] ?? 'SAR',
                'idempotency_key'       => $data['idempotency_key'] ?? null,
                'invoice_hash'          => $invoiceHash,
                'previous_invoice_hash' => $previousHash,
                'invoice_counter'       => $counter,
                'trace_id'              => $traceId,
                'notes'                 => $data['notes'] ?? null,
                'issued_at'             => now(),
                'due_at'                => $data['due_at'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            if (isset($data['payment'])) {
                $this->recordPayment($invoice, $data['payment'], $userId, $traceId);
            }

            $trackedProductIds = $this->trackedProductIdsForInvoice($data['items'], $companyId);
            $this->deductInventoryForItems(
                $data['items'],
                $trackedProductIds,
                $companyId,
                $branchId,
                $userId,
                $invoice,
                $traceId,
            );

            $this->postInvoiceLedger($invoice, $traceId);

            return $invoice->load(['items', 'payments']);
        });

        $this->intelligentEvents->emit(new InvoiceCreated(
            companyId: (int) $invoice->company_id,
            branchId: $invoice->branch_id ? (int) $invoice->branch_id : null,
            causedByUserId: $userId,
            invoiceId: $invoice->id,
            invoiceNumber: (string) $invoice->invoice_number,
            status: $invoice->status->value,
            total: (float) $invoice->total,
            sourceContext: 'InvoiceService::createInvoice',
        ));

        return $invoice;
    }

    /**
     * Must succeed for the surrounding DB transaction to commit: a posted invoice without
     * a balanced journal entry is a financial integrity violation.
     */
    private function postInvoiceLedger(Invoice $invoice, string $traceId): void
    {
        try {
            $lines = FinancialGlMapping::linesForSaleInvoice($invoice);

            $this->ledger->post(
                companyId: $invoice->company_id,
                data: [
                    'type'        => JournalEntryType::Sale->value,
                    'description' => "Sale Invoice {$invoice->invoice_number}",
                    'source_type' => Invoice::class,
                    'source_id'   => $invoice->id,
                    'entry_date'  => $invoice->issued_at?->toDateString() ?? now()->toDateString(),
                    'lines'       => $lines,
                    'trace_id'    => $traceId,
                ],
                branchId: $invoice->branch_id,
                userId:   $invoice->created_by_user_id,
            );
        } catch (LedgerPostingFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LedgerPostingFailedException(
                source: 'invoice',
                companyId: (int) $invoice->company_id,
                invoiceId: $invoice->id,
                previous: $e,
            );
        }
    }

    /**
     * Issue an invoice from a completed work order (B2B flow).
     * Work order items become invoice lines.
     */
    public function issueFromWorkOrder(WorkOrder $order, int $userId, ?string $idempotencyKey = null): Invoice
    {
        $companyId = $order->company_id;

        if (! in_array($order->status->value, ['completed', 'delivered'])) {
            throw new \DomainException('Work order must be completed or delivered before issuing an invoice.');
        }

        $existingInvoice = Invoice::where('source_type', WorkOrder::class)
            ->where('source_id', $order->id)
            ->first();

        if ($existingInvoice) {
            throw new \DomainException("Invoice {$existingInvoice->invoice_number} already exists for this work order.");
        }

        $order->loadMissing('items');

        $items = $order->items->map(fn($item) => [
            'name'            => $item->name,
            'description'     => $item->sku ?? null,
            'product_id'      => $item->product_id ?? null,
            'service_id'      => null,
            'sku'             => $item->sku ?? null,
            'quantity'        => (float) $item->quantity,
            'unit_price'      => (float) $item->unit_price,
            'cost_price'      => null,
            'discount_amount' => (float) $item->discount_amount,
            'tax_rate'        => (float) $item->tax_rate,
        ])->toArray();

        $data = [
            'customer_id'   => $order->customer_id,
            'vehicle_id'    => $order->vehicle_id,
            'type'          => 'sale',
            'source_type'   => WorkOrder::class,
            'source_id'     => $order->id,
            'customer_type' => 'b2b',
            'items'         => $items,
            'notes'         => $order->notes,
        ];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $data['idempotency_key'] = trim($idempotencyKey);
        }

        $invoice = $this->createInvoice($data, $companyId, $order->branch_id, $userId);

        $order->update([
            'invoice_id'             => $invoice->id,
            'work_order_sync_status' => 'invoiced',
        ]);

        return $invoice;
    }

    /**
     * Credit tenants: single invoice at managerial approval (per work order), before execution starts.
     */
    public function issueFromApprovedCreditWorkOrder(WorkOrder $order, int $userId, ?string $idempotencyKey = null): Invoice
    {
        if ($order->status !== WorkOrderStatus::Approved) {
            throw new \DomainException('Work order must be in approved status to issue the credit approval invoice.');
        }

        $existingInvoice = Invoice::where('source_type', WorkOrder::class)
            ->where('source_id', $order->id)
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $order->loadMissing('items');

        $items = $order->items->map(fn ($item) => [
            'name' => $item->name,
            'description' => $item->sku ?? null,
            'product_id' => $item->product_id ?? null,
            'service_id' => null,
            'sku' => $item->sku ?? null,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'cost_price' => null,
            'discount_amount' => (float) $item->discount_amount,
            'tax_rate' => (float) $item->tax_rate,
        ])->toArray();

        $data = [
            'customer_id' => $order->customer_id,
            'vehicle_id' => $order->vehicle_id,
            'type' => 'sale',
            'source_type' => WorkOrder::class,
            'source_id' => $order->id,
            'customer_type' => 'b2b',
            'items' => $items,
            'notes' => $order->notes,
        ];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $data['idempotency_key'] = trim($idempotencyKey);
        }

        $invoice = $this->createInvoice($data, (int) $order->company_id, (int) $order->branch_id, $userId);

        $order->update([
            'invoice_id' => $invoice->id,
            'work_order_sync_status' => 'invoiced',
        ]);

        return $invoice;
    }

    private function generateSequence(int $companyId, array $data): array
    {
        $maxCounter = Invoice::where('company_id', $companyId)
            ->max('invoice_counter') ?? 0;

        $counter       = $maxCounter + 1;
        $invoiceNumber = sprintf('INV-%d-%06d', $companyId, $counter);

        while (Invoice::where('company_id', $companyId)->where('invoice_number', $invoiceNumber)->exists()) {
            $counter++;
            $invoiceNumber = sprintf('INV-%d-%06d', $companyId, $counter);
        }

        $previous = Invoice::where('company_id', $companyId)
            ->orderByDesc('invoice_counter')
            ->first();
        $previousHash  = $previous?->invoice_hash ?? hash('sha256', 'genesis');

        $total       = collect($data['items'])->sum(fn($i) =>
            ((float)$i['quantity'] * (float)$i['unit_price']) * (1 + (float)($i['tax_rate'] ?? 15) / 100)
        );
        $invoiceHash = hash('sha256', $invoiceNumber . number_format($total, 4, '.', '') . $previousHash);

        return [$counter, $invoiceNumber, $previousHash, $invoiceHash];
    }

    private function buildItems(array $items, int $companyId): array
    {
        $subtotal  = 0.0;
        $taxAmount = 0.0;
        $itemsData = [];

        foreach ($items as $item) {
            $lineSub   = round((float)$item['quantity'] * (float)$item['unit_price'] - (float)($item['discount_amount'] ?? 0), 4);
            $lineTax   = round($lineSub * ((float)($item['tax_rate'] ?? 15) / 100), 4);
            $lineTotal = $lineSub + $lineTax;

            $subtotal  += $lineSub;
            $taxAmount += $lineTax;

            $itemsData[] = [
                'company_id'      => $companyId,
                'product_id'      => $item['product_id'] ?? null,
                'service_id'      => $item['service_id'] ?? null,
                'name'            => $item['name'],
                'description'     => $item['description'] ?? null,
                'sku'             => $item['sku'] ?? null,
                'quantity'        => $item['quantity'],
                'unit_price'      => $item['unit_price'],
                'cost_price'      => $item['cost_price'] ?? null,
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate'        => $item['tax_rate'] ?? 15,
                'tax_amount'      => $lineTax,
                'subtotal'        => $lineSub,
                'total'           => $lineTotal,
                'line_total'      => $lineTotal,
            ];
        }

        return [$subtotal, $taxAmount, $itemsData];
    }

    private function recordPayment(Invoice $invoice, array $paymentData, int $userId, string $traceId): Payment
    {
        $payment = $this->paymentService->createPayment(
            invoice:   $invoice,
            amount:    (float) $paymentData['amount'],
            method:    (string) $paymentData['method'],
            userId:    $userId,
            traceId:   $traceId,
            branchId:  $invoice->branch_id,
            reference: $paymentData['reference'] ?? null,
        );

        $invoice->refresh();

        if ($paymentData['method'] === 'wallet' && $invoice->customer_id) {
            $walletKey  = ($paymentData['idempotency_key'] ?? ($invoice->idempotency_key . '_wallet'));
            $vehicleId  = $invoice->vehicle_id;
            $traceIdStr = (string) $traceId;

            if ($vehicleId && $invoice->customer_type === 'b2b') {
                $this->walletService->debitVehicleForInvoice(
                    companyId:      $invoice->company_id,
                    customerId:     $invoice->customer_id,
                    vehicleId:      $vehicleId,
                    amount:         (float) $payment->amount,
                    invoiceId:      $invoice->id,
                    paymentId:      $payment->id,
                    userId:         $userId,
                    traceId:        $traceIdStr,
                    idempotencyKey: $walletKey,
                    branchId:       $invoice->branch_id,
                    notes:          null,
                    paymentMode:    'prepaid',
                );
            } else {
                $this->walletService->debitIndividualForInvoice(
                    companyId:      $invoice->company_id,
                    customerId:     $invoice->customer_id,
                    vehicleId:      $vehicleId,
                    amount:         (float) $payment->amount,
                    invoiceId:      $invoice->id,
                    paymentId:      $payment->id,
                    userId:         $userId,
                    traceId:        $traceIdStr,
                    idempotencyKey: $walletKey,
                    branchId:       $invoice->branch_id,
                    notes:          null,
                    paymentMode:    'prepaid',
                );
            }
        }

        return $payment;
    }

    /**
     * Same rule as POS: only physical (or other) products with inventory tracking deduct stock.
     * Services and lines without {@see Product::track_inventory} must not hit {@see InventoryService::deductStock}.
     *
     * @return array<int, true>
     */
    private function trackedProductIdsForInvoice(array $items, int $companyId): array
    {
        $productIds = [];
        foreach ($items as $item) {
            if (! empty($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }
        }
        $productIds = array_values(array_unique(array_filter($productIds, fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->where('track_inventory', true)
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array<int, true>  $trackedProductIds
     */
    private function deductInventoryForItems(
        array $items,
        array $trackedProductIds,
        int $companyId,
        int $branchId,
        int $userId,
        Invoice $invoice,
        string $traceId,
    ): void {
        foreach ($items as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($productId <= 0 || ! isset($trackedProductIds[$productId])) {
                continue;
            }

            $this->inventoryService->deductStock(
                companyId:     $companyId,
                branchId:      $branchId,
                productId:     $productId,
                quantity:      (float) $item['quantity'],
                userId:        $userId,
                referenceType: Invoice::class,
                referenceId:   $invoice->id,
                traceId:       $traceId,
                unitCost:      $item['cost_price'] ?? null,
            );
        }
    }
}
