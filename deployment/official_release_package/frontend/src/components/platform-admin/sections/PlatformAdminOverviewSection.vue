<template>
  <section id="admin-section-overview" class="scroll-mt-32 mb-16">
    <div
      v-if="isPlatform"
      id="platform-overview-executive"
      class="scroll-mt-28"
    >
      <div class="mb-5 border-b border-slate-200 pb-4 dark:border-slate-700">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">الملخص التنفيذي — سطح ذكاء المنصة</h2>
        <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
          تفسير وتوجيه وليس مجرد أرقام: الخدمات، المشتركون، الباقات، والنبض. التفاصيل المالية والعملاء التفصيليون يبقون في بوابة فريق العمل للمستأجر.
        </p>
      </div>
      <PlatformAdminExecutivePanel
        :refresh-tick="executiveOverviewTick"
        :finance-why-engine-teaser="financeWhyEngineTeaser"
      />
    </div>
    <PlatformAttentionNowSection v-if="isPlatform" />
    <div
      v-if="platformBanner"
      class="mb-4 rounded-xl border px-4 py-3 text-sm leading-relaxed"
      :class="companiesFeedOk ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-600/30 dark:bg-emerald-950/30 dark:text-emerald-100' : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-600/40 dark:bg-amber-950/40 dark:text-amber-100'"
    >
      {{ platformBanner }}
    </div>
    <div
      v-if="isPlatform"
      class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-slate-200/90 bg-white/90 px-4 py-2.5 text-[11px] text-slate-600 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-300"
    >
      <span class="inline-flex items-center gap-1.5 font-semibold text-slate-800 dark:text-slate-100">
        <span class="h-2 w-2 rounded-full" :class="companiesFeedOk ? 'bg-emerald-500' : 'bg-amber-500'" />
        المشتركون:
        <template v-if="companiesFeedOk">
          متصل — {{ companiesTotalCount.toLocaleString('ar-SA') }} شركة
          <span v-if="companiesTotalCount > companiesPageLength" class="font-normal text-slate-500 dark:text-slate-400">(عرض {{ companiesPageLength }} في هذه الصفحة)</span>
        </template>
        <template v-else>غير متاح من واجهة /admin/companies</template>
      </span>
      <span class="hidden h-4 w-px bg-slate-200 sm:inline dark:bg-slate-700" aria-hidden="true" />
      <span class="inline-flex items-center gap-1.5">
        <span class="h-2 w-2 rounded-full" :class="plansLoadOk ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'" />
        الباقات: {{ plansLoadOk ? 'متصل' : 'تعذّر أو لم يُحمَّل' }}
      </span>
      <span class="hidden h-4 w-px bg-slate-200 sm:inline dark:bg-slate-700" aria-hidden="true" />
      <span class="inline-flex items-center gap-1.5">
        <span class="h-2 w-2 rounded-full" :class="pulsePayload ? 'bg-primary-500' : pulseLoading ? 'bg-amber-400 animate-pulse' : 'bg-slate-300 dark:bg-slate-600'" />
        نبض المنصة (30 يوماً): {{ pulsePayload ? 'متصل' : pulseLoading ? 'جاري…' : 'غير متاح' }}
      </span>
    </div>
    <div
      v-if="showPlatformCompaniesEmptyCallout"
      class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-relaxed text-sky-950 dark:border-sky-800/60 dark:bg-sky-950/35 dark:text-sky-100"
    >
      <p class="mb-1 font-semibold">اتصال واجهة المشتركين يعمل لكن لا توجد شركات مسجّلة (0).</p>
      <p class="mb-2 text-[13px] opacity-90">
        غالباً الخادم الذي يستقبل <span class="font-mono text-xs" dir="ltr">/api/v1</span> ليس نفس قاعدة البيانات التي شغّلت عليها البذور، أو لم تُشغَّل البذور بعد.
      </p>
      <p class="text-[12px] font-mono bg-white/70 dark:bg-slate-900/60 rounded-lg px-2 py-1.5 overflow-x-auto" dir="ltr">
        docker compose exec app php artisan db:seed --force
      </p>
    </div>

    <details
      v-if="isPlatform"
      class="mb-5 rounded-2xl border border-slate-200/90 bg-white/90 shadow-sm dark:border-slate-700 dark:bg-slate-900/70"
    >
      <summary
        class="cursor-pointer list-none px-4 py-3 marker:hidden [&::-webkit-details-marker]:hidden"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm font-semibold text-slate-900 dark:text-white">دليل السياق: المنصة مقابل فريق العمل</span>
          <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400">اضغط للتوسيع</span>
        </div>
      </summary>
      <div class="space-y-4 border-t border-slate-100 px-4 pb-4 pt-3 text-[12px] leading-relaxed text-slate-700 dark:border-slate-800 dark:text-slate-300">
        <div class="rounded-xl border border-emerald-200/70 bg-emerald-50/40 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
          <p class="font-semibold text-emerald-950 dark:text-emerald-100">أين الفواتير والموظفون؟</p>
          <p class="mt-1.5">
            مسار المنصة يعرض المشتركين والباقات والنبض؛ التحصيل والقيود داخل
            <strong class="text-slate-900 dark:text-white">بوابة فريق العمل</strong> لكل شركة.
          </p>
          <div class="mt-2 flex flex-wrap gap-2">
            <PlatformOperationsExitLink
              v-for="link in staffPortalLinks"
              :key="'ov-' + link.to"
              :to="link.to"
              v-bind="{ ariaName: link.label }"
              :icon="link.icon"
              dense
              class="max-w-[11rem] shrink-0"
            >
              {{ link.label }}
            </PlatformOperationsExitLink>
          </div>
        </div>
        <div>
          <p class="font-semibold text-slate-900 dark:text-white">الإيرادات والتحليلات</p>
          <p class="mt-1">
            الأرقام في الملخص مرجعية من كتالوج الباقات وليست إيراداً محصّلاً. للتحليلات التشغيلية:
            <PlatformOperationsExitLink to="/reports" v-bind="{ ariaName: 'التقارير' }" variant="inline">التقارير</PlatformOperationsExitLink>
            و
            <PlatformOperationsExitLink to="/business-intelligence" v-bind="{ ariaName: 'ذكاء الأعمال' }" variant="inline">ذكاء الأعمال</PlatformOperationsExitLink>
            .
          </p>
          <p class="mt-2 text-[11px] text-slate-600 dark:text-slate-400">
            أوامر الخادم للنسخ:
            <button
              type="button"
              class="mr-1 font-bold text-primary-700 underline underline-offset-2 hover:text-primary-900 dark:text-primary-400"
              @click="emit('go-section', 'operator-commands')"
            >
              أوامر المشغّل
            </button>
          </p>
        </div>
      </div>
    </details>

    <div
      v-if="isPlatform"
      id="platform-overview-analytics"
      class="scroll-mt-28 mb-6"
    >
      <div
        v-if="platformOverviewLoading"
        class="rounded-2xl border border-slate-200/90 bg-slate-50/80 px-4 py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400"
      >
        جاري تحميل الرسوم والتحليلات…
      </div>
      <div v-else class="space-y-5">
        <div class="grid gap-5 lg:grid-cols-12">
          <div
            class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm ring-1 ring-slate-100/80 dark:border-slate-800 dark:bg-slate-900 dark:ring-slate-800/60 lg:col-span-8"
          >
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                  ملخص أسبوعي — أوامر العمل والشركات الجديدة
                </h3>
                <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                  من تقرير نبض المنصة؛ محوران لأن حجم أوامر العمل يختلف عن عدد الشركات.
                </p>
              </div>
              <span
                v-if="pulseReportPeriod"
                class="shrink-0 rounded-lg bg-slate-50 px-2 py-1 text-[10px] font-mono text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-600"
                dir="ltr"
              >{{ pulseReportPeriod }}</span>
            </div>
            <div v-if="!hasWeeklyTrend" class="flex min-h-[220px] items-center justify-center px-4 text-center text-sm text-slate-500 dark:text-slate-400">
              لا توجد دفعات زمنية كافية في النطاق الحالي، أو تعذّر تحميل نبض المنصة. سيظهر المخطط تلقائياً عند توفر بيانات أسبوعية.
            </div>
            <div v-else class="relative h-[260px] w-full">
              <Line
                :key="'plt-trend-' + chartThemeKey"
                :data="platformTrendLineData"
                :options="platformTrendLineOptions"
              />
            </div>
          </div>

          <div
            class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm ring-1 ring-slate-100/80 dark:border-slate-800 dark:bg-slate-900 dark:ring-slate-800/60 lg:col-span-4"
          >
            <h3 class="mb-1 border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-white">
              توزيع الباقات (المحمّل)
            </h3>
            <p v-if="planDistribution.length === 0" class="mt-4 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
              لا توجد شركات في الصفحة الحالية لرسم التوزيع.
            </p>
            <template v-else>
              <div class="relative mx-auto mt-2 h-[200px] w-full max-w-[280px]">
                <Doughnut
                  :key="'plt-plan-' + chartThemeKey"
                  :data="planDoughnutData"
                  :options="planDoughnutOptions"
                />
              </div>
              <PlatformInsightCard
                class="mt-3"
                eyebrow="قراءة التوزيع"
                title="توزيع الباقات في عيّنة الصفحة الحالية"
                :badge="`${planDistribution.length.toLocaleString('ar-SA')} باقة`"
                tone="default"
                :why="planDistributionSummary"
                meaning="الرسم يعطي الصورة السريعة؛ التفاصيل التعاقدية تُراجع من جدول المشتركين أو مالية المنصة."
                recommendation="استخدم التصفية حسب الباقة عند ضبط التسعير أو متابعة الترقيات."
                cta-label="عرض المشتركين مع التصفية"
                cta-to="/platform/companies"
              />
            </template>
          </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
          <div
            class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            :class="{ 'lg:col-span-2': !hasSubscriptionMixChart }"
          >
            <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">
              مكوّن الشركات (إجمالي المنصة)
            </h3>
            <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">
              تشغيل طبيعي مقابل موقوفة مقابل الحالات الأخرى — من ملخص النبض.
            </p>
            <div v-if="!hasCompanyMixChart" class="flex min-h-[140px] items-center text-sm text-slate-500 dark:text-slate-400">
              غير متاح بدون بيانات ملخص المنصة.
            </div>
            <div v-else class="relative h-[160px] w-full">
              <Bar
                :key="'plt-co-' + chartThemeKey"
                :data="companyMixBarData"
                :options="companyMixBarOptions"
              />
            </div>
          </div>
          <div
            v-if="hasSubscriptionMixChart"
            class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
          >
            <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">
              الاشتراكات حسب الحالة
            </h3>
            <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">
              عدد الاشتراكات لكل حالة مسجّلة في قاعدة المنصة.
            </p>
            <div class="relative h-[160px] w-full">
              <Bar
                :key="'plt-sub-' + chartThemeKey"
                :data="subscriptionMixBarData"
                :options="subscriptionMixBarOptions"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      id="platform-overview-pulse"
      class="scroll-mt-28 mb-6 rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-slate-50/80 to-primary-50/40 p-5 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-primary-900/25 dark:shadow-none"
    >
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 class="mb-1 flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
            <SparklesIcon class="h-5 w-5 text-primary-600 dark:text-primary-400" />
            نبض المنصة — مؤشرات موحّدة
          </h3>
          <p class="text-xs text-slate-600 dark:text-slate-400">
            يُستخرج من تقرير نبض المنصة الموحّد عند توفر الصلاحية؛ وإلا تُشتق الأعداد من قائمة المشتركين المحمّلة.
          </p>
        </div>
        <span v-if="pulseReportPeriod" class="rounded-lg bg-white/80 px-2 py-1 text-[10px] font-mono text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-600" dir="ltr">{{ pulseReportPeriod }}</span>
      </div>
      <div v-if="pulsePayload?.breakdown?.by_activity" class="mt-4 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-slate-700 dark:bg-slate-800/50">
          <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">شركات جديدة (الفترة)</div>
          <div class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">
            {{ Number(pulsePayload.breakdown.by_activity.companies_registered_in_period ?? 0).toLocaleString('ar-SA') }}
          </div>
        </div>
        <div class="rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-slate-700 dark:bg-slate-800/50">
          <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">أوامر عمل جديدة (الفترة)</div>
          <div class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">
            {{ Number(pulsePayload.breakdown.by_activity.work_orders_created_in_period ?? 0).toLocaleString('ar-SA') }}
          </div>
        </div>
      </div>
      <p class="mt-4 flex flex-wrap items-baseline gap-x-1 gap-y-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
        <span>للتحليل التفصيلي داخل المستأجر استخدم</span>
        <PlatformOperationsExitLink to="/reports" v-bind="{ ariaName: 'التقارير' }" variant="inline">التقارير</PlatformOperationsExitLink>
        <span>و</span>
        <PlatformOperationsExitLink to="/business-intelligence" v-bind="{ ariaName: 'ذكاء الأعمال' }" variant="inline">ذكاء الأعمال</PlatformOperationsExitLink>
        <span>(صلاحيات المستأجر). مسار</span>
        <PlatformOperationsExitLink to="/operations/global-feed" v-bind="{ ariaName: 'تدفق العمليات اليومي' }" variant="inline">تدفق العمليات</PlatformOperationsExitLink>
        <span>لمتابعة التشغيل اليومي.</span>
      </p>
    </div>

    <div
      id="platform-overview-recent"
      class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/20 dark:shadow-none"
    >
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-5 dark:border-slate-800">
        <div>
          <h3 class="text-sm font-semibold text-slate-900 dark:text-white">أحدث المشتركين في العيّنة المحمّلة</h3>
          <p class="mt-1 max-w-xl text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
            ما يحدث: لقطة سريعة لآخر المنضمّين — للتنقّل التفصيلي استخدم جدول المشتركين الكامل.
          </p>
        </div>
        <button
          v-if="companiesFeedOk"
          type="button"
          class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-[11px] font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
          @click="emit('go-section', 'tenants')"
        >
          عرض جدول المشتركين
        </button>
      </div>
      <table class="w-full text-sm">
        <caption v-if="platformOverviewLoading" class="sr-only">جاري تحميل قائمة المشتركين</caption>
        <thead class="bg-slate-50/90 dark:bg-slate-900/50">
          <tr>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الشركة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الباقة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الاشتراك</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الإيراد الشهري</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">حالة الشركة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الانضمام</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <template v-if="platformOverviewLoading">
            <tr v-for="sk in 5" :key="'ov-sk-' + sk">
              <td v-for="col in 6" :key="'ov-sk-' + sk + '-' + col" class="px-4 py-3">
                <div
                  class="h-4 animate-pulse rounded bg-slate-200/90 dark:bg-slate-700/80"
                  :class="col === 1 ? 'w-[85%]' : col === 2 ? 'w-[55%]' : 'w-[45%]'"
                />
              </td>
            </tr>
          </template>
          <tr v-else-if="recentCompanies.length === 0">
            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-gray-400 leading-relaxed">
              لا يوجد مشتركون في المنصة حتى الآن — القائمة تأتي من الخادم وليست وهمية.
              أنشئ شركة من مسار التسجيل العادي، أو انتقل من القائمة الجانبية إلى «المشتركون»، أو شغّل بذور قاعدة البيانات التجريبية إن وُجدت في المشروع.
            </td>
          </tr>
          <tr
            v-for="c in recentCompanies"
            :key="c.id"
            class="cursor-pointer transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-800/30"
            @click="emit('open-tenant', c)"
          >
            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ c.name }}</td>
            <td class="px-4 py-3">
              <PlanBadge :plan="c.plan_slug" :label="planDisplayName(c.plan_slug, c.plan_name)" />
            </td>
            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-300">{{ subscriptionStatusLabel(c.subscription_status) }}</td>
            <td class="px-4 py-3 font-medium text-emerald-700 dark:text-emerald-400">{{ formatCurrency(Number(c.monthly_revenue) || 0) }}</td>
            <td class="px-4 py-3 text-xs">{{ companyRowStatusLabel(c) }}</td>
            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ formatDate(c.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import {
  ChartBarIcon,
  BanknotesIcon,
  ClipboardDocumentListIcon,
  SparklesIcon,
  HomeIcon,
  DocumentTextIcon,
  UsersIcon,
  UserGroupIcon,
  PresentationChartLineIcon,
} from '@heroicons/vue/24/outline'
import { useDarkMode } from '@/composables/useDarkMode'
import PlatformAdminExecutivePanel from '@/components/platform-admin/PlatformAdminExecutivePanel.vue'
import PlatformAttentionNowSection from '@/components/platform-admin/sections/PlatformAttentionNowSection.vue'
import PlatformOperationsExitLink from '@/components/platform-admin/PlatformOperationsExitLink.vue'
import PlanBadge from '@/components/platform-admin/PlatformPlanBadge.vue'
import PlatformInsightCard from '@/components/platform-admin/ui/PlatformInsightCard.vue'
import type { PlatformAdminSectionId } from '@/config/platformAdminNav'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Filler,
)

