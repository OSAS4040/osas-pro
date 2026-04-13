<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServiceReminder extends Model
{
    protected $fillable = [
        'company_id','customer_id','vehicle_id','invoice_id',
        'next_service_date','discount_code','discount_value','discount_type','notified',
    ];
    protected $casts = ['next_service_date' => 'date', 'notified' => 'boolean', 'discount_value' => 'float'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function vehicle()  { return $this->belongsTo(Vehicle::class); }
}
