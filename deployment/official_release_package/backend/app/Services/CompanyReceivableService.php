<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompanyReceivableEntryType;
use App\Models\CompanyReceivableLedger;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Append-only company receivables — never update rows; post reversals as new lines.
 */
final class CompanyReceivableService
{
    public function __construct(
        private readonly CreditLimitService $creditLimit,
    ) {}

    public function recordChargeForApprovedWorkOrder(WorkOrder $wo, string $amount, ?string $idempotencyKey, ?int $invoiceId): CompanyReceivableLedger
    {
        return DB::transaction(function () use ($wo, $amount, $idempotencyKey, $invoiceId) {
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = CompanyReceivableLedger::query()
                    ->where('company_id', $wo->company_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $company = $wo->company()->lockForUpdate()->firstOrFail();
            app(BillingModelPolicyService::class)->assertCreditOperations((int) $company->id);
            $this->creditLimit->assertWithinLimit($company, $amount);

            $row = CompanyReceivableLedger::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $wo->company_id,
                'branch_id' => $wo->branch_id,
                'customer_id' => $wo->customer_id,
                'vehicle_id' => $wo->vehicle_id,
                'work_order_id' => $wo->id,
                'invoice_id' => $invoiceId,
                'entry_type' => CompanyReceivableEntryType::Charge,
                'amount' => $amount,
                'currency' => $company->currency ?? 'SAR',
                'idempotency_key' => $idempotencyKey,
                'reference_type' => WorkOrder::class,
                'reference_id' => $wo->id,
                'meta' => [
                    'trace_id' => app('trace_id'),
                    'kind' => 'work_order_approved_charge',
                ],
            ]);

            $this->creditLimit->refreshRunningBalance((int) $wo->company_id);

            return $row->fresh();
        });
    }

    public function recordReversalForWorkOrder(WorkOrder $wo, string $amount, string $reason, ?string $idempotencyKey): CompanyReceivableLedger
    {
        return DB::transaction(function () use ($wo, $amount, $reason, $idempotencyKey) {
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = CompanyReceivableLedger::query()
                    ->where('company_id', $wo->company_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $company = $wo->company()->lockForUpdate()->firstOrFail();
            app(BillingModelPolicyService::class)->assertCreditOperations((int) $company->id);

            $row = CompanyReceivableLedger::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $wo->company_id,
                'branch_id' => $wo->branch_id,
                'customer_id' => $wo->customer_id,
                'vehicle_id' => $wo->vehicle_id,
                'work_order_id' => $wo->id,
                'invoice_id' => $wo->invoice_id,
                'entry_type' => CompanyReceivableEntryType::Reversal,
                'amount' => $amount,
                'currency' => $company->currency ?? 'SAR',
                'idempotency_key' => $idempotencyKey,
                'reference_type' => WorkOrder::class,
                'reference_id' => $wo->id,
                'meta' => [
                    'trace_id' => app('trace_id'),
                    'kind' => 'work_order_cancellation_reversal',
                    'reason' => $reason,
                ],
            ]);

            $this->creditLimit->refreshRunningBalance((int) $wo->company_id);

            return $row->fresh();
        });
    }
}
