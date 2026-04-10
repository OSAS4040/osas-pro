<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $table = 'plugins_registry';
    protected $fillable = [
        'plugin_key', 'name', 'name_ar', 'description', 'description_ar', 'version', 'author', 'category', 'icon',
        'module_scope', 'config_schema', 'supported_plans', 'hooks', 'is_active', 'is_premium',
        'price_monthly', 'install_count', 'rating', 'recommended_rank', 'tags',
    ];

    protected $casts = [
        'module_scope' => 'array',
        'config_schema' => 'array',
        'supported_plans' => 'array',
        'hooks' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
    ];
    
    public function tenantInstalls() { return $this->hasMany(TenantPlugin::class, 'plugin_key', 'plugin_key'); }
}
