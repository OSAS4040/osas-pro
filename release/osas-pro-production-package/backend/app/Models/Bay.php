<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Bay extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid','company_id','branch_id','code','name','type','status',
        'capacity','current_work_order_id','capabilities','notes',
    ];

    protected $casts = ['capabilities' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function bookings(): HasMany { return $this->hasMany(Booking::class); }

    public function isAvailable(): bool { return $this->status === 'available'; }

    public function activeBookingAt(\Carbon\Carbon $at): ?Booking
    {
        return $this->bookings()
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->whereIn('status', ['confirmed','in_progress'])
            ->first();
    }
}