type PulseWeeklyBucket = { period_start: string; count: number }
type PulseStatusRow = { status: string; count: number }

export type OverviewPulsePayload = {
  summary?: Record<string, number>
  breakdown?: {
    by_activity?: Record<string, number>
    by_time_period?: {
      granularity?: string
      work_orders?: PulseWeeklyBucket[]
      companies?: PulseWeeklyBucket[]
    }
    by_status?: {
      companies?: PulseStatusRow[]
      subscriptions?: PulseStatusRow[]
      support_tickets?: PulseStatusRow[]
    }
  }
} | null

const props = defineProps<{
  isPlatform: boolean
  executiveOverviewTick: number
  platformBanner: string
  companiesFeedOk: boolean
  companiesTotalCount: number
  companiesPageLength: number
  plansLoadOk: boolean
  pulsePayload: OverviewPulsePayload
  pulseLoading: boolean
  pulseReportPeriod: string
  showPlatformCompaniesEmptyCallout: boolean
  platformOverviewLoading: boolean
  companies: any[]
  planDisplayName: (slug: string, planNameFromApi?: string) => string
  /** تلميح مختصر من محرك التفسير المالي (يُمرَّر من لوحة المنصة) */
  financeWhyEngineTeaser?: string
}>()

const emit = defineEmits<{
  'go-section': [id: PlatformAdminSectionId]
  'open-tenant': [row: any]
}>()

