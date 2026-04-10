<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id', 'vehicle_id',
        'created_by_user_id', 'assigned_technician_id', 'invoice_id',
        'order_number', 'work_order_number', 'status', 'priority',
        'source_type', 'source_id',
        'customer_complaint', 'diagnosis', 'technician_notes',
        'mileage_in', 'mileage_out', 'odometer_reading',
        'driver_name', 'driver_phone', 'notes',
        'estimated_total', 'actual_total',
        'started_at', 'completed_at', 'delivered_at',
        'trace_id', 'version', 'work_order_sync_status',
        'approved_by_user_id', 'approval_status', 'approved_at', 'credit_authorized',
        'created_by_side', 'fleet_approved_by_user_id', 'fleet_approved_at',
    ];

    protected $casts = [
        'status'              => WorkOrderStatus::class,
        'estimated_total'     => 'decimal:4',
        'actual_total'        => 'decimal:4',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'delivered_at'        => 'datetime',
        'approved_at'         => 'datetime',
        'fleet_approved_at'   => 'datetime',
        'credit_authorized'   => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function technicians()
    {
        return $this->hasMany(WorkOrderTechnician::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function canTransitionTo(WorkOrderStatus $new): bool
    {
        $allowed = [
            WorkOrderStatus::Draft->value => [
                WorkOrderStatus::PendingManagerApproval,
                WorkOrderStatus::Cancelled,
            ],
            WorkOrderStatus::PendingManagerApproval->value => [
                WorkOrderStatus::Approved,
                WorkOrderStatus::Cancelled,
            ],
            WorkOrderStatus::Approved->value => [
                WorkOrderStatus::InProgress,
            ],
            WorkOrderStatus::CancellationRequested->value => [],
            WorkOrderStatus::InProgress->value => [
                WorkOrderStatus::OnHold,
                WorkOrderStatus::Completed,
            ],
            WorkOrderStatus::OnHold->value => [
                WorkOrderStatus::InProgress,
            ],
            WorkOrderStatus::Completed->value => [WorkOrderStatus::Delivered],
            WorkOrderStatus::Delivered->value => [],
            WorkOrderStatus::Cancelled->value => [],
        ];

        return in_array($new, $allowed[$this->status->value] ?? [], true);
    }

    public function cancellationRequests()
    {
        return $this->hasMany(WorkOrderCancellationRequest::class, 'work_order_id');
    }
}
