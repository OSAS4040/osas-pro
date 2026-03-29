<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Accounting\FinancialGlMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * POSService handles the B2C fast-track atomic flow:
 *   create invoice → record payment → deduct stock → wallet debit → log
 *
 * All steps run inside a single DB transaction.
 * Target: complete within 1.5 seconds end-to-end.
 */
class POSService
{
    public function __construct(
        private readonly InventoryService  $inventoryService,
        private readonly WalletService     $walletService,
        private readonly PaymentService    $paymentService,
        private readonly IdempotencyService $idempotency,
        private readonly LedgerService     $ledger,
    ) {}

    /**
     * Execute a POS fast-track sale.
     *
     * @throws \DomainException  on idempotency payload mismatch
     * @throws \DomainException  on insufficient stock
     */
    public function sale(
        array  $data,
        int    $companyId,
        int    $branchId,
        int    $userId,
        string $idempotencyKey,
    ): Invoice {
        $traceId = trim((string) (app('trace_id') ?? '')) ?: Str::uuid()->toString();

        $invoice = DB::transaction(function () use (
            $data, $companyId, $branchId, $userId, $traceId
        ) {
            $previousInvoice = Invoice::where('company_id', $companyId)
                ->orderByDesc('invoice_counter')
                ->lockForUpdate()
                ->first();

            // Use MAX of both invoice_counter and extract from invoice_number to avoid gaps
            $maxByCounter = Invoice::where('company_id', $companyId)
                ->whereNotNull('invoice_counter')
                ->max('invoice_counter') ?? 0;

            // Also extract max from invoice_number pattern INV-{company_id}-{counter}
            $maxByPattern = (int) Invoice::where('company_id', $companyId)
                ->where('invoice_number', 'LIKE', "INV-{$companyId}-%")
                ->selectRaw("MAX(CAST(SPLIT_PART(invoice_number, '-', 3) AS INTEGER)) as max_num")
                ->value('max_num') ?? 0;

            $counter       = max($maxByCounter, $maxByPattern) + 1;
            $invoiceNumber = sprintf('INV-%d-%06d', $companyId, $counter);
            $previousHash  = $previousInvoice?->invoice_hash ?? hash('sha256', 'genesis');

            [$subtotal, $taxAmount, $itemsData] = $this->buildItems($data['items'], $companyId);

            $discount   = (float) ($data['discount_amount'] ?? 0);
            $total      = round($subtotal + $taxAmount - $discount, 4);
            $invoiceHash = hash('sha256', $invoiceNumber . number_format($total, 4, '.', '') . $previousHash);

            $invoice = Invoice::create([
                'uuid'                  => Str::uuid(),
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'customer_id'           => $data['customer_id'] ?? null,
                'vehicle_id'            => $data['vehicle_id'] ?? null,
                'created_by_user_id'    => $userId,
                'invoice_number'        => $invoiceNumber,
                'type'                  => 'sale',
                'source_type'           => $data['source_type'] ?? 'pos',
                'source_id'             => $data['source_id'] ?? null,
                'status'                => InvoiceStatus::Pending,
                'customer_type'         => $data['customer_type'] ?? 'b2c',
                'subtotal'              => $subtotal,
                'discount_amount'       => $discount,
                'tax_amount'            => $taxAmount,
                'total'                 => $total,
                'paid_amount'           => 0,
                'due_amount'            => $total,
                'currency'              => 'SAR',
                'invoice_hash'          => $invoiceHash,
                'previous_invoice_hash' => $previousHash,
                'invoice_counter'       => $counter,
                'trace_id'              => $traceId,
                'notes'                 => $data['notes'] ?? null,
                'issued_at'             => now(),
            ]);

            foreach ($itemsData as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            $invoice->refresh();
            $payment = $this->paymentService->createPayment(
                invoice:   $invoice,
                amount:    (float) $data['payment']['amount'],
                method:    $data['payment']['method'],
                userId:    $userId,
                traceId:   $traceId,
                branchId:  $branchId,
                reference: $data['payment']['reference'] ?? null,
            );
            $invoice->refresh();

            $this->deductInventory($data['items'], $companyId, $branchId, $userId, $invoice, $traceId);

            if ($data['payment']['method'] === 'wallet' && $invoice->customer_id) {
                $walletIdempotencyKey = $data['idempotency_key'] . '_wallet_debit';
                $vehicleId            = $data['vehicle_id'] ?? null;
                $customerType         = $data['customer_type'] ?? 'b2c';

                if ($vehicleId && $customerType === 'b2b') {
                    $this->walletService->debitVehicleForInvoice(
                        companyId:      $companyId,
                        customerId:     $invoice->customer_id,
                        vehicleId:      $vehicleId,
                        amount:         (float) $payment->amount,
                        invoiceId:      $invoice->id,
                        paymentId:      $payment->id,
                        userId:         $userId,
                        traceId:        $traceId,
                        idempotencyKey: $walletIdempotencyKey,
                        branchId:       $branchId,
                        notes:          null,
                        paymentMode:    'prepaid',
                    );
                } else {
                    $this->walletService->debitIndividualForInvoice(
                        companyId:      $companyId,
                        customerId:     $invoice->customer_id,
                        vehicleId:      $vehicleId,
                        amount:         (float) $payment->amount,
                        invoiceId:      $invoice->id,
                        paymentId:      $payment->id,
                        userId:         $userId,
                        traceId:        $traceId,
                        idempotencyKey: $walletIdempotencyKey,
                        branchId:       $branchId,
                        notes:          null,
                        paymentMode:    'prepaid',
                    );
                }
            }

            Log::info('pos.sale.completed', [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total'          => $total,
                'company_id'     => $companyId,
                'trace_id'       => $traceId,
            ]);

            $this->postPosLedger($invoice, $traceId);

            return $invoice->load(['items', 'payments']);
        });

        return $invoice;
    }

    private function postPosLedger(Invoice $invoice, string $traceId): void
    {
        try {
            $lines = FinancialGlMapping::linesForPosSale($invoice);

            $this->ledger->post(
                companyId: $invoice->company_id,
                data: [
                    'type'        => JournalEntryType::Sale->value,
                    'description' => "POS Sale {$invoice->invoice_number}",
                    'source_type' => Invoice::class,
                    'source_id'   => $invoice->id,
                    'entry_date'  => now()->toDateString(),
                    'lines'       => $lines,
                    'trace_id'    => $traceId,
                ],
                branchId: $invoice->branch_id,
                userId:   $invoice->created_by_user_id,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('pos.ledger.post.failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
                'trace_id'   => $traceId,
            ]);
        }
    }

    private function buildItems(array $items, int $companyId): array
    {
        $subtotal  = 0;
        $taxAmount = 0;
        $itemsData = [];

        foreach ($items as $item) {
            $lineSubtotal = round((float)$item['quantity'] * (float)$item['unit_price'] - (float)($item['discount_amount'] ?? 0), 4);
            $lineTax      = round($lineSubtotal * ((float)($item['tax_rate'] ?? 15) / 100), 4);
            $lineTotal    = $lineSubtotal + $lineTax;

            $subtotal  += $lineSubtotal;
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
                'subtotal'        => $lineSubtotal,
                'total'           => $lineTotal,
                'line_total'      => $lineTotal,
            ];
        }

        return [$subtotal, $taxAmount, $itemsData];
    }

    private function deductInventory(
        array   $items,
        int     $companyId,
        int     $branchId,
        int     $userId,
        Invoice $invoice,
        string  $traceId,
    ): void {
        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            // Skip inventory deduction for products that don't track inventory
            $product = \App\Models\Product::find($item['product_id']);
            if (!$product || !$product->track_inventory) {
                continue;
            }

            $this->inventoryService->deductStock(
                companyId:     $companyId,
                branchId:      $branchId,
                productId:     (int) $item['product_id'],
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