const darkMode = useDarkMode()

const staffPortalLinks = [
  { to: '/', label: 'لوحة التحكم', icon: HomeIcon },
  { to: '/invoices', label: 'الفواتير', icon: DocumentTextIcon },
  { to: '/ledger', label: 'دفتر الأستاذ', icon: BanknotesIcon },
  { to: '/chart-of-accounts', label: 'دليل الحسابات', icon: ClipboardDocumentListIcon },
  { to: '/reports', label: 'التقارير', icon: ChartBarIcon },
  { to: '/business-intelligence', label: 'ذكاء الأعمال', icon: PresentationChartLineIcon },
  { to: '/customers', label: 'العملاء', icon: UsersIcon },
  { to: '/workshop/employees', label: 'الموظفون', icon: UserGroupIcon },
]

const recentCompanies = computed(() => props.companies.slice(0, 8))

const planDistribution = computed(() => {
  const list = props.companies
  const total = Math.max(list.length, 1)
  const slugs = [...new Set(list.map((c) => c.plan_slug || '—'))]
  if (slugs.length === 0) {
    return []
  }
  return slugs.map((slug) => {
    const count = list.filter((c) => (c.plan_slug || '—') === slug).length
    const sample = list.find((c) => (c.plan_slug || '—') === slug)
    const label = props.planDisplayName(slug, sample?.plan_name)

    return {
      slug,
      label,
      count,
      pct: Math.round((count / total) * 100),
    }
  })
})

