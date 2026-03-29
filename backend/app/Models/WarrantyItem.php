<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyItem extends Model
{
    protected $fillable = [
        'company_id', 'invoice_id', 'work_order_id',
        'part_name', 'part_number', 'warranty_days',
        'warranty_start', 'warranty_end', 'reminder_sent',
    ];

    protected $casts = [
        'warranty_start' => 'date',
        'warranty_end'   => 'date',
        'reminder_sent'  => 'boolean',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->warranty_end, false));
    }

    public function getStatusAttribute(): string
    {
        $days = $this->days_remaining;
        if ($days <= 0)   return 'expired';
        if ($days <= 7)   return 'expiring_soon';
        if ($days <= 30)  return 'warning';
        return 'active';
    }
}
