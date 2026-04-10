<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Database\Seeders\DemoOperationsSeeder;
use Illuminate\Console\Command;

/**
 * Prints simulation row counts and optionally pings intelligence + dashboard routes (requires --token or sanctum acting as user in tests).
 */
class SimulationVerifyCommand extends Command
{
    protected $signature = 'simulation:verify {--uri= : Base URL for HTTP checks e.g. http://localhost/api/v1} {--token= : Bearer token for owner user}';

    protected $description = 'Verify DemoOperationsSeeder counts and optional HTTP intelligence + dashboard endpoints';

    public function handle(): int
    {
        $company = \App\Models\Company::where('email', DemoOperationsSeeder::COMPANY_EMAIL)->first();
        if (! $company) {
            $this->error('Simulation company not found. Run: php artisan db:seed --class=DemoOperationsSeeder');

            return self::FAILURE;
        }

        $cid = $company->id;

        $cCust = Customer::withoutGlobalScope('tenant')->where('company_id', $cid)->count();
        $cInv  = Invoice::withoutGlobalScope('tenant')->where('company_id', $cid)->count();
        $cPay  = Payment::withoutGlobalScope('tenant')->where('company_id', $cid)->count();

        $this->table(
            ['metric', 'count'],
            [
                ['customers', (string) $cCust],
                ['invoices', (string) $cInv],
                ['payments', (string) $cPay],
                ['company_id', (string) $cid],
            ]
        );

        $ok = $cCust === 20 && $cInv === 100 && $cPay === 100;
        if (! $ok) {
            $this->warn('Expected customers=20, invoices=100, payments=100.');
        }

        $base = rtrim((string) $this->option('uri'), '/');
        $token = (string) ($this->option('token') ?? '');

        if ($base !== '' && $token !== '') {
            $this->httpCheck($base.'/internal/intelligence/command-center', $token, 'command-center');
            $this->httpCheck($base.'/internal/intelligence/overview', $token, 'overview');
            $from = now()->subYear()->toDateString();
            $to   = now()->addDay()->toDateString();
            $this->httpCheck($base.'/dashboard/summary?from='.$from.'&to='.$to, $token, 'dashboard');
        } else {
            $this->line('Skip HTTP checks (pass --uri and --token to verify APIs).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function httpCheck(string $url, string $token, string $label): void
    {
        try {
            $res = \Illuminate\Support\Facades\Http::withToken($token)->acceptJson()->timeout(15)->get($url);
            $status = $res->status();
            if ($status >= 200 && $status < 300) {
                $this->info("{$label}: HTTP {$status}");
            } else {
                $this->warn("{$label}: HTTP {$status}");
            }
        } catch (\Throwable $e) {
            $this->error("{$label}: ".$e->getMessage());
        }
    }
}
