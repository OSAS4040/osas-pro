<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Support\Facades\Request;

class AttendanceService
{
    public function checkIn(
        int $employeeId,
        int $companyId,
        int $branchId,
        float $lat = null,
        float $lng = null,
        string $deviceId = null,
    ): AttendanceLog {
        return AttendanceLog::create([
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'employee_id' => $employeeId,
            'type'        => 'check_in',
            'logged_at'   => now(),
            'latitude'    => $lat,
            'longitude'   => $lng,
            'device_id'   => $deviceId,
            'ip_address'  => Request::ip(),
            'is_valid'    => true,
        ]);
    }

    public function checkOut(
        int $employeeId,
        int $companyId,
        int $branchId,
        float $lat = null,
        float $lng = null,
    ): AttendanceLog {
        return AttendanceLog::create([
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'employee_id' => $employeeId,
            'type'        => 'check_out',
            'logged_at'   => now(),
            'latitude'    => $lat,
            'longitude'   => $lng,
            'ip_address'  => Request::ip(),
            'is_valid'    => true,
        ]);
    }

    public function todayLog(int $employeeId): array
    {
        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->whereDate('logged_at', today())
            ->orderBy('logged_at')
            ->get();

        $checkIn  = $logs->first(fn ($l) => $l->type === 'check_in');
        $checkOut = $logs->filter(fn ($l) => $l->type === 'check_out')->last();
        $minutes  = ($checkIn && $checkOut)
            ? $checkIn->logged_at->diffInMinutes($checkOut->logged_at)
            : null;

        return [
            'check_in'       => $checkIn?->logged_at,
            'check_out'      => $checkOut?->logged_at,
            'worked_minutes' => $minutes,
            'status'         => $checkIn ? ($checkOut ? 'checked_out' : 'checked_in') : 'absent',
        ];
    }

    public function monthSummary(int $employeeId, int $year, int $month): array
    {
        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->whereYear('logged_at', $year)
            ->whereMonth('logged_at', $month)
            ->orderBy('logged_at')
            ->get()
            ->groupBy(fn ($l) => $l->logged_at->toDateString());

        $days = [];
        foreach ($logs as $date => $dayLogs) {
            $in  = $dayLogs->first(fn ($l) => $l->type === 'check_in');
            $out = $dayLogs->filter(fn ($l) => $l->type === 'check_out')->last();
            $days[$date] = [
                'check_in'  => $in?->logged_at->format('H:i'),
                'check_out' => $out?->logged_at->format('H:i'),
                'minutes'   => ($in && $out) ? $in->logged_at->diffInMinutes($out->logged_at) : null,
            ];
        }

        return [
            'days'           => $days,
            'present_count'  => count($days),
            'total_minutes'  => array_sum(array_column($days, 'minutes')),
        ];
    }
}