const planDistributionSummary = computed(() => {
  const rows = planDistribution.value
  if (rows.length === 0) {
    return 'لا توجد شركات في الصفحة الحالية لحساب التوزيع.'
  }
  const parts = rows
    .slice(0, 5)
    .map((r) => `${r.label}: ${r.count.toLocaleString('ar-SA')} (${r.pct}٪)`)
  const suffix = rows.length > 5 ? ` … و${(rows.length - 5).toLocaleString('ar-SA')} باقة أخرى` : ''
  return `الخليط الحالي: ${parts.join(' · ')}${suffix}.`
})

function formatWeekBucketLabel(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso.slice(0, 10)

  return d.toLocaleDateString('ar-SA', { month: 'numeric', day: 'numeric' })
}

const mergedWeeklyTrend = computed(() => {
  const tp = props.pulsePayload?.breakdown?.by_time_period
  const wo = tp?.work_orders ?? []
  const co = tp?.companies ?? []
  const keys = new Set<string>()
  wo.forEach((b) => {
    if (b.period_start) keys.add(b.period_start)
  })
  co.forEach((b) => {
    if (b.period_start) keys.add(b.period_start)
  })
  const sorted = [...keys].sort((a, b) => new Date(a).getTime() - new Date(b).getTime())
  const woMap = Object.fromEntries(wo.map((b) => [b.period_start, b.count]))
  const coMap = Object.fromEntries(co.map((b) => [b.period_start, b.count]))

  return {
    labels: sorted.map(formatWeekBucketLabel),
    workOrders: sorted.map((k) => Number(woMap[k] ?? 0)),
    companies: sorted.map((k) => Number(coMap[k] ?? 0)),
  }
})

