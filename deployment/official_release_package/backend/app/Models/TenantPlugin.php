<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TenantPlugin extends Model
{
    protected $table = 'tenant_plugins';
    protected $fillable = ['company_id','plugin_key','is_enabled','config','enabled_at','disabled_at'];
    protected $casts = ['config'=>'array','is_enabled'=>'boolean','enabled_at'=>'datetime','disabled_at'=>'datetime'];
    
    public function plugin() { return $this->belongsTo(Plugin::class, 'plugin_key', 'plugin_key'); }
    public function company() { return $this->belongsTo(Company::class); }
}
