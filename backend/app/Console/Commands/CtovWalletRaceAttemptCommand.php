<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Single wallet top-up attempt for concurrent race tests (shared idempotency key).
 */
class CtovWalletRaceAttemptCommand extends Command
{
    protected $signature = 'ctov:wallet-race-attempt
                            {--email=owner@demo.sa : Tenant user email}
                            {--key= : Shared idempotency key (required)}
                            {--amount=1.00 : Top-up amount}';

    protected $description = 'One topUpIndividual call — used by ctov:operational-report race pool';

    public function handle(WalletService $walletService): int
    {
        $key = trim((string) $this->option('key'));
        if ($key === '') {
            $this->error('--key is required');

            return self::FAILURE;
        }

        $email = (string) $this->option('email');
        $user  = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        Auth::login($user);
        app()->instance('tenant_company_id', $user->company_id);
        app()->instance('tenant_branch_id', $user->branch_id);
        app()->instance('trace_id', (string) Str::uuid());

        $customer = Customer::where('company_id', $user->company_id)->orderBy('id')->first();
        if (! $customer) {
            $this->error('No customer in company — run demo seed first');

            return self::FAILURE;
        }

        $amount = (float) $this->option('amount');

        try {
            $walletService->topUpIndividual(
                companyId:      (int) $user->company_id,
                customerId:     (int) $customer->id,
                vehicleId:      null,
                amount:         $amount,
                invoiceId:      null,
                paymentId:      null,
                userId:         (int) $user->id,
                traceId:        (string) Str::uuid(),
                idempotencyKey: $key,
                branchId:       (int) $user->branch_id,
                notes:          'ctov race attempt',
            );
            $this->line('SUCCESS');

            return self::SUCCESS;
        } catch (\DomainException $e) {
            $this->line('DUPLICATE: '.$e->getMessage());

            return 2;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
