<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractNotification extends Model
{
    protected $fillable = ['contract_id', 'type', 'channel', 'recipient', 'status', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
    public function contract() { return $this->belongsTo(Contract::class); }
}
