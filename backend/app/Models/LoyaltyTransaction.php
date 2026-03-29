<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LoyaltyTransaction extends Model {
    use HasTenantScope;
    protected $fillable = ['company_id','customer_id','type','points','description','source_type','source_id','expires_at'];
    protected $casts = ['expires_at'=>'datetime'];
    public function customer() { return $this->belongsTo(Customer::class); }
}
