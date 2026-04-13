<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NpsRating extends Model
{
    protected $fillable = [
        'company_id', 'invoice_id', 'work_order_id', 'customer_id',
        'score', 'comment', 'channel', 'alert_sent', 'resolved',
    ];

    protected $casts = ['alert_sent' => 'boolean', 'resolved' => 'boolean'];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
