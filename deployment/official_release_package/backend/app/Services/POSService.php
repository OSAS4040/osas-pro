<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Exceptions\LedgerPostingFailedException;
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
 * Critical write path runs inside a single DB transaction, including ledger posting.
 * If the journal entry cannot be posted, the entire sale rolls back (no orphan invoice).
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
        $wallStart = microtime(true);
        $seg = [
            'prefetch_ms' => 0.0,
            'tx_total_ms' => 0.0,
            'invoice_number_ms' => 0.0,
            'build_items_ms' => 0.0,
            'invoice_insert_ms' => 0.0,
            'invoice_items_insert_ms' => 0.0,
            'payment_ms' => 0.0,
            'inventory_ms' => 0.0,
            'wallet_ms' => 0.0,
            'ledger_sync_ms' => 0.0,
            'hydrate_ms' => 0.0,
        ];
        $t = microtime(true);
        $trackedProductIds = $this->trackedProductIds($data['items']);
        $seg['prefetch_ms'] = round((microtime(true) - $t) * 1000, 2);

        $tTx = microtime(true);
        $invoice = DB::transaction(function () use (
            $data, $companyId, $branchId, $userId, $traceId, $trackedProductIds, &$seg
        ) {
            $ti = microtime(true);
            $counter = $this->allocateInvoiceCounter($companyId);
            $invoiceNumber = sprintf('INV-%d-%06d', $companyId, $counter);
            $previousInvoice = Invoice::query()
                ->where('company_id', $companyId)
                ->whereNotNull('invoice_hash')
                ->orderByDesc('id')
                ->first();
            $previousHash  = $previousInvoice?->invoice_hash ?? hash('sha256', 'genesis');
            $seg['invoice_number_ms'] = round((microtime(true) - $ti) * 1000, 2);

            $ti = microtime(true);
            [$subtotal, $taxAmount, $itemsData] = $this->buildItems($data['items'], $companyId);
            $seg['build_items_ms'] = round((microtime(true) - $ti) * 1000, 2);

            $discount   = (float) ($data['discount_amount'] ?? 0);
            $total      = round($subtotal + $taxAmount - $discount, 4);
            $invoiceHash = hash('sha256', $invoiceNumber . number_format($total, 4, '.', '') . $previousHash);

            $ti = microtime(true);
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
            $seg['invoice_insert_ms'] = round((microtime(true) - $ti) * 1000, 2);

            $ti = microtime(true);
            foreach ($itemsData as $item) {
                InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }
            $seg['invoice_items_insert_ms'] = round((microtime(true) - $ti) * 1000, 2);

            $ti = microtime(true);
            $payment = $this->paymentService->createPayment(
                invoice:   $invoice,
                amount:    (float) $data['payment']['amount'],
                method:    $data['payment']['method'],
                userId:    $userId,
                traceId:   $traceId,
                branchId:  $branchId,
                reference: $data['payment']['reference'] ?? null,
            );
            $seg['payment_ms'] = round((microtime(true) - $ti) * 1000, 2);

            $ti = microtime(true);
            $this->deductInventory(
                items: $data['items'],
                trackedProductIds: $trackedProductIds,
                companyId: $companyId,
                branchId: $branchId,
                userId: $userId,
                invoice: $invoice,
                traceId: $traceId
            );
            $seg['inventory_ms'] = round((microtime(true) - $ti) * 1000, 2);

            if ($data['payment']['method'] === 'wallet' && $invoice->customer_id) {
                $ti = microtime(true);
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
                $seg['wallet_ms'] = round((microtime(true) - $ti) * 1000, 2);
            }

            $invoice->refresh();
            $tiLedger = microtime(true);
            $this->postPosSaleLedgerOrThrow($invoice, $traceId);
            $seg['ledger_sync_ms'] = round((microtime(true) - $tiLedger) * 1000, 2);

            Log::info('pos.sale.completed', [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total'          => $total,
                'company_id'     => $companyId,
                'trace_id'       => $traceId,
            ]);

            return $invoice;
        });
        $seg['tx_total_ms'] = round((microtime(true) - $tTx) * 1000, 2);

        $ti = microtime(true);
        $invoice = Invoice::query()
            ->with(['items', 'payments'])
            ->findOrFail($invoice->id);
        $seg['hydrate_ms'] = round((microtime(true) - $ti) * 1000, 2);

        $totalMs = round((microtime(true) - $wallStart) * 1000, 2);
        if (filter_var((string) env('POS_HOTPATH_PROFILING', true), FILTER_VALIDATE_BOOL) && $totalMs >= 300) {
            Log::warning('pos.sale.hotpath.profile', [
                'trace_id' => $traceId,
                'invoice_id' => $invoice->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'items_count' => count($data['items']),
                'tracked_items_count' => count($trackedProductIds),
                'payment_method' => (string) ($data['payment']['method'] ?? ''),
                'total_ms' => $totalMs,
            ] + $seg + ['hotpath_over_1s' => $totalMs >= 1000]);
        }

        return $invoice;
    }

    /**
     * Synchronous ledger post inside the POS sale transaction — failure rolls back payment, stock, and invoice.
     */
    private function postPosSaleLedgerOrThrow(Invoice $invoice, string $traceId): void
    {
        try {
            $alreadyPosted = DB::table('journal_entries')
                ->where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->exists();
            if ($alreadyPosted) {
                return;
            }

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
                userId: $invoice->created_by_user_id,
            );
        } catch (LedgerPostingFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LedgerPostingFailedException(
                source: 'pos',
                companyId: (int) $invoice->company_id,
                invoiceId: $invoice->id,
                previous: $e,
            );
        }
    }

    private function allocateInvoiceCounter(int $companyId): int
    {
        $row = DB::selectOne("SELECT nextval('invoice_counter_global_seq') AS next_counter");

        return (int) ($row->next_counter ?? 1);
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
            if ($productId <= 0) {
                continue;
            }
            if (! isset($trackedProductIds[$productId])) {
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

    /**
     * Resolve inventory-tracked products once to keep POS transaction lean.
     *
     * @return array<int, true>
     */
    private function trackedProductIds(array $items): array
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

        return \App\Models\Product::query()
            ->whereIn('id', $productIds)
            ->where('track_inventory', true)
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }
}
