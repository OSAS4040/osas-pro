<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\CompanyFinancialModelStatus;
use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuthLoginEvent;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

/**
 * Executive read-only aggregator for /admin overview.
 */
final class PlatformAdminOverviewService
{
    private const CACHE_KEY = 'platform:admin:overview:v2';

    /** Executive overview cache TTL — Signal Engine reads the same snapshot; keep aligned with docs + scoring meta. */
    public const EXECUTIVE_OVERVIEW_CACHE_TTL_SECONDS = 60;

    /** يُحدَّث من ‎`routes/console.php`‎ عند كل ‎`php artisan schedule:run`‎ ناجح (عادةً عبر cron). */
    public const SCHEDULER_LAST_RUN_CACHE_KEY = 'platform:schedule:last_run_at';

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds(self::EXECUTIVE_OVERVIEW_CACHE_TTL_SECONDS),
            fn (): array => $this->buildFresh(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFresh(): array
    {
        $now = now();
        $companiesTotal = (int) Company::query()->count();
        $companiesNew7d = (int) Company::query()->where('created_at', '>=', $now->copy()->subDays(7))->count();
        $companiesNew30d = (int) Company::query()->where('created_at', '>=', $now->copy()->subDays(30))->count();
        $tenantUsersTotal = (int) User::query()->whereNotNull('company_id')->count();
        $pendingFinancial = (int) Company::query()
            ->where('financial_model_status', CompanyFinancialModelStatus::PendingPlatformReview)
            ->count();

        $activityByCompany = $this->activityByCompany();
        $latestSubscriptions = $this->latestSubscriptionByCompany();
        $companyBuckets = $this->companyActivityBuckets($activityByCompany);
        $distribution = $this->buildDistribution($latestSubscriptions);
        $attention = $this->companiesRequiringAttention($activityByCompany, $latestSubscriptions);
        $health = $this->buildHealth();

        return [
            'generated_at' => $now->toIso8601String(),
            'cache' => ['ttl_seconds' => self::EXECUTIVE_OVERVIEW_CACHE_TTL_SECONDS],
            'definitions' => [
                'active_company' => 'شركة لديها نشاط خلال آخر 7 أيام (أمر عمل أو فاتورة أو تسجيل دخول).',
                'low_activity_company' => 'شركة لديها نشاط بين اليوم 8 والـ 14 دون نشاط في آخر 7 أيام.',
                'inactive_company' => 'لا نشاط يذكر خلال آخر 14 يوماً.',
                'catalog_mrr_estimate' => 'MRR تقديري من كتالوج الباقات لآخر اشتراك فعّال لكل شركة — وليس إيراداً محصّلاً.',
                'activity_score' => 'درجة النشاط = 3×أوامر العمل + 2×الفواتير + 1×تسجيلات الدخول (آخر 7 أيام).',
            ],
            'kpis' => [
                'total_companies' => $companiesTotal,
                'active_companies' => $companyBuckets['active'],
                'low_activity_companies' => $companyBuckets['low_activity'],
                'inactive_companies' => $companyBuckets['inactive'],
                'trial_companies' => (int) ($distribution['by_status']['trial'] ?? 0),
                'churn_risk_companies' => $this->countChurnRiskCompanies($attention),
                'total_users' => $tenantUsersTotal,
                'subscriptions_active' => (int) ($distribution['by_status']['active'] ?? 0),
                'estimated_mrr' => $this->estimateMrrFromCatalog($latestSubscriptions),
                'companies_new_7d' => $companiesNew7d,
                'companies_new_30d' => $companiesNew30d,
            ],
            'trends' => [
                'companies_growth' => $this->companiesSignupsLastDays(30),
                'activity_trend' => $this->activityTrendLastDays(30),
                'subscription_trend' => $this->subscriptionTrendLastDays(30),
            ],
            'distribution' => $distribution,
            'activity' => $this->buildActivityIntelligence($activityByCompany),
            'alerts' => $this->buildAlerts($pendingFinancial, $health['failed_jobs'], $attention),
            'companies_requiring_attention' => $attention,
            'health' => $health,
            'insights' => $this->buildInsights(
                $companiesNew7d,
                $companiesNew30d,
                $companyBuckets,
                (array) ($distribution['by_status'] ?? []),
                $health,
                $attention,
            ),
        ];
    }

    /**
     * @return array<int, array{status: string, plan: string, ends_at: string|null}>
     */
    private function latestSubscriptionByCompany(): array
    {
        $out = [];
        /** @var list<int> $companyIds */
        $companyIds = Company::query()->pluck('id')->all();
        foreach ($companyIds as $companyId) {
            $row = Subscription::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->orderByDesc('id')
                ->first();
            if ($row === null) {
                continue;
            }
            $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            $out[$companyId] = [
                'status' => strtolower($status),
                'plan' => (string) $row->plan,
                'ends_at' => $row->ends_at?->toDateString(),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{status: string, plan: string, ends_at: string|null}>  $latestSubscriptions
     */
    private function estimateMrrFromCatalog(array $latestSubscriptions): float
    {
        $sum = 0.0;
        foreach ($latestSubscriptions as $row) {
            if (($row['status'] ?? '') !== SubscriptionStatus::Active->value) {
                continue;
            }
            $slug = (string) ($row['plan'] ?? '');
            if ($slug === '') {
                continue;
            }
            $sum += (float) (Plan::query()->where('slug', $slug)->value('price_monthly') ?? 0);
        }

        return round($sum, 2);
    }

    /**
     * @return array<int, array{work_orders: int, invoices: int, logins: int, activity_score_7d: int, last_activity_days_ago: int}>
     */
    private function activityByCompany(): array
    {
        $since7 = now()->subDays(7);
        $out = [];
        /** @var list<int> $companyIds */
        $companyIds = Company::query()->pluck('id')->all();
        foreach ($companyIds as $companyId) {
            $wo7 = (int) WorkOrder::withoutGlobalScopes()->where('company_id', $companyId)->where('created_at', '>=', $since7)->count();
            $inv7 = (int) Invoice::withoutGlobalScopes()->where('company_id', $companyId)->where('created_at', '>=', $since7)->count();
            $log7 = Schema::hasTable('auth_login_events')
                ? (int) AuthLoginEvent::query()->where('company_id', $companyId)->where('created_at', '>=', $since7)->count()
                : 0;

            $lastWo = WorkOrder::withoutGlobalScopes()->where('company_id', $companyId)->max('created_at');
            $lastInv = Invoice::withoutGlobalScopes()->where('company_id', $companyId)->max('created_at');
            $lastLog = Schema::hasTable('auth_login_events')
                ? AuthLoginEvent::query()->where('company_id', $companyId)->max('created_at')
                : null;
            $latestAt = collect([$lastWo, $lastInv, $lastLog])->filter()->max();
            $days = 999;
            if (is_string($latestAt) && $latestAt !== '') {
                $days = abs(now()->diffInDays(\Illuminate\Support\Carbon::parse($latestAt), false));
            }

            $out[$companyId] = [
                'work_orders' => $wo7,
                'invoices' => $inv7,
                'logins' => $log7,
                'activity_score_7d' => (3 * $wo7) + (2 * $inv7) + $log7,
                'last_activity_days_ago' => $days,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{work_orders: int, invoices: int, logins: int, activity_score_7d: int, last_activity_days_ago: int}>  $activityByCompany
     * @return array{active: int, low_activity: int, inactive: int}
     */
    private function companyActivityBuckets(array $activityByCompany): array
    {
        $active = 0;
        $low = 0;
        $inactive = 0;
        foreach ($activityByCompany as $row) {
            $days = $row['last_activity_days_ago'];
            if ($days <= 7) {
                $active++;
            } elseif ($days <= 14) {
                $low++;
            } else {
                $inactive++;
            }
        }

        return ['active' => $active, 'low_activity' => $low, 'inactive' => $inactive];
    }

    /**
     * @param  array<int, array{status: string, plan: string, ends_at: string|null}>  $latestSubscriptions
     * @return array<string, array<string, int>>
     */
    private function buildDistribution(array $latestSubscriptions): array
    {
        $byStatus = [];
        $byPlan = [];
        foreach ($latestSubscriptions as $row) {
            $status = (string) ($row['status'] ?? 'unknown');
            $plan = (string) ($row['plan'] ?? 'unknown');
            $byStatus[$status] = (int) ($byStatus[$status] ?? 0) + 1;
            $byPlan[$plan] = (int) ($byPlan[$plan] ?? 0) + 1;
        }
        ksort($byStatus);
        arsort($byPlan);

        return ['by_plan' => $byPlan, 'by_status' => $byStatus];
    }

    /**
     * @param  array<int, array{work_orders: int, invoices: int, logins: int, activity_score_7d: int, last_activity_days_ago: int}>  $activityByCompany
     * @return array<string, mixed>
     */
    private function buildActivityIntelligence(array $activityByCompany): array
    {
        $rows = [];
        foreach ($activityByCompany as $companyId => $activity) {
            $companyName = (string) (Company::query()->whereKey($companyId)->value('name') ?? ('#'.$companyId));
            $rows[] = [
                'company_id' => $companyId,
                'company_name' => $companyName,
                'activity_score' => $activity['activity_score_7d'],
                'last_activity_days_ago' => $activity['last_activity_days_ago'],
            ];
        }
        usort($rows, fn ($a, $b) => $b['activity_score'] <=> $a['activity_score']);
        $most = array_slice($rows, 0, 5);
        usort($rows, fn ($a, $b) => $a['activity_score'] <=> $b['activity_score']);
        $least = array_slice($rows, 0, 5);
        $avg = count($rows) > 0
            ? round(array_sum(array_map(fn ($r) => (int) $r['activity_score'], $rows)) / count($rows), 2)
            : 0.0;

        return [
            'most_active_companies' => $most,
            'least_active_companies' => $least,
            'avg_activity_score' => $avg,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHealth(): array
    {
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null;

        $databaseOk = true;
        try {
            DB::selectOne('select 1 as ok');
        } catch (\Throwable) {
            $databaseOk = false;
        }

        $redisOk = false;
        try {
            $pong = Redis::connection()->ping();
            $redisOk = $pong === true || $pong === '+PONG' || $pong === 'PONG';
        } catch (\Throwable) {
            $redisOk = false;
        }

        $queuePending = null;
        if (Schema::hasTable('jobs')) {
            $queuePending = (int) DB::table('jobs')->count();
        }

        $failedPressure = $failedJobs !== null && $failedJobs > 20;
        $jobsPressure = $queuePending !== null && $queuePending > 500;
        $queueOk = ! $failedPressure && ! $jobsPressure;

        return [
            'api' => $databaseOk ? 'ok' : 'degraded',
            'queue' => $queueOk ? 'ok' : 'degraded',
            'failed_jobs' => $failedJobs,
            'trend' => ($queueOk && $databaseOk && $redisOk) ? 'stable' : 'degraded',
            'database_ok' => $databaseOk,
            'redis_ok' => $redisOk,
            'queue_pending_count' => $queuePending,
            'scheduler_last_run_at' => $this->schedulerLastRunAt(),
            'scheduler_note_ar' => 'يُحدَّث هذا الطابع عندما يستدعي الخادم ‎`php artisan schedule:run`‎ (cron). إن بقي فارغاً فالجدولة لا تصل إلى التطبيق.',
        ];
    }

    private function schedulerLastRunAt(): ?string
    {
        $raw = Cache::get(self::SCHEDULER_LAST_RUN_CACHE_KEY);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return $raw;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function companiesSignupsLastDays(int $days): array
    {
        $since = now()->copy()->subDays($days)->startOfDay();
        $rows = Company::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as count')
            ->groupBy('date_key')
            ->orderBy('date_key')
            ->get();

        return $rows->map(fn ($r) => ['date' => (string) $r->date_key, 'count' => (int) $r->count])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityTrendLastDays(int $days): array
    {
        $since = now()->copy()->subDays($days)->startOfDay();
        $series = [];
        for ($i = 0; $i <= $days; $i++) {
            $date = $since->copy()->addDays($i)->format('Y-m-d');
            $wo = (int) WorkOrder::withoutGlobalScopes()->whereDate('created_at', $date)->count();
            $inv = (int) Invoice::withoutGlobalScopes()->whereDate('created_at', $date)->count();
            $log = Schema::hasTable('auth_login_events')
                ? (int) AuthLoginEvent::query()->whereDate('created_at', $date)->count()
                : 0;
            $series[] = [
                'date' => $date,
                'activity_score' => (3 * $wo) + (2 * $inv) + $log,
                'work_orders' => $wo,
                'invoices' => $inv,
                'logins' => $log,
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subscriptionTrendLastDays(int $days): array
    {
        $since = now()->copy()->subDays($days)->startOfDay();
        $rows = Subscription::withoutGlobalScopes()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date_key, status, COUNT(*) as count')
            ->groupBy('date_key', 'status')
            ->orderBy('date_key')
            ->get();

        $bucket = [];
        foreach ($rows as $row) {
            $date = (string) $row->date_key;
            if (! isset($bucket[$date])) {
                $bucket[$date] = [];
            }
            $rawStatus = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            $status = strtolower($rawStatus);
            $bucket[$date][$status] = (int) $row->count;
        }

        $out = [];
        foreach ($bucket as $date => $mix) {
            $out[] = ['date' => $date, 'status_mix' => $mix];
        }

        return $out;
    }

    /**
     * @param  array<int, array{work_orders: int, invoices: int, logins: int, activity_score_7d: int, last_activity_days_ago: int}>  $activityByCompany
     * @param  array<int, array{status: string, plan: string, ends_at: string|null}>  $latestSubscriptions
     * @return list<array<string, mixed>>
     */
    private function companiesRequiringAttention(array $activityByCompany, array $latestSubscriptions): array
    {
        $companies = Company::query()
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'name', 'is_active', 'status', 'financial_model_status', 'updated_at']);

        $attention = [];
        foreach ($companies as $company) {
            $cid = (int) $company->id;
            $activity = $activityByCompany[$cid] ?? [
                'activity_score_7d' => 0,
                'last_activity_days_ago' => 999,
            ];
            $sub = $latestSubscriptions[$cid] ?? null;
            $reasons = [];

            if ($company->status === CompanyStatus::Suspended || ! $company->is_active) {
                $reasons[] = 'operational_block';
            }
            if ($company->financial_model_status === CompanyFinancialModelStatus::PendingPlatformReview) {
                $reasons[] = 'pending_financial_review';
            }
            if ((int) $activity['last_activity_days_ago'] > 14) {
                $reasons[] = 'inactive_14d';
            } elseif ((int) $activity['last_activity_days_ago'] > 7) {
                $reasons[] = 'low_activity_7_14d';
            }
            if ($sub !== null && ($sub['status'] ?? '') === 'trial' && is_string($sub['ends_at']) && $sub['ends_at'] !== '') {
                $days = now()->diffInDays(\Illuminate\Support\Carbon::parse($sub['ends_at']), false);
                if ($days >= 0 && $days <= 3) {
                    $reasons[] = 'trial_ending';
                }
            }
            if ($reasons === []) {
                continue;
            }

            $reason = $reasons[0];
            $attention[] = [
                'company_id' => $cid,
                'name' => $company->name,
                'reason' => $reason,
                'reason_ar' => $this->attentionReasonAr($reason),
                'reasons' => $reasons,
                'activity_score' => (int) $activity['activity_score_7d'],
                'last_activity_days_ago' => (int) $activity['last_activity_days_ago'],
                'is_active' => (bool) $company->is_active,
                'company_status' => $company->status?->value,
                'financial_model_status' => $company->financial_model_status?->value,
                'updated_at' => $company->updated_at?->toIso8601String(),
                'action_hint' => $this->attentionActionHint($reason),
                'action_path' => '/admin#admin-section-tenants',
            ];

            if (count($attention) >= 25) {
                break;
            }
        }

        return $attention;
    }

    /**
     * @param  list<array<string, mixed>>  $attention
     * @return list<array<string, mixed>>
     */
    private function buildAlerts(int $pendingFinancial, ?int $failedJobs, array $attention): array
    {
        $alerts = [];
        if ($pendingFinancial > 0) {
            $alerts[] = [
                'type' => 'pending_financial_review',
                'severity' => 'medium',
                'message' => 'يوجد '.$pendingFinancial.' شركة بانتظار مراجعة النموذج المالي على مستوى المنصة.',
                'action_hint' => 'راجع قسم «النموذج المالي» واعتماد أو رفض الطلبات.',
                'action_path' => '/admin#admin-section-finance',
            ];
        }
        if ($failedJobs !== null && $failedJobs > 20) {
            $alerts[] = [
                'type' => 'queue_failed_jobs',
                'severity' => 'high',
                'message' => 'إجمالي المهام الفاشلة المتراكمة في الطابور مرتفع ('.$failedJobs.').',
                'action_hint' => 'افحص عمال الطابور وحدد السبب الجذري أولاً، ثم أعد المحاولة بشكل موجّه أو أصلح السبب.',
                'action_path' => '/admin#admin-section-ops',
            ];
        }
        foreach ($attention as $row) {
            if (($row['reason'] ?? '') === 'trial_ending') {
                $alerts[] = [
                    'type' => 'trial_expiring',
                    'severity' => 'high',
                    'company_id' => $row['company_id'],
                    'message' => 'اشتراك تجريبي على وشك الانتهاء للشركة «'.($row['name'] ?? '').'».',
                    'action_hint' => 'تواصل مع المستأجر أو حوّل إلى باقة مدفوعة وفق سياسة المنصة.',
                    'action_path' => '/admin#admin-section-tenants',
                ];
            }
        }

        return array_slice($alerts, 0, 20);
    }

    /**
     * @param  array{active: int, low_activity: int, inactive: int}  $companyBuckets
     * @param  array<string, int>  $statusDistribution
     * @param  array<string, mixed>  $health
     * @param  list<array<string, mixed>>  $attention
     * @return list<array<string, string>>
     */
    private function buildInsights(
        int $new7,
        int $new30,
        array $companyBuckets,
        array $statusDistribution,
        array $health,
        array $attention,
    ): array {
        $insights = [];
        if ($new30 > 0) {
            $pct = round(($new7 / max(1, $new30)) * 100, 1);
            $insights[] = ['tone' => 'positive', 'text' => 'إشارة نمو: '.$new7.' شركة جديدة خلال 7 أيام ('.$pct.'٪ من مقياس 30 يوماً).'];
        }
        if ($companyBuckets['low_activity'] > 0) {
            $insights[] = ['tone' => 'warning', 'text' => $companyBuckets['low_activity'].' شركة بأداء منخفض (نشاط بين 8 و14 يوماً).'];
        }
        if ($companyBuckets['inactive'] > 0) {
            $insights[] = ['tone' => 'action', 'text' => $companyBuckets['inactive'].' شركة بلا نشاط يذكر منذ 14 يوماً فأكثر — راجع خطة إعادة التفعيل.'];
        }
        if (($statusDistribution['trial'] ?? 0) > 0) {
            $insights[] = ['tone' => 'neutral', 'text' => 'توجد شركات على باقة تجريبية — راقب التحويل إلى مدفوع.'];
        }
        if (($health['trend'] ?? 'stable') === 'degraded') {
            $insights[] = ['tone' => 'warning', 'text' => 'الصحة التشغيلية منخفضة (قاعدة البيانات، Redis، أو ضغط الطابور) — راجع قسم التشغيل العام.'];
        }
        if (count($attention) > 0) {
            $insights[] = ['tone' => 'action', 'text' => 'توجد قائمة شركات تحتاج متابعة مرتبة حسب الأولوية التشغيلية.'];
        }

        return $insights;
    }

    /**
     * @param  list<array<string, mixed>>  $attention
     */
    private function countChurnRiskCompanies(array $attention): int
    {
        $count = 0;
        foreach ($attention as $row) {
            if (in_array((string) ($row['reason'] ?? ''), ['trial_ending', 'low_activity_7_14d', 'inactive_14d'], true)) {
                $count++;
            }
        }

        return $count;
    }

    private function attentionActionHint(string $reason): string
    {
        return match ($reason) {
            'trial_ending' => 'متابعة تحويل التجربة إلى اشتراك مدفوع.',
            'low_activity_7_14d' => 'تشغيل خطة تدخل للشركات منخفضة النشاط.',
            'inactive_14d' => 'تصعيد لمسار إعادة تفعيل / نجاح العملاء.',
            'pending_financial_review' => 'إكمال مراجعة النموذج المالي المعلّقة.',
            'operational_block' => 'التحقق من سبب الإيقاف التشغيلي ثم إعادة التفعيل بأمان.',
            default => 'مراجعة يدوية من مشغّل المنصة.',
        };
    }

    private function attentionReasonAr(string $reason): string
    {
        return match ($reason) {
            'operational_block' => 'إيقاف تشغيلي أو شركة موقوفة',
            'pending_financial_review' => 'نموذج مالي بانتظار مراجعة المنصة',
            'inactive_14d' => 'لا نشاط منذ 14 يوماً فأكثر',
            'low_activity_7_14d' => 'نشاط منخفض (7–14 يوماً)',
            'trial_ending' => 'تجربة تنتهي خلال أيام',
            default => $reason,
        };
    }
}