const hasWeeklyTrend = computed(() => mergedWeeklyTrend.value.labels.length > 0)

const chartThemeKey = computed(() => (darkMode.isDark.value ? 'd' : 'l'))

const platformTrendLineData = computed(() => {
  const m = mergedWeeklyTrend.value

  return {
    labels: m.labels,
    datasets: [
      {
        label: 'أوامر عمل جديدة',
        data: m.workOrders,
        yAxisID: 'y',
        borderColor: 'rgb(139, 92, 246)',
        backgroundColor: 'rgba(139, 92, 246, 0.1)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 5,
      },
      {
        label: 'شركات جديدة',
        data: m.companies,
        yAxisID: 'y1',
        borderColor: 'rgb(13, 148, 136)',
        backgroundColor: 'rgba(13, 148, 136, 0.08)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 5,
      },
    ],
  }
})

const platformTrendLineOptions = computed(() => {
  const d = darkMode.isDark.value
  const tick = d ? '#94a3b8' : '#64748b'
  const grid = d ? 'rgba(148,163,184,0.14)' : 'rgba(100,116,139,0.12)'

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
      legend: {
        position: 'bottom' as const,
        rtl: true,
        labels: { color: tick, boxWidth: 10, padding: 14, font: { size: 11 } },
      },
      tooltip: {
        rtl: true,
        titleAlign: 'right' as const,
        bodyAlign: 'right' as const,
      },
    },
    scales: {
      x: {
        ticks: { color: tick, maxRotation: 40, autoSkip: true, maxTicksLimit: 16 },
        grid: { color: grid },
      },
      y: {
        type: 'linear' as const,
        display: true,
        position: 'right' as const,
        beginAtZero: true,
        title: { display: true, text: 'أوامر العمل', color: tick, font: { size: 10 } },
        ticks: { color: tick },
        grid: { color: grid },
      },
      y1: {
        type: 'linear' as const,
        display: true,
        position: 'left' as const,
        beginAtZero: true,
        title: { display: true, text: 'شركات', color: tick, font: { size: 10 } },
        ticks: { color: tick },
        grid: { drawOnChartArea: false },
      },
    },
  }
})

