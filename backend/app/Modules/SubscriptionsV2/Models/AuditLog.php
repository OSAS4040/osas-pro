<?php

namespace App\Modules\SubscriptionsV2\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @see subscriptions_v2_audit_logs (تعارض اسمي مع audit_logs الحوكمة العامة)
 */
class AuditLog extends Model
{
    protected $table = 'subscriptions_v2_audit_logs';

    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'before_json',
        'after_json',
        'context_json',
    ];

    protected $casts = [
        'before_json'  => 'array',
        'after_json'   => 'array',
        'context_json' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
