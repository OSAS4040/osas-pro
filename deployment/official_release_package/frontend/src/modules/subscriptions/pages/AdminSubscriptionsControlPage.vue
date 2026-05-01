<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-xl font-bold">مؤشرات الاشتراكات</h1>
      <RouterLink class="text-sm font-semibold text-primary-700 hover:underline" :to="{ name: 'admin-subscriptions-list' }">
        قائمة الاشتراكات التفصيلية
      </RouterLink>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">اشتراكات نشطة</p>
        <p class="text-xl font-semibold">{{ overview.subscription_status_counts?.active || 0 }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">متأخر سداد</p>
        <p class="text-xl font-semibold">{{ overview.subscription_status_counts?.past_due || 0 }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">موقوف / منتهي</p>
        <p class="text-xl font-semibold">
          {{ (overview.subscription_status_counts?.suspended || 0) + (overview.subscription_status_counts?.expired || 0) }}
        </p>
      </article>
    </div>

    <div class="grid md:grid-cols-4 gap-4">
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">إيراد اليوم (تقدير)</p>
        <p class="text-lg font-semibold">{{ insights.revenue?.daily_revenue ?? '0' }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">إيراد الشهر (تقدير)</p>
        <p class="text-lg font-semibold">{{ insights.revenue?.monthly_revenue ?? '0' }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">اشتراكات بحالة خطر</p>
        <p class="text-lg font-semibold">{{ riskyCount }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">طلبات بانتظار الاعتماد</p>
        <p class="text-lg font-semibold">{{ overview.payment_order_status_counts?.awaiting_review || 0 }}</p>
      </article>
    </div>

    <article
      v-if="auth.hasPermission('platform.subscription.manage') && attentionSummary"
      class="rounded-xl border border-amber-200 bg-amber-50/90 p-4 dark:border-amber-900/50 dark:bg-amber-950/30"
    >
      <h2 class="mb-2 text-sm font-bold text-amber-950 dark:text-amber-100">طلبات تحتاج تدخلاً الآن</h2>
      <div class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/60">
          <p class="text-slate-500 dark:text-slate-400">بانتظار المراجعة</p>
          <p class="text-lg font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ attentionSummary.awaiting_review }}</p>
        </div>
        <div class="rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/60">
          <p class="text-slate-500 dark:text-slate-400">بانتظار الموافقة النهائية</p>
          <p class="text-lg font-bold tabular-nums text-amber-800 dark:text-amber-200">{{ attentionSummary.matched_pending_final_approval }}</p>
        </div>
        <div class="rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/60">
          <p class="text-slate-500 dark:text-slate-400">تحويل ببيانات مرسلة</p>
          <p class="text-lg font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ attentionSummary.pending_transfer_with_submission }}</p>
        </div>
        <div class="rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/60">
          <p class="text-slate-500 dark:text-slate-400">الإجمالي</p>
          <p class="text-lg font-bold tabular-nums text-primary-800 dark:text-primary-200">{{ attentionSummary.total_attention }}</p>
        </div>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        <RouterLink
          class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-700"
          :to="{ name: 'admin-subscriptions-review' }"
        >
          فتح طابور المراجعة
        </RouterLink>
        <RouterLink class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600" :to="{ name: 'admin-subscriptions-list' }">
          قائمة الاشتراكات
        </RouterLink>
        <RouterLink class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-600" :to="{ name: 'admin-subscriptions-invoices' }">
          فواتير المنصة
        </RouterLink>
      </div>
    </article>

    <article v-if="(insights.risks ?? []).length" class="rounded-xl border p-4 bg-white dark:bg-slate-900">
      <h2 class="font-semibold mb-2">اشتراكات عالية/متوسطة المخاطر</h2>
      <ul class="space-y-2 text-sm">
        <li v-for="r in insights.risks || []" :key="r.subscription_id">
          <RouterLink class="font-semibold text-primary-700 hover:underline" :to="platformCompanyPath(Number(r.company_id))">
            شركة #{{ r.company_id }}
          </RouterLink>
          —
          <RouterLink class="text-primary-700 hover:underline" :to="{ name: 'admin-subscriptions-detail', params: { subscriptionId: r.subscription_id } }">
            اشتراك #{{ r.subscription_id }}
          </RouterLink>
          <span class="text-slate-600 dark:text-slate-300"> — {{ r.risk_level }} ({{ r.status }})</span>
        </li>
      </ul>
    </article>

    <article v-if="walletWarnings.length" class="rounded-xl border p-4 bg-amber-50 dark:bg-amber-900/20">
      <h2 class="font-semibold mb-2">تنبيهات تغطية المحفظة</h2>
      <ul class="space-y-2 text-sm">
        <li v-for="w in walletWarnings" :key="w.subscription_id">
          <RouterLink class="font-semibold text-primary-800 hover:underline dark:text-primary-300" :to="platformCompanyPath(Number(w.company_id))">
            شركة #{{ w.company_id }}
          </RouterLink>
          —
          <RouterLink class="text-primary-800 hover:underline dark:text-primary-300" :to="{ name: 'admin-subscriptions-detail', params: { subscriptionId: w.subscription_id } }">
            اشتراك #{{ w.subscription_id }}
          </RouterLink>
          <span class="text-slate-700 dark:text-slate-200"> — {{ w.message }} (تقدير {{ w.estimated_days_coverage }} يوماً)</span>
        </li>
      </ul>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { subscriptionsApi } from '../api'
import { platformCompanyPath } from '../lib/platformLinks'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const attentionSummary = ref<{
  awaiting_review: number
  matched_pending_final_approval: number
  pending_transfer_with_submission: number
  total_attention: number
} | null>(null)
const overview = ref<any>({})
const insights = ref<any>({})
const riskyCount = computed(() => (insights.value?.risks ?? []).length)
const walletWarnings = computed(() =>
  (insights.value?.wallet_insights ?? []).filter((item: any) => Number(item.estimated_days_coverage ?? 0) < 4),
)
onMounted(async () => {
  const reqs: Promise<unknown>[] = [subscriptionsApi.adminOverview(), subscriptionsApi.adminInsights()]
  if (auth.hasPermission('platform.subscription.manage')) {
    reqs.push(subscriptionsApi.adminSubscriptionAttentionSummary())
  }
  const results = await Promise.all(reqs)
  overview.value = (results[0] as { data?: { data?: unknown } })?.data?.data ?? {}
  insights.value = (results[1] as { data?: { data?: unknown } })?.data?.data ?? {}
  if (auth.hasPermission('platform.subscription.manage') && results[2]) {
    attentionSummary.value = (results[2] as { data?: { data?: typeof attentionSummary.value } })?.data?.data ?? null
  }
})
</script>

