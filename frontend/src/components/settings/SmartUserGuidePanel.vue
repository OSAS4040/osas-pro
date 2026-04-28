<template>
  <div class="space-y-4" dir="rtl">
    <div class="rounded-2xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 no-print">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-base font-bold text-gray-900 dark:text-slate-100">دليل المستخدم الذكي</h3>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
            دليل رقمي قابل للبحث والطباعة والحفظ، ويتحدّث تلقائيا من شاشة النظام والصفحات المفعلة.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="btn btn-outline" @click="printGuide">طباعة</button>
          <button type="button" class="btn btn-outline" @click="downloadGuide">حفظ JSON</button>
          <button type="button" class="btn btn-primary disabled:opacity-50" :disabled="saving" @click="saveGuideSettings">
            {{ saving ? 'جارٍ الحفظ...' : 'حفظ ونشر' }}
          </button>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
          <label class="label">بحث في الدليل</label>
          <input v-model="search" type="search" class="field" placeholder="ابحث باسم الشاشة أو الخاصية أو الوصف..." />
        </div>
        <div>
          <label class="label">حالة النشر</label>
          <select v-model="publishStatus" class="field bg-white dark:bg-slate-700">
            <option value="draft">مسودة داخلية</option>
            <option value="published">منشور للمستخدمين</option>
          </select>
        </div>
      </div>
    </div>

    <div class="print-container rounded-2xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <h4 class="text-sm font-semibold text-gray-800 dark:text-slate-200 mb-3">محتوى الدليل حسب الأقسام</h4>
      <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
        <section
          v-for="group in groupedFiltered"
          :key="group.section"
          class="rounded-xl border border-gray-100 dark:border-slate-700 bg-gray-50/70 dark:bg-slate-900/40 p-3"
        >
          <h5 class="font-bold text-sm text-primary-700 dark:text-primary-300 mb-2">{{ group.section }}</h5>
          <div class="space-y-2">
            <article v-for="item in group.items" :key="item.to" class="rounded-lg bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 p-3">
              <div class="flex items-center justify-between gap-2">
                <p class="font-medium text-sm text-gray-800 dark:text-slate-200">{{ item.label }}</p>
                <code class="text-[11px] text-gray-400">{{ item.to }}</code>
              </div>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ item.summary }}</p>
              <ul v-if="item.sections.length" class="mt-2 list-disc pr-4 text-xs text-gray-600 dark:text-slate-300 space-y-1">
                <li v-for="s in item.sections.slice(0, 3)" :key="`${item.to}-${s.title}`">{{ s.title }}: {{ s.body }}</li>
              </ul>
            </article>
          </div>
        </section>
      </div>
      <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-3">
        يتم تحديث بنية الدليل تلقائيا عند إضافة صفحات جديدة إلى القائمة الجانبية أو تحديث بيانات المساعدة.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { NAV_SEARCH_ITEMS, navSearchItemVisibleForPortals } from '@/config/navSearchItems'
import { tenantSectionOpen } from '@/config/staffFeatureGate'
import { enabledPortals } from '@/config/portalAccess'
import { listPageHelpEntries } from '@/help/pageHelpRegistry'
import { printDocument } from '@/composables/useAppPrint'

type GuideItem = {
  to: string
  label: string
  section: string
  summary: string
  sections: Array<{ title: string; body: string }>
}

const auth = useAuthStore()
const biz = useBusinessProfileStore()
const toast = useToast()
const search = ref('')
const publishStatus = ref<'draft' | 'published'>('draft')
const saving = ref(false)

const helpMap = computed(() => {
  const map = new Map<string, ReturnType<typeof listPageHelpEntries>[number]['entry']>()
  listPageHelpEntries().forEach(({ key, entry }) => map.set(key, entry))
  return map
})

const guideItems = computed<GuideItem[]>(() => {
  void biz.loaded
  void biz.businessType
  void biz.effectiveFeatureMatrix
  return NAV_SEARCH_ITEMS.filter((nav) => {
    if (!navSearchItemVisibleForPortals(nav, enabledPortals)) return false
    if (nav.requiresManager && !auth.isManager) return false
    if (nav.requiresOwner && !auth.isOwner) return false
    if (nav.requiresStaff && !auth.isStaff) return false
    if (nav.requiresPlatform && !auth.isPlatform) return false
    if (nav.requiresPermission && !auth.hasPermission(nav.requiresPermission)) return false
    if (nav.requiresAnyPermission?.length) {
      const ok = nav.requiresAnyPermission.some((p) => auth.hasPermission(p))
      if (!ok) return false
    }
    if (
      typeof nav.requiresTenantFeature === 'string'
      && nav.requiresTenantFeature.length > 0
      && !tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), nav.requiresTenantFeature)
    ) {
      return false
    }
    return true
  }).map((nav) => {
    const byName = helpMap.value.get((nav.to === '/' ? 'dashboard' : nav.to.replace(/^\//, '').replace(/\//g, '.')))
    return {
      to: nav.to,
      label: nav.label,
      section: nav.section,
      summary: byName?.summary ?? `شرح تنفيذي لشاشة ${nav.label} ضمن ${nav.section}.`,
      sections: byName?.sections ?? [],
    }
  })
})

const groupedFiltered = computed(() => {
  const q = search.value.trim().toLowerCase()
  const filtered = q
    ? guideItems.value.filter((i) => `${i.label} ${i.section} ${i.summary}`.toLowerCase().includes(q))
    : guideItems.value
  const groups: Record<string, GuideItem[]> = {}
  filtered.forEach((item) => {
    groups[item.section] ||= []
    groups[item.section].push(item)
  })
  return Object.entries(groups).map(([section, items]) => ({ section, items }))
})

async function saveGuideSettings() {
  if (!auth.user?.company_id) return
  saving.value = true
  try {
    await apiClient.patch(`/companies/${auth.user.company_id}/settings`, {
      smart_user_guide: {
        status: publishStatus.value,
        updated_at: new Date().toISOString(),
        items_count: guideItems.value.length,
      },
    })
    toast.success('تم حفظ حالة دليل المستخدم الذكي')
  } catch {
    toast.error('تعذر حفظ دليل المستخدم')
  } finally {
    saving.value = false
  }
}

async function loadGuideSettings() {
  if (!auth.user?.company_id) return
  try {
    const { data } = await apiClient.get(`/companies/${auth.user.company_id}/settings`)
    const status = data?.data?.smart_user_guide?.status
    if (status === 'draft' || status === 'published') publishStatus.value = status
  } catch {
    // ignore
  }
}

async function printGuide() {
  await printDocument()
}

function downloadGuide() {
  const payload = {
    generated_at: new Date().toISOString(),
    status: publishStatus.value,
    items: guideItems.value,
  }
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'smart-user-guide.json'
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  void biz.load().catch(() => {})
  void loadGuideSettings()
})
</script>

<style scoped>
.field {
  @apply w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-slate-700 dark:text-slate-100;
}
.label {
  @apply block text-xs text-gray-500 dark:text-slate-400 mb-1;
}
</style>
