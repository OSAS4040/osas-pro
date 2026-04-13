<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\SlaPolicy;
use App\Models\KbCategory;
use App\Models\KnowledgeBase;
use App\Models\User;

class SupportSystemSeeder extends Seeder
{
    public function run(): void
    {
        $company = \App\Models\Company::first();
        if (!$company) return;

        $owner = User::where('company_id', $company->id)->where('role', 'owner')->first();
        if (!$owner) return;

        // ── SLA Policies ──────────────────────────────────────────────────────
        $slas = [
            ['critical', 1, 4,  2,  ['owner']],
            ['high',     2, 8,  4,  ['owner','manager']],
            ['medium',   4, 24, 12, ['manager']],
            ['low',      8, 72, 36, ['manager']],
        ];

        $slaPolicies = [];
        foreach ($slas as [$priority, $first, $resolution, $escalate, $roles]) {
            if (SlaPolicy::where('company_id', $company->id)->where('priority', $priority)->exists()) {
                $slaPolicies[$priority] = SlaPolicy::where('company_id', $company->id)->where('priority', $priority)->first();
                continue;
            }
            $slaPolicies[$priority] = SlaPolicy::create([
                'uuid'                      => Str::uuid(),
                'company_id'                => $company->id,
                'name'                      => "SLA - " . ucfirst($priority),
                'priority'                  => $priority,
                'first_response_hours'      => $first,
                'resolution_hours'          => $resolution,
                'escalation_after_hours'    => $escalate,
                'escalate_to_roles'         => $roles,
                'notify_customer_on_breach' => true,
                'is_active'                 => true,
            ]);
        }

        // ── KB Categories ─────────────────────────────────────────────────────
        $kbCats = [
            ['البدء السريع',    'الشروع في استخدام النظام', 'RocketLaunchIcon',  '#3B82F6', 0],
            ['الفواتير',        'أسئلة الفوترة والدفع',     'CreditCardIcon',    '#10B981', 1],
            ['المركبات',        'إدارة المركبات والأسطول',  'TruckIcon',         '#F59E0B', 2],
            ['الحجوزات',        'نظام الحجوزات والمواعيد',  'CalendarIcon',      '#8B5CF6', 3],
            ['الحسابات',        'إدارة الحسابات والمحافظ',  'BanknotesIcon',     '#EF4444', 4],
            ['الأمان',          'الخصوصية والأمان',         'ShieldCheckIcon',   '#6B7280', 5],
        ];

        $catMap = [];
        foreach ($kbCats as [$name, $nameAr, $icon, $color, $sort]) {
            if (KbCategory::where('company_id', $company->id)->where('name', $name)->exists()) {
                $catMap[$name] = KbCategory::where('company_id', $company->id)->where('name', $name)->first();
                continue;
            }
            $catMap[$name] = KbCategory::create([
                'uuid'       => Str::uuid(),
                'company_id' => $company->id,
                'name'       => $name,
                'name_ar'    => $nameAr,
                'icon'       => $icon,
                'color'      => $color,
                'sort_order' => $sort,
                'is_public'  => true,
            ]);
        }

        // ── KB Articles ───────────────────────────────────────────────────────
        $articles = [
            [
                'title'      => 'كيفية إنشاء فاتورة جديدة',
                'summary'    => 'دليل خطوة بخطوة لإنشاء فاتورة متوافقة مع هيئة الزكاة',
                'content'    => '<h2>خطوات إنشاء الفاتورة</h2><ol><li>انتقل إلى قسم الفواتير</li><li>اضغط "فاتورة جديدة"</li><li>اختر العميل</li><li>أضف المنتجات أو الخدمات</li><li>تحقق من ضريبة القيمة المضافة</li><li>اضغط "إصدار"</li></ol>',
                'cat'        => 'الفواتير',
                'tags'       => ['فاتورة','زكاة','ضريبة'],
                'is_featured'=> true,
            ],
            [
                'title'      => 'إضافة مركبة جديدة للنظام',
                'summary'    => 'كيفية تسجيل مركبة عميل مع تفاصيلها الكاملة',
                'content'    => '<h2>تسجيل المركبة</h2><p>من قسم المركبات، اضغط "إضافة مركبة" وأدخل: رقم اللوحة، الماركة، الموديل، سنة الصنع، وأي معلومات إضافية.</p>',
                'cat'        => 'المركبات',
                'tags'       => ['مركبة','لوحة','إضافة'],
                'is_featured'=> false,
            ],
            [
                'title'      => 'شحن رصيد المحفظة',
                'summary'    => 'طريقة شحن رصيد محفظة الشركة أو الأسطول',
                'content'    => '<h2>شحن الرصيد</h2><p>يمكن شحن الرصيد من خلال قسم المحفظة عبر تحديد نوع المحفظة والمبلغ المراد شحنه، ويتم تسجيل العملية تلقائيًا في دفتر الأستاذ.</p>',
                'cat'        => 'الحسابات',
                'tags'       => ['محفظة','رصيد','شحن'],
                'is_featured'=> true,
            ],
            [
                'title'      => 'إعداد نظام الحجوزات',
                'summary'    => 'ضبط إعدادات الحجوزات ومناطق العمل',
                'content'    => '<h2>إعداد الحجوزات</h2><p>من الإعدادات، حدد مناطق العمل المتاحة، ساعات العمل، وقدرة الاستيعاب اليومية.</p>',
                'cat'        => 'الحجوزات',
                'tags'       => ['حجز','موعد','جدولة'],
                'is_featured'=> false,
            ],
        ];

        foreach ($articles as $a) {
            if (KnowledgeBase::where('company_id', $company->id)->where('title', $a['title'])->exists()) continue;
            KnowledgeBase::create([
                'uuid'           => Str::uuid(),
                'company_id'     => $company->id,
                'kb_category_id' => $catMap[$a['cat']]->id,
                'author_id'      => $owner->id,
                'title'          => $a['title'],
                'summary'        => $a['summary'],
                'content'        => $a['content'],
                'tags'           => $a['tags'],
                'status'         => 'published',
                'is_public'      => true,
                'is_featured'    => $a['is_featured'],
                'published_at'   => now()->subDays(rand(1, 30)),
                'views'          => rand(10, 200),
                'helpful_yes'    => rand(5, 50),
                'helpful_no'     => rand(0, 10),
            ]);
        }

        // ── Sample Tickets ────────────────────────────────────────────────────
        if (SupportTicket::where('company_id', $company->id)->count() > 0) return;

        $ticketSamples = [
            ['الفاتورة لم تُرسل للعميل', 'طلبت إصدار فاتورة ولكنها لم تصل للعميل عبر الواتساب', 'financial', 'high', 'in_progress'],
            ['خطأ في حساب ضريبة القيمة المضافة', 'الفاتورة تعرض نسبة ضريبة خاطئة 20% بدلاً من 15%', 'financial', 'critical', 'open'],
            ['لا يمكن إضافة مركبة جديدة', 'عند محاولة إضافة مركبة يظهر خطأ "رقم اللوحة موجود مسبقًا"', 'vehicle', 'medium', 'resolved'],
            ['استفسار عن طريقة الحجز', 'كيف يمكن للعميل حجز موعد بنفسه من البوابة؟', 'operational', 'low', 'closed'],
            ['المحفظة لا تعكس عملية الشحن', 'شحنا رصيداً بمبلغ 500 ريال ولم يظهر في المحفظة', 'financial', 'critical', 'escalated'],
            ['طلب تدريب على النظام', 'نحتاج جلسة تدريبية للموظفين الجدد', 'general', 'low', 'open'],
            ['تقرير المبيعات لا يُصدَّر', 'زر تصدير PDF في التقارير لا يعمل', 'technical', 'high', 'in_progress'],
        ];

        foreach ($ticketSamples as [$subject, $desc, $cat, $priority, $status]) {
            $slaPolicy = $slaPolicies[$priority] ?? null;
            $isResolved = in_array($status, ['resolved','closed']);

            $ticket = SupportTicket::create([
                'uuid'           => Str::uuid(),
                'ticket_number'  => SupportTicket::generateTicketNumber(),
                'company_id'     => $company->id,
                'branch_id'      => $owner->branch_id,
                'created_by'     => $owner->id,
                'assigned_to'    => $owner->id,
                'sla_policy_id'  => $slaPolicy?->id,
                'subject'        => $subject,
                'description'    => $desc,
                'category'       => $cat,
                'priority'       => $priority,
                'status'         => $status,
                'channel'        => ['portal','email','phone'][rand(0,2)],
                'sla_due_at'     => now()->addHours($slaPolicy?->resolution_hours ?? 24)->subHours(rand(0, 10)),
                'sla_breached'   => $status === 'escalated',
                'first_response_at' => now()->subHours(rand(1, 5)),
                'resolved_at'    => $isResolved ? now()->subHours(rand(1, 10)) : null,
                'satisfaction_score' => $isResolved ? rand(3, 5) : null,
                'ai_sentiment_score' => round(rand(-100, 100) / 100, 2),
                'created_at'     => now()->subDays(rand(0, 14)),
            ]);

            // Add a reply to each ticket
            TicketReply::create([
                'uuid'        => Str::uuid(),
                'ticket_id'   => $ticket->id,
                'user_id'     => $owner->id,
                'author_type' => 'staff',
                'author_name' => $owner->name,
                'body'        => 'تم استلام طلبك وسيتم مراجعته في أقرب وقت ممكن. رقم التذكرة: ' . $ticket->ticket_number,
                'is_internal' => false,
                'event_type'  => 'reply',
                'created_at'  => $ticket->created_at->addMinutes(30),
            ]);
        }
    }
}