const PLAN_CHART_COLORS = [
  'rgba(139, 92, 246, 0.88)',
  'rgba(45, 212, 191, 0.88)',
  'rgba(251, 191, 36, 0.9)',
  'rgba(56, 189, 248, 0.88)',
  'rgba(244, 63, 94, 0.82)',
  'rgba(100, 116, 139, 0.85)',
]

const planDoughnutData = computed(() => {
  const rows = planDistribution.value

  return {
    labels: rows.map((r) => r.label),
    datasets: [
      {
        data: rows.map((r) => r.count),
        backgroundColor: rows.map((_, i) => PLAN_CHART_COLORS[i % PLAN_CHART_COLORS.length]),
        borderWidth: 2,
        borderColor: darkMode.isDark.value ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.95)',
      },
    ],
  }
})

const planDoughnutOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '58%',
  plugins: {
    legend: { display: false },
    tooltip: {
      rtl: true,
      callbacks: {
        label(ctx: { dataset: { data: number[] }; parsed: number; label: string }) {
          const data = ctx.dataset.data
          const total = data.reduce((a, b) => a + Number(b), 0)
          const v = Number(ctx.parsed)
          const pct = total > 0 ? Math.round((v / total) * 100) : 0

          return ` ${ctx.label}: ${v.toLocaleString('ar-SA')} (${pct}٪)`
        },
      },
    },
  },
}))

