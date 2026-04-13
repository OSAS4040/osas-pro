<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Commission;
use App\Models\CommissionRule;

class CommissionService
{
    /**
     * Calculate and record a commission for an employee based on active rules.
     * يُفضَّل القاعدة ذات الأولوية الأعلى، ثم المرتبطة بعميل محدد، ثم الموظف، ثم العامة.
     */
    public function calculate(
        int $companyId,
        int $employeeId,
        string $sourceType,
        int $sourceId,
        float $baseAmount,
        ?int $customerId = null,
    ): ?Commission {
        $appliesTo = match (true) {
            str_contains($sourceType, 'Invoice')   => 'invoice',
            str_contains($sourceType, 'WorkOrder') => 'work_order',
            default                                 => 'service',
        };

        $rule = CommissionRule::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('applies_to', $appliesTo)
            ->where('min_amount', '<=', $baseAmount)
            ->where(function ($q) use ($employeeId) {
                $q->whereNull('employee_id')->orWhere('employee_id', $employeeId);
            })
            ->where(function ($q) use ($customerId) {
                $q->whereNull('customer_id');
                if ($customerId !== null) {
                    $q->orWhere('customer_id', $customerId);
                }
            })
            ->orderByDesc('priority')
            ->orderByRaw('CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByRaw('CASE WHEN employee_id IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('id')
            ->first();

        if (! $rule) {
            return null;
        }

        $amount = round($baseAmount * (float) $rule->rate / 100, 2);
        if ($rule->max_commission_amount !== null) {
            $amount = min($amount, (float) $rule->max_commission_amount);
        }

        $quality = $this->attendanceQualityFactor($employeeId, $companyId);
        $blend   = 0.75 + 0.25 * $quality;
        $amount  = round($amount * (float) $rule->attendance_multiplier * $blend, 2);

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

    /**
     * نسبة تقريبية لانتظام الحضور (آخر 30 يوماً) — تُستخدم لدمج ذكي مع العمولة.
     */
    private function attendanceQualityFactor(int $employeeId, int $companyId): float
    {
        $since = now()->subDays(30)->startOfDay();
        $days  = AttendanceLog::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('type', 'check_in')
            ->where('logged_at', '>=', $since)
            ->get()
            ->pluck('logged_at')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->count();
        $expected = 22;

        return min(1.0, $days / max(1, $expected));
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
