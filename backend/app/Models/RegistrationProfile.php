<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationProfile extends Model
{
    protected $fillable = [
        'user_id',
        'account_type',
        'full_name',
        'company_name',
        'contact_name',
        'status',
        'company_activation_status',
        'profile_completion_percent',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
