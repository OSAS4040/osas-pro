<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PluginLog extends Model
{
    protected $table = 'plugin_logs';
    protected $fillable = ['plugin_key','company_id','event_type','payload','status','execution_ms'];
    protected $casts = ['payload' => 'array'];
}
