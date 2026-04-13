<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LoyaltyPoints extends Model {
    use HasTenantScope;
    protected $fillable = ['company_id','customer_id','points','points_used','points_to_sar_rate','last_activity_at'];
    protected $casts = ['last_activity_at'=>'datetime','points_to_sar_rate'=>'float'];
    public function customer() { return $this->belongsTo(Customer::class); }
}
