<template>
  <div class="mt-4">
    <div class="mb-3 border-b border-slate-100 pb-3 dark:border-slate-800">
      <h3 class="text-sm font-semibold text-slate-900 dark:text-white">ذكاء النشاط (آخر 7 أيام)</h3>
      <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        ما يحدث: مقارنة أعلى وأدنى النشاط بين المشتركين. متوسط درجة النشاط:
        <span class="font-mono font-medium text-primary-700 tabular-nums dark:text-primary-400">{{ avgLabel }}</span>
      </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="space-y-3">
        <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-800 dark:text-emerald-300">زخم قوي</p>
        <PlatformInsightCard
          v-for="row in mostCards"
          :key="'m-' + row.company_id"
          :eyebrow="row.eyebrow"
          :title="row.title"
          :badge="row.badge"
          tone="positive"
          :why="row.why"
          :meaning="row.meaning"
          :recommendation="row.recommendation"
          :cta-label="row.ctaLabel"
          :cta-to="row.ctaTo"
        />
        <p v-if="!mostCards.length" class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
          لا توجد بيانات كافية لعرض بطاقات النشاط المرتفع.
        </p>
      </div>

      <div class="space-y-3">
        <p class="text-[10px] font-medium uppercase tracking-wide text-amber-900 dark:text-amber-200">يحتاج انتباهاً</p>
        <PlatformInsightCard
          v-for="row in leastCards"
          :key="'l-' + row.company_id"
          :eyebrow="row.eyebrow"
          :title="row.title"
          :badge="row.badge"
          tone="warning"
          :why="row.why"
          :meaning="row.meaning"
          :recommendation="row.recommendation"
          :cta-label="row.ctaLabel"
          :cta-to="row.ctaTo"
        />
        <p v-if="!leastCards.length" class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
          لا توجد بيانات كافية لعرض بطاقات التباطؤ.
        </p>
      </div>
    </div>

    <div class="mt-4 flex justify-end border-t border-slate-100 pt-3 dark:border-slate-800">
      <RouterLink
        to="/platform/companies"
        class="text-[11px] font-medium text-primary-700 underline-offset-2 transition hover:text-primary-900 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
      >
        عرض المشتركين وتصفية النشاط
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { PlatformAdminActivityRow, PlatformAdminOverviewPayload } from '@/types/platformAdminOverview'
import PlatformInsightCard from '@/components/platform-admin/ui/PlatformInsightCard.vue'

const props = defineProps<{
  activity: PlatformAdminOverviewPayload['activity']
}>()

const most = computed(() => props.activity.most_active_companies ?? [])
const least = computed(() => props.activity.least_active_companies ?? [])

const avgLabel = computed(() => {
  const n = props.activity.avg_activity_score
  return typeof n === 'number' && !Number.isNaN(n) ? n.toLocaleString('ar-SA', { maximumFractionDigits: 2 }) : '—'
})

function daysLabel(days: number): string {
  if (days >= 999) return 'لا يوجد تسجيل'
  return `منذ ${days.toLocaleString('ar-SA')} يوماً`
}

type CardVm = {
  company_id: number
  eyebrow: string
  title: string
  badge: string
  why: string
  meaning: string
  recommendation: string
  ctaLabel: string
  ctaTo: string
}

function mapMost(row: PlatformAdminActivityRow): CardVm {
  const score = row.activity_score.toLocaleString('ar-SA')
  return {
    company_id: row.company_id,
    eyebrow: 'نمو قوي في الاستخدام',
    title: row.company_name,
    badge: `درجة ${score}`,
    why: `زخم تشغيلي مرتفع في نافذة 7 أيام؛ آخر نشاط مسجّل ${daysLabel(row.last_activity_days_ago)}.`,
    meaning: 'فرصة لرفع الإيراد عبر التحصيل والترقية عند توافق نموذج الفوترة.',
    recommendation: 'راجع الفواتير وخطط الباقة لهذا المستأجر قبل نهاية الدورة.',
    ctaLabel: 'فتح ملف الشركة',
    ctaTo: `/platform/companies/${row.company_id}`,
  }
}

function mapLeast(row: PlatformAdminActivityRow): CardVm {
  const score = row.activity_score.toLocaleString('ar-SA')
  return {
    company_id: row.company_id,
    eyebrow: 'تباطؤ أو ضعف تفاعل',
    title: row.company_name,
    badge: `درجة ${score}`,
    why: `انخفاض النشاط مقارنة بالمتوسط؛ آخر نشاط ${daysLabel(row.last_activity_days_ago)}.`,
    meaning: 'مؤشر مبكر لضعف التبني أو حاجة لتدخل تشغيلي/تجاري.',
    recommendation: 'جدولة متابعة قصيرة ومراجعة حالة الاشتراك والدعم.',
    ctaLabel: 'فتح ملف الشركة',
    ctaTo: `/platform/companies/${row.company_id}`,
  }
}

const mostCards = computed(() => most.value.map(mapMost))
const leastCards = computed(() => least.value.map(mapLeast))
</script>
