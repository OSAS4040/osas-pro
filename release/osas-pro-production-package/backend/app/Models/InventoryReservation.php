<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id',
        'created_by_user_id', 'product_id', 'inventory_id',
        'work_order_id',
        'reference_type', 'reference_id',
        'quantity', 'status', 'expires_at',
    ];

    protected $casts = [
        'status'     => ReservationStatus::class,
        'quantity'   => 'decimal:4',
        'expires_at' => 'datetime',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === ReservationStatus::Pending;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            ReservationStatus::Consumed,
            ReservationStatus::Released,
            ReservationStatus::Canceled,
            ReservationStatus::Expired,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canTransitionTo(ReservationStatus $newStatus): bool
    {
        $transitions = [
            ReservationStatus::Pending->value   => [ReservationStatus::Consumed, ReservationStatus::Released, ReservationStatus::Canceled, ReservationStatus::Expired],
            ReservationStatus::Consumed->value  => [],
            ReservationStatus::Released->value  => [],
            ReservationStatus::Canceled->value  => [],
            ReservationStatus::Expired->value   => [],
        ];

        $allowed = $transitions[$this->status->value] ?? [];
        return in_array($newStatus, $allowed);
    }
}
