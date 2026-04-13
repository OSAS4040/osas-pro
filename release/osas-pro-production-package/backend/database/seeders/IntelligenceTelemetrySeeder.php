<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DomainEvent;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent: payments for paid demo invoices + mirrored domain_events for Phase 2 / Command Center.
 * Requires DemoCompanySeeder + DemoDataSeeder (demo@autocenter.sa).
 */
class IntelligenceTelemetrySeeder extends Seeder
{
    public const SOURCE_CONTEXT = 'IntelligenceTelemetrySeeder';

    public function run(): void
    {
        $company = Company::where('email', 'demo@autocenter.sa')->first();
        if (! $company) {
            $this->command?->warn('IntelligenceTelemetrySeeder: demo company missing; skip.');

            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();
        if (! $branch) {
            $this->command?->error('IntelligenceTelemetrySeeder: main branch missing.');

            return;
        }

        $owner = User::where('company_id', $company->id)->where('email', 'owner@demo.sa')->first();
        $userId = $owner?->id;

        DomainEvent::where('company_id', $company->id)
            ->where('source_context', self::SOURCE_CONTEXT)
            ->delete();

        $this->ensurePaymentsForPaidInvoices($company->id, $branch->id, $userId);

        $base = now()->subDays(10)->startOfDay();

        foreach (Customer::where('company_id', $company->id)->orderBy('id')->get() as $i => $customer) {
            $this->insertEvent(
                $company->id,
                $branch->id,
                $userId,
                'customer',
                (string) $customer->id,
                'CustomerCreated',
                [
                    'customer_id'   => $customer->id,
                    'customer_uuid' => $customer->uuid,
                ],
                $base->copy()->addHours(1 + $i)
            );
        }

        foreach (Vehicle::where('company_id', $company->id)->orderBy('id')->get() as $i => $vehicle) {
            $this->insertEvent(
                $company->id,
                $branch->id,
                $userId,
                'vehicle',
                (string) $vehicle->id,
                'VehicleCreated',
                [
                    'vehicle_id'    => $vehicle->id,
                    'customer_id'   => $vehicle->customer_id,
                    'plate_number'  => $vehicle->plate_number,
                ],
                $base->copy()->addHours(20 + $i)
            );
        }

        foreach (WorkOrder::where('company_id', $company->id)->orderBy('id')->get() as $i => $wo) {
            $this->insertEvent(
                $company->id,
                $branch->id,
                $userId,
                'work_order',
                (string) $wo->id,
                'WorkOrderCreated',
                [
                    'work_order_id' => $wo->id,
                    'order_number'  => $wo->order_number,
                    'status'        => $wo->status instanceof \BackedEnum ? $wo->status->value : (string) $wo->status,
                ],
                $base->copy()->addHours(40 + $i)
            );
        }

        foreach (Invoice::where('company_id', $company->id)->orderBy('id')->get() as $i => $invoice) {
            $status = $invoice->status instanceof InvoiceStatus ? $invoice->status->value : (string) $invoice->status;
            $this->insertEvent(
                $company->id,
                $branch->id,
                $userId,
                'invoice',
                (string) $invoice->id,
                'InvoiceCreated',
                [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status'         => $status,
                    'total'          => (float) $invoice->total,
                ],
                $base->copy()->addHours(60 + $i)
            );

            if ($invoice->status === InvoiceStatus::Paid) {
                $payment = $invoice->payments()->orderBy('id')->first();
                if ($payment) {
                    $this->insertEvent(
                        $company->id,
                        $branch->id,
                        $userId,
                        'invoice',
                        (string) $invoice->id,
                        'InvoicePaid',
                        [
                            'invoice_id'     => $invoice->id,
                            'payment_id'     => $payment->id,
                            'amount'         => (float) $payment->amount,
                            'method'         => $payment->method,
                            'invoice_status' => $status,
                        ],
                        $base->copy()->addHours(70 + $i)
                    );
                }
            }
        }

        $this->command?->info('IntelligenceTelemetrySeeder: payments + domain_events synced for demo company.');
    }

    private function ensurePaymentsForPaidInvoices(int $companyId, int $branchId, ?int $createdByUserId): void
    {
        $uid = $createdByUserId ?? User::where('company_id', $companyId)->value('id');
        if (! $uid) {
            $this->command?->error('IntelligenceTelemetrySeeder: no user for payment rows.');

            return;
        }

        $invoices = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Paid)
            ->where('paid_amount', '>', 0)
            ->get();

        foreach ($invoices as $invoice) {
            if ($invoice->payments()->exists()) {
                continue;
            }

            Payment::create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'invoice_id'         => $invoice->id,
                'created_by_user_id' => $uid,
                'method'             => 'cash',
                'payment_method'     => 'cash',
                'amount'             => $invoice->paid_amount,
                'currency'           => $invoice->currency ?? 'SAR',
                'reference'          => 'SEED-'.Str::upper(Str::random(8)),
                'status'             => 'completed',
                'meta'               => ['seed' => self::SOURCE_CONTEXT],
                'created_at'         => $invoice->issued_at ?? now(),
            ]);
        }
    }

    private function insertEvent(
        int $companyId,
        int $branchId,
        ?int $userId,
        string $aggregateType,
        string $aggregateId,
        string $eventName,
        array $payload,
        Carbon $occurredAt,
    ): void {
        $trace = 'seed-'.Str::lower(Str::random(10));

        DomainEvent::create([
            'uuid'              => (string) Str::uuid(),
            'company_id'        => $companyId,
            'branch_id'         => $branchId,
            'aggregate_type'    => $aggregateType,
            'aggregate_id'      => $aggregateId,
            'event_name'        => $eventName,
            'event_version'     => 1,
            'payload_json'      => $payload,
            'metadata_json'     => array_filter([
                'company_id'        => $companyId,
                'branch_id'         => $branchId,
                'caused_by_user_id' => $userId,
                'trace_id'          => $trace,
                'correlation_id'    => 'seed-intelligence-bundle',
            ], fn ($v) => $v !== null),
            'trace_id'          => $trace,
            'correlation_id'    => 'seed-intelligence-bundle',
            'caused_by_user_id' => $userId,
            'caused_by_type'    => $userId ? 'user' : null,
            'source_context'    => self::SOURCE_CONTEXT,
            'processing_status' => 'recorded',
            'occurred_at'       => $occurredAt,
        ]);
    }
}
