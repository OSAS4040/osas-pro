<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\WalletType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controlled API-style financial scenarios (no UI). Uses existing services only.
 */
class TestFinancialFlows extends Command
{
    protected $signature = 'test:financial-flows {--email=admin@osas.sa : User email to run as}';

    protected $description = 'Run controlled financial test flows (wallet, invoice, idempotency, refund, fleet)';

    public function handle(
        WalletService $walletService,
        PaymentService $paymentService,
        InvoiceService $invoiceService,
    ): int {
        $email = (string) $this->option('email');
        $user  = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        $this->scenarioHeader('SETUP');
        $this->line("Running as: {$user->email} (company_id={$user->company_id})");
        $this->newLine();

        $companyId = (int) $user->company_id;
        $branchId  = (int) $user->branch_id;

        $this->runScenario('SCENARIO 1 — Wallet Top-up', function () use ($walletService, $user, $companyId, $branchId) {
            $this->bindTenant($user);
            $customer = $this->makeCustomer($companyId, $branchId, 'S1-'.Str::random(4));

            $walletBefore = $this->customerMainBalance($companyId, $customer->id);
            $idem         = 'flow-s1-topup-'.Str::uuid()->toString();
            $traceId      = (string) Str::uuid();

            $txn = $walletService->topUpIndividual(
                companyId:      $companyId,
                customerId:     $customer->id,
                vehicleId:      null,
                amount:         100.00,
                invoiceId:      null,
                paymentId:      null,
                userId:         $user->id,
                traceId:        $traceId,
                idempotencyKey: $idem,
                branchId:       $branchId,
                notes:          'test:financial-flows scenario 1',
            );

            $walletAfter = $this->customerMainBalance($companyId, $customer->id);

            $this->line('  customer_id: '.$customer->id);
            $this->line('  wallet balance before: '.($walletBefore ?? 'null (no wallet yet)'));
            $this->line('  wallet balance after:  '.$walletAfter);
            $this->line('  transaction id: '.$txn->id);

            return ['customer' => $customer];
        });

        $c1 = $GLOBALS['__financial_flows_s1_customer'] ?? null;

        $this->runScenario('SCENARIO 2 — Invoice + Cash Payment', function () use ($invoiceService, $user, $companyId, $branchId, $c1, $walletService, $paymentService) {
            $this->bindTenant($user);
            $customer = $c1 ?? $this->makeCustomer($companyId, $branchId, 'S2-'.Str::random(4));

            $invoice = $invoiceService->createInvoice(
                data: [
                    'customer_id'   => $customer->id,
                    'customer_type' => 'b2c',
                    'items'         => [
                        ['name' => 'Flow Test Line', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
                    ],
                    'idempotency_key' => 'flow-s2-inv-'.Str::uuid()->toString(),
                ],
                companyId: $companyId,
                branchId:  $branchId,
                userId:    $user->id,
            );

            $this->payInvoice(
                $invoice,
                ['amount' => 50, 'method' => 'cash'],
                $user,
                $walletService,
                $paymentService,
            );

            $invoice->refresh();

            $this->line('  invoice id: '.$invoice->id);
            $this->line('  status: '.$invoice->status->value);
            $this->line('  paid_amount: '.$invoice->paid_amount);
            $this->line('  due_amount: '.$invoice->due_amount);

            return ['customer' => $customer];
        });

        $this->runScenario('SCENARIO 3 — Invoice + Wallet Payment', function () use ($invoiceService, $user, $companyId, $branchId, $walletService, $paymentService) {
            $this->bindTenant($user);
            $customer = $GLOBALS['__financial_flows_s1_customer']
                ?? $this->makeCustomer($companyId, $branchId, 'S3-'.Str::random(4));

            $invoice = $invoiceService->createInvoice(
                data: [
                    'customer_id'   => $customer->id,
                    'customer_type' => 'b2c',
                    'items'         => [
                        ['name' => 'Flow Wallet Line', 'quantity' => 1, 'unit_price' => 30, 'tax_rate' => 0],
                    ],
                    'idempotency_key' => 'flow-s3-inv-'.Str::uuid()->toString(),
                ],
                companyId: $companyId,
                branchId:  $branchId,
                userId:    $user->id,
            );

            $walletKey = 'flow-s3-wallet-'.Str::uuid()->toString();
            $this->payInvoice(
                $invoice,
                [
                    'amount'                 => 30,
                    'method'                 => 'wallet',
                    'wallet_idempotency_key' => $walletKey,
                ],
                $user,
                $walletService,
                $paymentService,
            );

            $invoice->refresh();
            $bal = $this->customerMainBalance($companyId, $customer->id);

            $this->line('  invoice id: '.$invoice->id);
            $this->line('  wallet balance after: '.$bal);
            $this->line('  invoice status: '.$invoice->status->value);

            $pay = Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->orderByDesc('id')->first();

            return ['wallet_payment_id' => $pay?->id, 'customer' => $customer];
        });

        $this->runScenario('SCENARIO 4 — Idempotency (HTTP)', function () use ($invoiceService, $user, $companyId, $branchId) {
            $this->bindTenant($user);
            $customer = $GLOBALS['__financial_flows_s1_customer']
                ?? $this->makeCustomer($companyId, $branchId, 'S4-'.Str::random(4));

            $invoice = $invoiceService->createInvoice(
                data: [
                    'customer_id'   => $customer->id,
                    'customer_type' => 'b2c',
                    'items'         => [
                        ['name' => 'Idem Line', 'quantity' => 1, 'unit_price' => 40, 'tax_rate' => 0],
                    ],
                    'idempotency_key' => 'flow-s4-inv-'.Str::uuid()->toString(),
                ],
                companyId: $companyId,
                branchId:  $branchId,
                userId:    $user->id,
            );

            $idemKey = 'idem-flow-'.Str::uuid()->toString();
            $token   = $user->createToken('financial-flows')->plainTextToken;

            $payload = [
                'amount'    => 40,
                'method'    => 'cash',
                'reference' => 'idem-test',
            ];

            // Hit nginx from inside Docker (CLI has no web route table match for Request::create).
            $base = env('FINANCIAL_FLOW_API_BASE', 'http://nginx');
            $url  = $base.'/api/v1/invoices/'.$invoice->id.'/pay';

            $client = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['Idempotency-Key' => $idemKey]);

            $res1 = $client->post($url, $payload);
            $status1  = $res1->status();
            $content1 = $res1->body();

            $countAfterFirst = Payment::where('invoice_id', $invoice->id)->count();

            $res2 = $client->post($url, $payload);
            $status2  = $res2->status();
            $content2 = $res2->body();

            $countAfterSecond = Payment::where('invoice_id', $invoice->id)->count();

            $this->line('  API base: '.$base);
            $this->line('  first response status: '.$status1);
            $this->line('  second response status: '.$status2);
            $this->line('  bodies match: '.($content1 === $content2 ? 'yes' : 'no'));
            $this->line('  payment rows for invoice: '.$countAfterSecond.' (expect 1)');
            if ($status1 >= 400 || $status2 >= 400) {
                throw new \RuntimeException('HTTP error: '.$content1.' | '.$content2);
            }
            if ($countAfterSecond !== 1) {
                throw new \RuntimeException('Expected exactly one payment for idempotent replay');
            }
        });

        $this->runScenario('SCENARIO 5 — Refund', function () use ($paymentService, $user, $companyId) {
            $this->bindTenant($user);
            $pid = $GLOBALS['__financial_flows_wallet_payment_id'] ?? null;
            if (! $pid) {
                throw new \RuntimeException('No wallet payment id from scenario 3 — cannot refund');
            }

            $payment = Payment::findOrFail($pid);
            $customerId = Invoice::find($payment->invoice_id)?->customer_id;

            $idem = 'flow-refund-'.Str::uuid()->toString();
            $traceId = (string) Str::uuid();

            $refund = $paymentService->refund(
                paymentId:      (int) $pid,
                userId:         $user->id,
                traceId:        $traceId,
                idempotencyKey: $idem,
                amount:         null,
                reason:         'test:financial-flows scenario 5',
            );

            $inv = Invoice::find($payment->invoice_id);
            $inv?->refresh();

            $walletAfter = $customerId
                ? $this->customerMainBalance($companyId, (int) $customerId)
                : null;

            $this->line('  refund payment id: '.$refund->id);
            $this->line('  original payment id: '.$pid);
            $this->line('  invoice status: '.($inv ? $inv->status->value : 'n/a'));
            $this->line('  customer_main wallet balance after refund: '.($walletAfter ?? 'n/a'));
        });

        $this->runScenario('SCENARIO 6 — Fleet + Vehicle', function () use ($walletService, $user, $companyId, $branchId, $invoiceService, $paymentService) {
            $this->bindTenant($user);
            $customer = $this->makeCustomer($companyId, $branchId, 'S6-'.Str::random(4));
            $vehicle  = Vehicle::create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'customer_id'        => $customer->id,
                'created_by_user_id' => $user->id,
                'plate_number'       => 'FF-'.rand(1000, 9999),
                'make'               => 'Test',
                'model'              => 'Fleet',
                'year'               => 2024,
                'is_active'          => true,
            ]);

            $trace = (string) Str::uuid();
            $walletService->topUpFleet(
                companyId:      $companyId,
                customerId:     $customer->id,
                vehicleId:      null,
                amount:         5000.00,
                invoiceId:      null,
                paymentId:      null,
                userId:         $user->id,
                traceId:        $trace,
                idempotencyKey: 'flow-s6-fleet-'.Str::uuid()->toString(),
                branchId:       $branchId,
                notes:          null,
            );

            $walletService->transferToVehicle(
                companyId:      $companyId,
                customerId:     $customer->id,
                vehicleId:      $vehicle->id,
                amount:         800.00,
                invoiceId:      null,
                paymentId:      null,
                userId:         $user->id,
                traceId:        (string) Str::uuid(),
                idempotencyKey: 'flow-s6-xfer-'.Str::uuid()->toString(),
                branchId:       $branchId,
                notes:          null,
            );

            $invoice = $invoiceService->createInvoice(
                data: [
                    'customer_id'   => $customer->id,
                    'vehicle_id'    => $vehicle->id,
                    'customer_type' => 'b2b',
                    'items'         => [
                        ['name' => 'Fleet vehicle service', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
                    ],
                    'idempotency_key' => 'flow-s6-inv-'.Str::uuid()->toString(),
                ],
                companyId: $companyId,
                branchId:  $branchId,
                userId:    $user->id,
            );

            $this->payInvoice(
                $invoice,
                [
                    'amount'                 => 50,
                    'method'                 => 'wallet',
                    'wallet_idempotency_key' => 'flow-s6-wpay-'.Str::uuid()->toString(),
                ],
                $user,
                $walletService,
                $paymentService,
            );

            $fleetBal   = $this->walletBalanceByType($companyId, $customer->id, WalletType::FleetMain);
            $vehicleBal = $this->vehicleWalletBalance($companyId, $customer->id, $vehicle->id);

            $this->line('  fleet_main balance: '.$fleetBal);
            $this->line('  vehicle_wallet balance: '.$vehicleBal);
        });

