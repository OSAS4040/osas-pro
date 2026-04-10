<?php

namespace App\Services;

use App\Models\Bay;
use App\Models\Booking;
use App\Models\Branch;
use App\Support\BranchOpeningHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Check if a bay is free for the requested slot.
     */
    public function isSlotAvailable(int $bayId, Carbon $start, Carbon $end): bool
    {
        return !Booking::where('bay_id', $bayId)
            ->whereIn('status', ['pending','confirmed','in_progress'])
            ->where(fn ($q) => $q->whereBetween('starts_at', [$start, $end])
                ->orWhereBetween('ends_at', [$start, $end])
                ->orWhere(fn ($q2) => $q2->where('starts_at', '<=', $start)->where('ends_at', '>=', $end))
            )->exists();
    }

    /**
     * Find best available bay for a time slot and optional capability.
     */
    public function findAvailableBay(int $companyId, int $branchId, Carbon $start, Carbon $end, string $capability = null): ?Bay
    {
        $bays = Bay::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['available','reserved'])
            ->get();

        if ($capability) {
            $capable = $bays->filter(fn ($b) => in_array($capability, $b->capabilities ?? []));
            if ($capable->isNotEmpty()) $bays = $capable;
        }

        foreach ($bays as $bay) {
            if ($this->isSlotAvailable($bay->id, $start, $end)) return $bay;
        }

        return null;
    }

    public function book(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $start = Carbon::parse($data['starts_at']);
            $end   = Carbon::parse($data['ends_at']);

            $branchId = (int) ($data['branch_id'] ?? 0);
            if ($branchId > 0) {
                $branch = Branch::query()->where('company_id', $data['company_id'])->where('id', $branchId)->first();
                if ($branch && ! BranchOpeningHours::slotAllowed($branch->opening_hours, $start, $end)) {
                    throw new \DomainException('الموعد خارج ساعات عمل الفرع.');
                }
            }

            if (!$this->isSlotAvailable($data['bay_id'], $start, $end)) {
                throw new \DomainException('منطقة العمل محجوزة في هذا الوقت.');
            }

            $booking = Booking::create($data);

            // Mark bay as reserved if booking is for now
            if ($start->lte(now()) && $end->gte(now())) {
                Bay::find($data['bay_id'])?->update(['status' => 'reserved']);
            }

            return $booking;
        });
    }

    public function confirm(int $bookingId): Booking
    {
        $b = Booking::findOrFail($bookingId);
        $b->update(['status' => 'confirmed']);
        return $b->fresh();
    }

    public function start(int $bookingId): Booking
    {
        $b = Booking::with('bay')->findOrFail($bookingId);
        $b->update(['status' => 'in_progress']);
        $b->bay?->update(['status' => 'in_use', 'current_work_order_id' => $b->work_order_id]);
        return $b->fresh();
    }

    public function complete(int $bookingId): Booking
    {
        $b = Booking::with('bay')->findOrFail($bookingId);
        $b->update(['status' => 'completed']);
        $b->bay?->update(['status' => 'available', 'current_work_order_id' => null]);
        return $b->fresh();
    }

    public function cancel(int $bookingId, int $byUserId, string $reason = ''): Booking
    {
        $b = Booking::findOrFail($bookingId);
        $b->update(['status' => 'cancelled', 'cancelled_by' => $byUserId, 'cancellation_reason' => $reason]);
        return $b->fresh();
    }

    /**
     * Heatmap: utilization per bay per hour for a given date.
     */
    public function heatmap(int $companyId, int $branchId, string $date): array
    {
        $bays = Bay::where('company_id', $companyId)->where('branch_id', $branchId)->get();
        $result = [];

        foreach ($bays as $bay) {
            $hourly = [];
            $occupiedHours = 0;
            for ($h = 7; $h <= 21; $h++) {
                $slotStart = Carbon::parse("{$date} {$h}:00:00");
                $slotEnd   = $slotStart->copy()->addHour();
                $count = (int) Booking::where('bay_id', $bay->id)
                    ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
                    ->where('starts_at', '<', $slotEnd)
                    ->where('ends_at', '>', $slotStart)
                    ->count();
                $hourly[$h] = $count;
                if ($count > 0) {
                    $occupiedHours++;
                }
            }
            $result[] = [
                'bay_id'       => $bay->id,
                'code'         => $bay->code,
                'name'         => $bay->name,
                'status'       => $bay->status,
                'hourly'       => $hourly,
                'utilization'  => round($occupiedHours / 15 * 100, 1),
            ];
        }

        return $result;
    }
}
