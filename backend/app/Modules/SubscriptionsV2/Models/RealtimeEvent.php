<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Models;

use Illuminate\Database\Eloquent\Model;

final class RealtimeEvent extends Model
{
    protected $table = 'subscription_realtime_events';

    protected $fillable = [
        'company_id',
        'audience',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

