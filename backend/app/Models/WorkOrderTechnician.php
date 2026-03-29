<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderTechnician extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'work_order_id', 'user_id', 'role',
        'labor_hours', 'labor_cost', 'assigned_at', 'completed_at',
    ];

    protected $casts = [
        'labor_hours'  => 'decimal:2',
        'labor_cost'   => 'decimal:4',
        'assigned_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
