<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Calculate and record a commission for an employee based on active rules.
     */
    public function calculate(
        int $companyId,
        int $employeeId,
        string $sourceType,
        int $sourceId,
        float $baseAmount,
    ): ?Commission {
        $rule = CommissionRule::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('employee_id', $employeeId)->orWhereNull('employee_id'))
            ->where('min_amount', '<=', $baseAmount)
            ->orderByDesc('employee_id') // employee-specific rule takes priority
            ->first();

        if (!$rule) return null;

        $amount = round($baseAmount * $rule->rate / 100, 2);

        return Commission::create([
            'company_id'  => $companyId,
            'employee_id' => $employeeId,
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
            'base_amount' => $baseAmount,
            'rate'        => $rule->rate,
            'amount'      => $amount,
            'status'      => 'pending',
        ]);
    }

    public function markPaid(int $commissionId, int $paidBy): Commission
    {
        $c = Commission::findOrFail($commissionId);
        $c->update(['status' => 'paid', 'paid_at' => now(), 'paid_by' => $paidBy]);
        return $c->fresh();
    }

    public function pendingTotal(int $employeeId): float
    {
        return (float) Commission::where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->sum('amount');
    }
}
