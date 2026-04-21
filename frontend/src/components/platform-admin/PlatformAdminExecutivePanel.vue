<template>
  <section v-if="auth.isPlatform" class="mb-6 rounded-2xl border border-violet-200/90 bg-gradient-to-br from-white via-violet-50/40 to-slate-50/90 p-4 shadow-sm dark:border-violet-900/50 dark:from-slate-900 dark:via-violet-950/20 dark:to-slate-950" dir="rtl">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-violet-100/80 pb-3 dark:border-violet-900/40">
      <div>
        <h2 class="text-base font-black text-slate-900 dark:text-white sm:text-lg">لوحة قيادة منصة أسس برو</h2>
        <p class="mt-1 text-[12px] text-slate-600 dark:text-slate-400">مركز قيادة أسس برو لإدارة المنصة — قراءة فقط</p>
        <p v-if="payload" class="mt-1 font-mono text-[10px] text-violet-700 dark:text-violet-400" dir="ltr">{{ payload.generated_at }}</p>
      </div>
      <PlatformQuickActions @refresh="reload" />
    </div>

    <p v-if="error" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">{{ error }}</p>
    <div v-if="loading && !payload" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"><div v-for="s in 8" :key="s" class="h-20 animate-pulse rounded-xl bg-white/80 dark:bg-slate-800/60" /></div>

    <template v-else-if="payload">
      <div class="mt-4 rounded-xl border border-violet-200/70 bg-white/80 p-3 dark:border-violet-900/50 dark:bg-slate-900/40">
        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">سياسة المنصة لإظهار الأقسام</h3>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">تتحكم بما يمكن للشركات إظهاره لاحقًا لمستخدميها.</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <label v-for="k in sectionKeys" :key="`sec-${k}`" class="flex items-center gap-2 text-xs">
            <input v-model="platformNav.sections[k]" type="checkbox" class="rounded" />
            <span>{{ sectionLabels[k] }}</span>
          </label>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <label v-for="k in groupKeys" :key="`grp-${k}`" class="flex items-center gap-2 text-xs">
            <input v-model="platformNav.groups[k]" type="checkbox" class="rounded" />
            <span>{{ groupLabels[k] }}</span>
          </label>
        </div>
        <div class="mt-3 flex justify-end">
          <button
            type="button"
            class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700 disabled:opacity-60"
            :disabled="savingPolicy"
            @click="savePlatformPolicy"
          >
            {{ savingPolicy ? 'جاري الحفظ...' : 'حفظ سياسة المنصة' }}
          </button>
        </div>
      </div>
      <PlatformHealth :health="payload.health" />
      <PlatformKpis :kpis="payload.kpis" :definitions="payload.definitions" />
      <PlatformCharts :trends="payload.trends" :distribution="payload.distribution" />
      <PlatformActivityIntel :activity="payload.activity" />
      <PlatformAlerts :alerts="payload.alerts" />
      <PlatformInsights :insights="payload.insights" />
      <div class="mt-4 overflow-hidden rounded-xl border border-amber-200/80 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20">
        <div class="border-b border-amber-200/60 bg-amber-100/50 px-3 py-2 text-xs font-bold text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">شركات تحتاج متابعة</div>
        <p v-if="!payload.companies_requiring_attention?.length" class="px-3 py-4 text-center text-[11px] text-slate-600 dark:text-slate-400">لا توجد شركات في قائمة المتابعة حالياً.</p>
        <table v-else class="min-w-full text-right text-[11px]">
          <thead class="bg-white/95 text-slate-500 dark:bg-slate-900/95 dark:text-slate-400"><tr><th class="p-2">الشركة</th><th class="p-2">الملاحظة</th><th class="p-2">إجراء</th></tr></thead>
          <tbody>
            <tr v-for="c in payload.companies_requiring_attention" :key="c.company_id" class="border-t border-amber-100/80 dark:border-amber-900/30">
              <td class="p-2 font-semibold text-slate-900 dark:text-white">{{ c.name }}</td>
              <td class="p-2 text-slate-700 dark:text-slate-300">{{ c.reason }}<template v-if="c.action_hint"> · {{ c.action_hint }}</template></td>
              <td class="p-2"><RouterLink :to="c.action_path" class="text-violet-700 underline dark:text-violet-400">فتح</RouterLink></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import type { PlatformAdminOverviewPayload } from '@/types/platformAdminOverview'
import PlatformKpis from '@/components/platform/PlatformKpis.vue'
import PlatformCharts from '@/components/platform/PlatformCharts.vue'
import PlatformAlerts from '@/components/platform/PlatformAlerts.vue'
import PlatformInsights from '@/components/platform/PlatformInsights.vue'
import PlatformHealth from '@/components/platform/PlatformHealth.vue'
import PlatformActivityIntel from '@/components/platform/PlatformActivityIntel.vue'
import PlatformQuickActions from '@/components/platform/PlatformQuickActions.vue'
import {
  DEFAULT_NAV_VISIBILITY,
  NAV_GROUP_LABELS,
  NAV_SECTION_LABELS,
  type NavVisibilityPolicy,
} from '@/config/navigationVisibility'

const props = defineProps<{
  refreshTick?: number
}>()

const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const payload = ref<PlatformAdminOverviewPayload | null>(null)
const sectionLabels = NAV_SECTION_LABELS
const groupLabels = NAV_GROUP_LABELS
const sectionKeys = Object.keys(NAV_SECTION_LABELS)
const groupKeys = Object.keys(NAV_GROUP_LABELS)
const platformNav = ref<NavVisibilityPolicy>(JSON.parse(JSON.stringify(DEFAULT_NAV_VISIBILITY)))
const savingPolicy = ref(false)

async function reload(): Promise<void> {
  if (!auth.isPlatform) return
  loading.value = true
  error.value = ''
  try {
    const { data: body } = await apiClient.get<{ data: PlatformAdminOverviewPayload }>('/admin/overview', {
      skipGlobalErrorToast: true,
    })
    payload.value = body.data
    const { data: navRes } = await apiClient.get('/platform/navigation-visibility', { skipGlobalErrorToast: true })
    platformNav.value = navRes?.data ?? JSON.parse(JSON.stringify(DEFAULT_NAV_VISIBILITY))
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    error.value = typeof msg === 'string' && msg.length > 0 ? msg : 'تعذّر تحميل لوحة قيادة المنصة (تحقق من الصلاحية platform.companies.read).'
    payload.value = null
  } finally {
    loading.value = false
  }
}

async function savePlatformPolicy(): Promise<void> {
  savingPolicy.value = true
  try {
    const { data } = await apiClient.patch('/platform/navigation-visibility', platformNav.value, {
      skipGlobalErrorToast: true,
    })
    platformNav.value = data?.data ?? platformNav.value
  } finally {
    savingPolicy.value = false
  }
}

onMounted(() => {
  if (auth.isPlatform) void reload()
})

watch(
  () => props.refreshTick,
  () => {
    if (auth.isPlatform) void reload()
  },
)

watch(
  () => auth.isPlatform,
  (v) => {
    if (v) void reload()
  },
)
</script>