const subscriptionBreakdownRows = computed(() => {
  const rows = props.pulsePayload?.breakdown?.by_status?.subscriptions
  if (!Array.isArray(rows)) return []

  return rows.filter((r) => Number(r.count) > 0)
})

const hasSubscriptionMixChart = computed(() => subscriptionBreakdownRows.value.length > 0)

function subscriptionStatusArLabel(s: string): string {
  const m: Record<string, string> = {
    active: 'نشط',
    trialing: 'تجريبي',
    canceled: 'ملغى',
    cancelled: 'ملغى',
    past_due: 'متأخر السداد',
    unpaid: 'غير مسدد',
    incomplete: 'غير مكتمل',
    incomplete_expired: 'منتهي غير مكتمل',
    paused: 'متوقف',
  }

  return m[String(s).toLowerCase()] ?? s
}

const subscriptionMixBarData = computed(() => {
  const rows = subscriptionBreakdownRows.value

  return {
    labels: rows.map((r) => subscriptionStatusArLabel(r.status)),
    datasets: [
      {
        label: 'اشتراكات',
        data: rows.map((r) => r.count),
        backgroundColor: rows.map((_, i) => `hsla(${248 - i * 38}, 68%, 52%, 0.75)`),
        borderColor: rows.map((_, i) => `hsl(${248 - i * 38}, 68%, 42%)`),
        borderWidth: 1,
      },
    ],
  }
})

const subscriptionMixBarOptions = computed(() => {
  const d = darkMode.isDark.value
  const tick = d ? '#94a3b8' : '#64748b'
  const grid = d ? 'rgba(148,163,184,0.12)' : 'rgba(100,116,139,0.1)'

  return {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { color: tick }, grid: { color: grid } },
      y: { ticks: { color: tick }, grid: { display: false } },
    },
  }
})

const companyMixBarData = computed(() => {
  const s = props.pulsePayload?.summary
  if (!s) {
    return { labels: [] as string[], datasets: [] as { label: string; data: number[]; backgroundColor: string[]; borderColor: string[]; borderWidth: number }[] }
  }
  const op = Number(s.companies_operational ?? 0)
  const sus = Number(s.companies_suspended ?? 0)
  const oth = Number(s.companies_other ?? 0)

  return {
    labels: ['تشغيل طبيعي', 'موقوفة', 'أخرى'],
    datasets: [
      {
        label: 'عدد الشركات',
        data: [op, sus, oth],
        backgroundColor: [
          'rgba(16, 185, 129, 0.78)',
          'rgba(251, 146, 60, 0.82)',
          'rgba(148, 163, 184, 0.78)',
        ],
        borderColor: ['rgb(5, 150, 105)', 'rgb(234, 88, 12)', 'rgb(71, 85, 105)'],
        borderWidth: 1,
      },
    ],
  }
})

const hasCompanyMixChart = computed(() => {
  const s = props.pulsePayload?.summary
  if (!s) return false
  const sum = Number(s.companies_operational ?? 0) + Number(s.companies_suspended ?? 0) + Number(s.companies_other ?? 0)

  return sum > 0
})

const companyMixBarOptions = computed(() => {
  const d = darkMode.isDark.value
  const tick = d ? '#94a3b8' : '#64748b'
  const grid = d ? 'rgba(148,163,184,0.12)' : 'rgba(100,116,139,0.1)'

  return {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { color: tick }, grid: { color: grid } },
      y: { ticks: { color: tick }, grid: { display: false } },
    },
  }
})

function subscriptionStatusLabel(status: string | null | undefined): string {
  const s = String(status ?? '').toLowerCase()
  const map: Record<string, string> = {
    active: 'نشط',
    grace_period: 'فترة سماح',
    suspended: 'موقوف',
  }

  return map[s] || (s ? s : '—')
}

function companyRowStatusLabel(c: any): string {
  if (c.company_status === 'suspended') return 'شركة موقوفة'
  if (c.is_active === false) return 'غير مفعّلة'
  if (c.company_status === 'inactive') return 'غير نشطة'

  return c.is_active ? 'تشغيل طبيعي' : '—'
}

function formatCurrency(v: number): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  const x = new Date(d)

  return Number.isNaN(x.getTime()) ? '—' : x.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}
</script>
