<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid','ticket_number','company_id','branch_id','customer_id','fleet_account_id',
        'assigned_to','created_by','sla_policy_id','subject','description','category',
        'priority','status','channel','source_module','source_id',
        'first_response_at','resolved_at','closed_at','sla_due_at','escalated_at',
        'sla_breached','first_response_breached','satisfaction_score','satisfaction_comment',
        'satisfaction_rated_at','suggested_kb_articles','ai_sentiment_score',
        'ai_category_suggestion','ai_priority_suggestion','tags','attachments',
        'internal_notes','is_private',
    ];

    protected $casts = [
        'tags'                   => 'array',
        'attachments'            => 'array',
        'suggested_kb_articles'  => 'array',
        'sla_breached'           => 'boolean',
        'first_response_breached'=> 'boolean',
        'is_private'             => 'boolean',
        'first_response_at'      => 'datetime',
        'resolved_at'            => 'datetime',
        'closed_at'              => 'datetime',
        'sla_due_at'             => 'datetime',
        'escalated_at'           => 'datetime',
        'satisfaction_rated_at'  => 'datetime',
        'ai_sentiment_score'     => 'float',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function replies()
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('created_at');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function watchers()
    {
        return $this->belongsToMany(User::class, 'ticket_watchers', 'ticket_id', 'user_id');
    }

    // ─── Computed ──────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->sla_due_at && now()->gt($this->sla_due_at)
            && !in_array($this->status, ['resolved', 'closed']);
    }

    public function getSlaRemainingMinutesAttribute(): ?int
    {
        if (!$this->sla_due_at) return null;
        return (int) now()->diffInMinutes($this->sla_due_at, false);
    }

    public function getSlaPercentageAttribute(): ?float
    {
        if (!$this->sla_due_at || !$this->slaPolicy) return null;
        $totalMinutes = $this->slaPolicy->resolution_hours * 60;
        $elapsed      = $this->created_at->diffInMinutes(now());
        return min(100, round(($elapsed / $totalMinutes) * 100, 1));
    }

    // ─── Static helpers ────────────────────────────────────────────────────────

    public static function generateTicketNumber(): string
    {
        $prefix = 'TKT-' . now()->format('Ym') . '-';
        $last   = static::where('ticket_number', 'like', $prefix . '%')
                        ->latest('id')->value('ticket_number');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ─── AI Analysis (keyword-based smart categorization) ─────────────────────

    public static function analyzeTicket(string $text): array
    {
        $text = mb_strtolower($text);

        $categories = [
            'financial'   => ['فاتورة','invoice','دفع','payment','رصيد','wallet','محفظة','مبلغ','خطأ مالي'],
            'technical'   => ['خطأ','error','لا يعمل','broken','crash','بطيء','slow','bug','مشكلة تقنية'],
            'vehicle'     => ['مركبة','vehicle','سيارة','car','لوحة','plate','صيانة','maintenance'],
            'operational' => ['حجز','booking','أمر عمل','work order','موعد','appointment'],
            'billing'     => ['اشتراك','subscription','باقة','plan','تجديد','renewal'],
            'complaint'   => ['شكوى','complaint','غير راضٍ','موظف','سيئ','سوء'],
        ];

        $priorityKeywords = [
            'critical' => ['عاجل','urgent','مهم جداً','critical','إيقاف','stop','خطير'],
            'high'     => ['مهم','important','سريع','fast','أسرع','quickly'],
            'low'      => ['بسيط','simple','استفسار','inquiry','متى','when'],
        ];

        $detectedCategory = 'general';
        foreach ($categories as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) { $detectedCategory = $cat; break 2; }
            }
        }

        $detectedPriority = 'medium';
        foreach ($priorityKeywords as $pri => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) { $detectedPriority = $pri; break 2; }
            }
        }

        // Simple sentiment score
        $negativeWords = ['سيئ','غاضب','مشكلة','خطأ','لا يعمل','فشل','مرفوض'];
        $positiveWords = ['شكراً','ممتاز','رائع','جيد','سريع','مفيد'];
        $negCount = 0; $posCount = 0;
        foreach ($negativeWords as $w) { if (str_contains($text, $w)) $negCount++; }
        foreach ($positiveWords as $w) { if (str_contains($text, $w)) $posCount++; }
        $sentiment = ($posCount - $negCount) / max(1, $posCount + $negCount);

        return [
            'category'  => $detectedCategory,
            'priority'  => $detectedPriority,
            'sentiment' => round($sentiment, 2),
        ];
    }
}