        $this->newLine();
        $this->info('All scenarios finished.');

        return self::SUCCESS;
    }

    private function scenarioHeader(string $title): void
    {
        $this->line(str_repeat('=', 60));
        $this->line($title);
        $this->line(str_repeat('=', 60));
    }

    /**
     * @param  callable(): mixed  $fn
     */
    private function runScenario(string $name, callable $fn): void
    {
        $this->newLine();
        $this->scenarioHeader($name);
        $this->line('Status: running...');
        try {
            $result = $fn();
            if (is_array($result)) {
                if (isset($result['customer'])) {
                    $GLOBALS['__financial_flows_s1_customer'] = $result['customer'];
                }
                if (array_key_exists('wallet_payment_id', $result)) {
                    $GLOBALS['__financial_flows_wallet_payment_id'] = $result['wallet_payment_id'];
                }
            }
            $this->line('Status: SUCCESS');
        } catch (\Throwable $e) {
            $this->error('Status: FAIL');
            $this->error('  '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
        }
    }

    private function bindTenant(User $user): void
    {
        Auth::login($user);
        app()->instance('tenant_company_id', $user->company_id);
        app()->instance('tenant_branch_id', $user->branch_id);
        app()->instance('trace_id', (string) Str::uuid());
    }

    private function makeCustomer(int $companyId, int $branchId, string $suffix): Customer
    {
        return Customer::create([
            'uuid'        => (string) Str::uuid(),
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'name'        => 'Financial Flow '.$suffix,
            'type'        => 'individual',
            'email'       => 'flow-'.Str::lower($suffix).'@test.local',
            'is_active'   => true,
        ]);
    }

    private function customerMainBalance(int $companyId, int $customerId): ?string
    {
        $w = CustomerWallet::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('wallet_type', WalletType::CustomerMain)
            ->first();

        return $w ? (string) $w->balance : null;
    }

    private function walletBalanceByType(int $companyId, int $customerId, WalletType $type): string
    {
        $w = CustomerWallet::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('wallet_type', $type)
            ->first();

        return $w ? (string) $w->fresh()->balance : '0';
    }

    private function vehicleWalletBalance(int $companyId, int $customerId, int $vehicleId): string
    {
        $w = CustomerWallet::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('vehicle_id', $vehicleId)
            ->where('wallet_type', WalletType::VehicleWallet)
            ->first();

        return $w ? (string) $w->fresh()->balance : '0';
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
                reference: $data['reference'] ?? null,
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
