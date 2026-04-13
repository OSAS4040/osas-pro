<template>
  <div class="app-shell-page max-w-5xl space-y-6" dir="rtl">
    <div>
      <h1 class="page-title-xl">{{ lt('title') }}</h1>
      <p class="page-subtitle text-sm mt-1 leading-relaxed">
        {{ lt('intro') }}
      </p>
      <p
        v-if="businessType"
        class="mt-2 inline-flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
      >
        <span class="font-semibold text-gray-500 dark:text-slate-400">{{ lt('bizType') }}</span>
        <span class="font-mono">{{ businessType }}</span>
        <span v-if="businessTypeLabel" class="text-gray-600 dark:text-slate-300">— {{ businessTypeLabel }}</span>
      </p>
    </div>

    <div
      v-if="errorMsg"
      class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100"
      role="alert"
    >
      {{ errorMsg }}
    </div>

    <div v-if="loading" class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-8 text-center text-gray-500">
      {{ lt('loading') }}
    </div>

    <template v-else-if="!errorMsg">
      <section
        v-for="group in grouped"
        :key="group.key"
        class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden"
      >
        <h2 class="text-sm font-bold text-gray-900 dark:text-slate-100 px-4 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50/80 dark:bg-slate-900/50">
          {{ group.label }}
        </h2>
        <ul class="divide-y divide-gray-100 dark:divide-slate-700">
          <li
            v-for="item in group.items"
            :key="item.id"
            class="px-4 py-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
          >
            <div class="min-w-0 flex-1 space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-gray-900 dark:text-slate-100">{{ pickLang(item.title) }}</span>
                <span :class="statusClass(item.status)" class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md">
                  {{ statusLabel(item.status) }}
                </span>
                <span
                  v-if="item.rollout === 'beta'"
                  class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                >
                  Beta
                </span>
              </div>
              <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed">
                {{ pickLang(item.description) }}
              </p>
              <p v-if="reasonText(item)" class="text-xs text-amber-800 dark:text-amber-200/90">
                {{ reasonText(item) }}
              </p>
            </div>
            <div class="flex-shrink-0 sm:pt-0.5">
              <RouterLink
                v-if="item.path && item.status !== 'planned'"
                :to="item.path"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold px-3 py-2 min-w-[6rem] transition-colors"
              >
                {{ lt('open') }}
              </RouterLink>
              <span
                v-else
                class="inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-slate-600 text-gray-400 dark:text-slate-500 text-xs font-medium px-3 py-2 min-w-[6rem] cursor-not-allowed"
              >
                {{ lt('noLink') }}
              </span>
            </div>
          </li>
        </ul>
      </section>
    </template>

    <p class="text-xs text-gray-500 dark:text-slate-400">
      <RouterLink to="/about/taxonomy" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">
        {{ lt('glossary') }}
      </RouterLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'

const locale = useLocale()

type CapItem = {
  id: string
  section: { ar: string; en: string }
  title: { ar: string; en: string }
  description: { ar: string; en: string }
  status: string
  rollout: string
  path?: string | null
  reason_ar?: string
  reason_en?: string
  gate?: string
}

const loading = ref(true)
const errorMsg = ref('')
const isAr = computed(() => locale.lang.value === 'ar' || locale.lang.value.startsWith('ar'))

const businessType = ref('')
const businessTypeLabelObj = ref<{ ar?: string; en?: string } | null>(null)
const items = ref<CapItem[]>([])

const businessTypeLabel = computed(() => {
  const o = businessTypeLabelObj.value
  if (!o) return ''
  return isAr.value ? (o.ar ?? o.en ?? '') : (o.en ?? o.ar ?? '')
})

function lt(key: 'title' | 'intro' | 'bizType' | 'loading' | 'open' | 'noLink' | 'glossary'): string {
  const ar: Record<string, string> = {
    title: 'قدرات النظام (للمستأجر الحالي)',
    intro:
      'قائمة قراءة فقط من الخادم: ما هو مفعّل لنشاط منشأتك وصلاحية دورك. لا تستبدل ضوابط الأمان على الخادم.',
    bizType: 'نوع النشاط',
    loading: 'جاري التحميل…',
    open: 'فتح',
    noLink: 'لا رابط',
    glossary: 'مسرد المنصة والمستأجر',
  }
  const en: Record<string, string> = {
    title: 'System capabilities (current tenant)',
    intro:
      'Read-only catalogue from the server: what is enabled for your company profile and your role. Does not replace server-side enforcement.',
    bizType: 'Business type',
    loading: 'Loading…',
    open: 'Open',
    noLink: 'No link',
    glossary: 'Platform / tenant glossary',
  }
  return isAr.value ? ar[key] : en[key]
}

function pickLang(v: { ar?: string; en?: string }): string {
  if (isAr.value) return v.ar || v.en || ''
  return v.en || v.ar || ''
}

function statusLabel(s: string): string {
      const map: Record<string, { ar: string; en: string }> = {
        available: { ar: 'متاح', en: 'Available' },
        beta: { ar: 'تجريبي', en: 'Beta' },
        planned: { ar: 'مخطط', en: 'Planned' },
        cancelled: { ar: 'موقوف', en: 'Not planned' },
        post_launch: { ar: 'بعد النشر', en: 'Post-launch' },
        restricted_activity: { ar: 'غير مفعّل للنشاط', en: 'Disabled for profile' },
        restricted_permission: { ar: 'صلاحية', en: 'Permission' },
        restricted_role: { ar: 'دور', en: 'Role' },
      }
      const m = map[s] || { ar: s, en: s }
      return isAr.value ? m.ar : m.en
}

function statusClass(s: string): string {
  if (s === 'available') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
  if (s === 'beta') return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200'
  if (s === 'planned') return 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-200'
  if (s === 'cancelled') return 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200'
  if (s === 'post_launch') return 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-200'
  return 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-100'
}

function reasonText(item: CapItem): string {
  if (isAr.value) return item.reason_ar || ''
  return item.reason_en || ''
}

const grouped = computed(() => {
  const map = new Map<string, { label: string; items: CapItem[] }>()
  for (const it of items.value) {
    const label = pickLang(it.section)
    const key = `${pickLang(it.section)}|${it.section.ar}`
    if (!map.has(key)) {
      map.set(key, { label, items: [] })
    }
    map.get(key)!.items.push(it)
  }
  return [...map.entries()].map(([k, g]) => ({ key: k, label: g.label, items: g.items }))
})

onMounted(async () => {
  loading.value = true
  errorMsg.value = ''
  try {
    const { data } = await apiClient.get('/system/capabilities')
    businessType.value = String(data?.data?.business_type ?? '')
    const btl = data?.data?.business_type_label
    businessTypeLabelObj.value = btl && typeof btl === 'object' ? (btl as { ar?: string; en?: string }) : null
    items.value = Array.isArray(data?.data?.items) ? data.data.items : []
  } catch (e: unknown) {
    const err = e as { response?: { status?: number; data?: { message?: string; code?: string } } }
    if (err.response?.status === 403) {
      errorMsg.value = isAr.value
        ? 'هذه الصفحة مخصصة لفريق عمل المنشأة فقط.'
        : 'This page is only for workshop-side tenant users.'
    } else {
      errorMsg.value = isAr.value ? 'تعذر تحميل القائمة.' : 'Could not load the capability catalogue.'
    }
    businessTypeLabelObj.value = null
    items.value = []
  } finally {
    loading.value = false
  }
})
</script>
