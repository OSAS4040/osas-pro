<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketReply extends Model
{
    protected $table = 'support_ticket_replies';

    protected $fillable = [
        'uuid','ticket_id','user_id','author_type','author_name',
        'body','is_internal','channel','attachments','event_type','event_meta',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'attachments' => 'array',
        'event_meta'  => 'array',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
