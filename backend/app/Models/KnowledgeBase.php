<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBase extends Model
{
    use SoftDeletes, HasTenantScope;

    protected $table = 'knowledge_base';

    protected $fillable = [
        'uuid','company_id','kb_category_id','author_id','title','title_ar',
        'content','content_ar','summary','tags','related_categories',
        'status','views','helpful_yes','helpful_no','is_public','is_featured','published_at',
    ];

    protected $casts = [
        'tags'               => 'array',
        'related_categories' => 'array',
        'is_public'          => 'boolean',
        'is_featured'        => 'boolean',
        'published_at'       => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getHelpfulnessRateAttribute(): float
    {
        $total = $this->helpful_yes + $this->helpful_no;
        return $total > 0 ? round(($this->helpful_yes / $total) * 100, 1) : 0;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
