<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use HasTenantScope;

    protected $table = 'sla_policies';

    protected $fillable = [
        'uuid','company_id','name','priority','first_response_hours',
        'resolution_hours','escalation_after_hours','escalate_to_roles',
        'notify_customer_on_breach','is_active',
    ];

    protected $casts = [
        'escalate_to_roles'           => 'array',
        'notify_customer_on_breach'   => 'boolean',
        'is_active'                   => 'boolean',
    ];

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
}
