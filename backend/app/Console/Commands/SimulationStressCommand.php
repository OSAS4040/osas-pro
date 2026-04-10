<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Services\WorkOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Realistic load simulation: customers, vehicles, wallet top-ups, mixed invoices,
 * cash + wallet payments, inventory deductions, and periodic work-order → invoice flows.
 */
class SimulationStressCommand extends Command
{
    protected $signature = 'simulation:stress
                            {--email=simulation.owner@demo.local : Owner user email (tenant context)}
                            {--customers=35 : Customers per batch (20–50 typical)}
                            {--batches=1 : Repeat the full customer loop this many times (pressure)}
                            {--wo-every=6 : Every Nth customer (1-based), run work order → invoice flow}
                            {--skip-wo : Do not create work orders}
                            {--skip-product-lines : No product lines (no stock deduction)}';

    protected $description = 'Simulate realistic operations: wallets, invoices, payments, inventory, work orders';

    private int $statCustomers = 0;

    private int $statVehicles = 0;

    private int $statTopUps = 0;

    private int $statInvoices = 0;

    private int $statPayments = 0;

    private int $statWorkOrders = 0;

    private array $errors = [];

    public function handle(
        WalletService $walletService,
        PaymentService $paymentService,
        InvoiceService $invoiceService,
        InventoryService $inventoryService,
        WorkOrderService $workOrderService,
    ): int {
        $email = (string) $this->option('email');
        $user  = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        $companyId = (int) $user->company_id;
        $branchId  = (int) $user->branch_id;

        $customersPerBatch = max(1, min(200, (int) $this->option('customers')));
        $batches           = max(1, min(50, (int) $this->option('batches')));
        $woEvery           = max(1, (int) $this->option('wo-every'));
        $skipWo            = (bool) $this->option('skip-wo');
        $skipProduct       = (bool) $this->option('skip-product-lines');

        $this->info(sprintf(
            'simulation:stress — company_id=%d branch_id=%d user=%s | customers/batch=%d batches=%d',
            $companyId,
            $branchId,
            $user->email,
            $customersPerBatch,
            $batches,
        ));

        $productAndUnit = $this->ensureProductAndStock($companyId, $branchId, $user->id, $inventoryService, $skipProduct);

        for ($b = 0; $b < $batches; $b++) {
            if ($batches > 1) {
                $this->line('--- batch '.($b + 1).'/'.$batches.' ---');
            }
            for ($i = 0; $i < $customersPerBatch; $i++) {
                $globalIndex = $b * $customersPerBatch + $i + 1;
                try {
                    $this->bindTenant($user);
                    $suffix = 'SIM-'.$b.'-'.$i.'-'.Str::lower(Str::random(4));

                    $customer = Customer::create([
                        'uuid'       => (string) Str::uuid(),
                        'company_id' => $companyId,
                        'branch_id'  => $branchId,
                        'name'       => 'Sim Customer '.$globalIndex,
                        'type'       => 'b2c',
                        'email'      => 'sim-'.$globalIndex.'-'.Str::lower(Str::random(6)).'@demo.local',
                        'phone'      => '+9665'.sprintf('%08d', min(99999999, 10000000 + $globalIndex)),
                        'is_active'  => true,
                    ]);
                    $this->statCustomers++;

                    $vehicle = Vehicle::create([
                        'uuid'               => (string) Str::uuid(),
                        'company_id'         => $companyId,
                        'branch_id'          => $branchId,
                        'customer_id'        => $customer->id,
                        'created_by_user_id' => $user->id,
                        'plate_number'       => sprintf('SIM-%04d', ($globalIndex % 10000)),
                        'make'               => ['Toyota', 'Hyundai', 'Nissan', 'Kia'][$globalIndex % 4],
                        'model'              => 'Model-'.($globalIndex % 9 + 1),
                        'year'               => 2019 + ($globalIndex % 6),
                        'is_active'          => true,
                    ]);
                    $this->statVehicles++;

                    $topUpAmount = round(random_int(15_000, 80_000) / 100, 2);
                    $walletService->topUpIndividual(
                        companyId:      $companyId,
                        customerId:     $customer->id,
                        vehicleId:      null,
                        amount:         $topUpAmount,
                        invoiceId:      null,
                        paymentId:      null,
                        userId:         $user->id,
                        traceId:        (string) Str::uuid(),
                        idempotencyKey: 'sim-topup-'.Str::uuid()->toString(),
                        branchId:       $branchId,
                        notes:          'simulation:stress top-up',
                    );
                    $this->statTopUps++;

                    // 1) Service-only invoice → cash
                    $invService = $invoiceService->createInvoice(
                        data: [
                            'customer_id'     => $customer->id,
                            'vehicle_id'      => $vehicle->id,
                            'customer_type'   => 'b2c',
                            'items'           => [
                                [
                                    'name'       => 'Service / labor (sim)',
                                    'quantity'   => 1,
                                    'unit_price' => round(random_int(8_000, 35_000) / 100, 2),
                                    'tax_rate'   => 15,
                                ],
                            ],
                            'idempotency_key' => 'sim-inv-svc-'.Str::uuid()->toString(),
                        ],
                        companyId: $companyId,
                        branchId:  $branchId,
                        userId:    $user->id,
                    );
                    $this->statInvoices++;
                    $invService->refresh();
                    $this->payInvoice(
                        $invService,
                        ['amount' => (float) $invService->due_amount, 'method' => 'cash'],
                        $user,
                        $walletService,
                        $paymentService,
                    );
                    $this->statPayments++;

                    // 2) Optional product line → wallet (stock deduction when product exists)
                    if (! $skipProduct && $productAndUnit !== null) {
                        ['product' => $product] = $productAndUnit;
                        $qty       = random_int(1, 3);
                        $unitPrice = round(random_int(1_200, 9_000) / 100, 2);

                        $invProd = $invoiceService->createInvoice(
                            data: [
                                'customer_id'     => $customer->id,
                                'vehicle_id'      => $vehicle->id,
                                'customer_type'   => 'b2c',
                                'items'           => [
                                    [
                                        'name'        => $product->name.' (sim)',
                                        'product_id'  => $product->id,
                                        'quantity'    => $qty,
                                        'unit_price'  => $unitPrice,
                                        'tax_rate'    => 15,
                                    ],
                                ],
                                'idempotency_key' => 'sim-inv-prd-'.Str::uuid()->toString(),
                            ],
                            companyId: $companyId,
                            branchId:  $branchId,
                            userId:    $user->id,
                        );
                        $this->statInvoices++;
                        $invProd->refresh();
                        $need = (float) $invProd->due_amount + 5;
                        $walletService->topUpIndividual(
                            companyId:      $companyId,
                            customerId:     $customer->id,
                            vehicleId:      null,
                            amount:         round($need, 2),
                            invoiceId:      null,
                            paymentId:      null,
                            userId:         $user->id,
                            traceId:        (string) Str::uuid(),
                            idempotencyKey: 'sim-topup2-'.Str::uuid()->toString(),
                            branchId:       $branchId,
                            notes:          'simulation:stress (product line)',
                        );
                        $this->statTopUps++;
                        $invProd->refresh();
                        $this->payInvoice(
                            $invProd,
                            [
                                'amount'                 => (float) $invProd->due_amount,
                                'method'                 => 'wallet',
                                'wallet_idempotency_key' => 'sim-wpay-'.Str::uuid()->toString(),
                            ],
                            $user,
                            $walletService,
                            $paymentService,
                        );
                        $this->statPayments++;
                    }

                    // 3) Work order → completed → invoice from WO → pay
                    if (! $skipWo && ($globalIndex % $woEvery) === 0) {
                        $order = $workOrderService->create(
                            [
                                'customer_id' => $customer->id,
                                'vehicle_id'  => $vehicle->id,
                                'items'       => [
                                    [
                                        'item_type' => 'labor',
                                        'name'      => 'WO Labor (sim)',
                                        'quantity'  => 1,
                                        'unit_price'=> round(random_int(12_000, 55_000) / 100, 2),
                                        'tax_rate'  => 15,
                                    ],
                                ],
                            ],
                            $companyId,
                            $branchId,
                            $user->id,
                        );
                        $this->statWorkOrders++;
                        $order->refresh();
                        $workOrderService->transition($order, WorkOrderStatus::InProgress);
                        $order->refresh();
                        $workOrderService->transition($order, WorkOrderStatus::Completed, [
                            'technician_notes' => 'Simulation completed',
                            'mileage_out'      => 40000 + $globalIndex,
                        ]);
                        $order->refresh();

                        $invWo = $invoiceService->issueFromWorkOrder(
                            $order,
                            $user->id,
                        );
                        $this->statInvoices++;
                        $invWo->refresh();
                        $this->payInvoice(
                            $invWo,
                            ['amount' => (float) $invWo->due_amount, 'method' => 'cash'],
                            $user,
                            $walletService,
                            $paymentService,
                        );
                        $this->statPayments++;
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = 'customer_index '.$globalIndex.': '.$e->getMessage();
                    if ($this->output->isVerbose()) {
                        $this->error($e->getTraceAsString());
                    }
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['customers_created', $this->statCustomers],
                ['vehicles_created', $this->statVehicles],
                ['wallet_top_ups', $this->statTopUps],
                ['invoices_created', $this->statInvoices],
                ['payments_recorded', $this->statPayments],
                ['work_orders', $this->statWorkOrders],
            ],
        );

        if ($this->errors !== []) {
            $this->warn('Completed with '.count($this->errors).' error(s):');
            foreach (array_slice($this->errors, 0, 20) as $line) {
                $this->line('  '.$line);
            }
            if (count($this->errors) > 20) {
                $this->line('  ... ('.count($this->errors).' total)');
            }

            return self::FAILURE;
        }

        $this->info('simulation:stress finished successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array{product: Product, unit: Unit}|null
     */
    private function ensureProductAndStock(
        int $companyId,
        int $branchId,
        int $userId,
        InventoryService $inventoryService,
        bool $skipProduct,
    ): ?array {
        if ($skipProduct) {
            $this->warn('Skipping product lines and stock setup (--skip-product-lines).');

            return null;
        }

        $unit = Unit::firstOrCreate(
            [
                'company_id' => $companyId,
                'symbol'     => 'SIM-PCS',
            ],
            [
                'name'      => 'Simulation Piece',
                'type'      => 'quantity',
                'is_base'   => true,
                'is_system' => false,
                'is_active' => true,
            ],
        );

        $product = Product::firstOrCreate(
            [
                'company_id' => $companyId,
                'sku'        => 'SIM-STOCK-001',
            ],
            [
                'uuid'              => (string) Str::uuid(),
                'created_by_user_id'=> $userId,
                'name'              => 'Simulation Stock SKU',
                'product_type'      => ProductType::Physical,
                'unit_id'           => $unit->id,
                'sale_price'        => 25.00,
                'cost_price'        => 12.00,
                'track_inventory'   => true,
                'is_active'         => true,
            ],
        );

        $inventoryService->addStock(
            companyId: $companyId,
            branchId:  $branchId,
            productId: $product->id,
            quantity:  50_000,
            userId:    $userId,
            type:      StockMovementType::ManualAdd->value,
            traceId:   'simulation-stress-seed-'.Str::uuid()->toString(),
            unitId:    $unit->id,
            note:      'simulation:stress initial stock',
        );

        $this->line(sprintf('Stock baseline: product_id=%d sku=%s (+50000)', $product->id, $product->sku));

        return ['product' => $product, 'unit' => $unit];
    }

    private function bindTenant(User $user): void
    {
        Auth::login($user);
        app()->instance('tenant_company_id', $user->company_id);
        app()->instance('tenant_branch_id', $user->branch_id);
        app()->instance('trace_id', (string) Str::uuid());
    }

    private function payInvoice(
        Invoice $invoice,
        array $data,
        User $user,
        WalletService $walletService,
        PaymentService $paymentService,
    ): void {
        DB::transaction(function () use ($invoice, $data, $user, $walletService, $paymentService) {
            $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($invoice->status, [
                InvoiceStatus::Paid,
                InvoiceStatus::Cancelled,
                InvoiceStatus::Refunded,
                InvoiceStatus::Draft,
            ], true)) {
                throw new \DomainException('This invoice cannot accept a payment in its current status.');
            }

            if ($data['method'] === 'wallet' && ! $invoice->customer_id) {
                throw new \DomainException('Wallet payment requires an invoice customer.');
            }

            $traceId = trim((string) (app('trace_id') ?? '')) ?: Str::uuid()->toString();

            $payment = $paymentService->createPayment(
                invoice:   $invoice,
                amount:    (float) $data['amount'],
                method:    $data['method'],
                userId:    $user->id,
                traceId:   $traceId,
                branchId:  $invoice->branch_id,
                reference: $data['reference'] ?? 'sim-stress',
            );

            $invoice->refresh();

            if ($data['method'] === 'wallet' && $invoice->customer_id) {
                $walletKey = $data['wallet_idempotency_key']
                    ?? ($invoice->idempotency_key !== null && $invoice->idempotency_key !== ''
                        ? $invoice->idempotency_key.'_wallet'
                        : null);
                if ($walletKey === null || $walletKey === '') {
                    throw new \DomainException('wallet_idempotency_key is required when method is wallet.');
                }

                $vehicleId = $invoice->vehicle_id;

                if ($vehicleId && $invoice->customer_type === 'b2b') {
                    $walletService->debitVehicleForInvoice(
                        companyId:      $invoice->company_id,
                        customerId:     $invoice->customer_id,
                        vehicleId:      $vehicleId,
                        amount:         (float) $payment->amount,
                        invoiceId:      $invoice->id,
                        paymentId:      $payment->id,
                        userId:         $user->id,
                        traceId:        $traceId,
                        idempotencyKey: $walletKey,
                        branchId:       $invoice->branch_id,
                        notes:          null,
                        paymentMode:    'prepaid',
                    );
                } else {
                    $walletService->debitIndividualForInvoice(
                        companyId:      $invoice->company_id,
                        customerId:     $invoice->customer_id,
                        vehicleId:      $vehicleId,
                        amount:         (float) $payment->amount,
                        invoiceId:      $invoice->id,
                        paymentId:      $payment->id,
                        userId:         $user->id,
                        traceId:        $traceId,
                        idempotencyKey: $walletKey,
                        branchId:       $invoice->branch_id,
                        notes:          null,
                        paymentMode:    'prepaid',
                    );
                }
            }
        });
    }
}
