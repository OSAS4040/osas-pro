<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbCategory extends Model
{
    use HasTenantScope;

    protected $table = 'kb_categories';

    protected $fillable = [
        'uuid','company_id','name','name_ar','icon','color','sort_order','is_public',
    ];

    protected $casts = ['is_public' => 'boolean'];

    public function articles()
    {
        return $this->hasMany(KnowledgeBase::class, 'kb_category_id');
    }
}
