<template>
  <div class="space-y-4 max-w-6xl mx-auto px-2" dir="rtl">
    <div class="text-center">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">باقات الاشتراك</h2>
      <p class="text-gray-500 dark:text-slate-400 mt-1 text-xs">اختر الباقة المناسبة لعملك</p>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Current Subscription Banner -->
    <div v-if="currentSub" class="bg-primary-50 border border-primary-200 dark:bg-primary-950/30 dark:border-primary-800 rounded-lg p-3 flex flex-wrap items-center gap-3">
      <div class="w-9 h-9 bg-primary-600 rounded-full flex items-center justify-center shrink-0">
        <CheckBadgeIcon class="w-5 h-5 text-white" />
      </div>
      <div class="flex-1 min-w-[200px]">
        <p class="text-sm font-semibold text-primary-900 dark:text-primary-100">اشتراكك الحالي: {{ currentPlanLabel }}</p>
        <p class="text-[11px] text-primary-700 dark:text-primary-300/90 leading-snug mt-0.5">
          ينتهي: {{ formatDate(currentSub.ends_at) }} ·
          الفروع: {{ usage?.usage?.branches }}/{{ usage?.limits?.max_branches }} ·
          المستخدمون: {{ usage?.usage?.users }}/{{ usage?.limits?.max_users }}
        </p>
      </div>
      <button class="px-3 py-1.5 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700 shrink-0"
              @click="$router.push('/subscription')"
      >
        إدارة الاشتراك
      </button>
    </div>

    <p v-if="!loading && loadError" class="text-center text-amber-700 dark:text-amber-400 text-sm">{{ loadError }}</p>

    <!-- Plans Grid: بطاقات مُحكمة، ارتفاع موحّد، ميزات على عمودين -->
    <div v-if="!loading && plans.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-stretch">
      <div v-for="plan in plans" :key="plan.id || plan.slug"
           class="bg-white dark:bg-slate-900 rounded-xl border flex flex-col h-full min-h-0 p-4 transition-all hover:shadow-md"
           :class="plan.slug === currentPlanSlug ? 'border-primary-500 ring-1 ring-primary-500/30 shadow-sm' : 'border-gray-200 dark:border-slate-700'"
      >
        <!-- Badge: ارتفاع ثابت لتناسق الصف -->
        <div class="min-h-[26px] mb-2 flex items-start">
          <span v-if="plan.slug === currentPlanSlug" class="bg-primary-600 text-white text-[10px] px-2 py-0.5 rounded-full font-medium">باقتك الحالية</span>
          <span v-else-if="plan.slug === 'professional'" class="bg-orange-500 text-white text-[10px] px-2 py-0.5 rounded-full font-medium">الأكثر شيوعاً</span>
        </div>

        <div class="flex items-start justify-between gap-2">
          <h3 class="text-base font-bold text-gray-900 dark:text-white leading-tight">{{ plan.name_ar || plan.name }}</h3>
          <button
            v-if="auth.isOwner"
            type="button"
            class="text-[10px] px-1.5 py-0.5 rounded border border-gray-300 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 shrink-0"
            @click="openEdit(plan)"
          >
            تعديل
          </button>
        </div>
        <div class="mt-1.5 mb-2">
          <span class="text-2xl font-bold text-primary-700 dark:text-primary-400 tabular-nums">{{ formatNum(planPrice(plan)) }}</span>
          <span class="text-gray-500 dark:text-slate-400 text-xs"> ر.س/شهر</span>
        </div>
        <p class="text-gray-500 dark:text-slate-400 text-[11px] leading-snug mb-3 line-clamp-2">
          {{ plan.description || 'باقة تشغيل ومحاسبة وذكاء أعمال بنطاق مؤسسي.' }}
        </p>

        <!-- Limits -->
        <div class="space-y-1 mb-2">
          <div v-for="(val, key) in planLimits(plan)" :key="key"
               class="flex items-center gap-1.5 text-[11px] text-gray-700 dark:text-slate-300"
          >
            <CheckIcon class="w-3.5 h-3.5 text-green-500 flex-shrink-0" />
            <span>{{ val }}</span>
          </div>
        </div>

        <!-- Features: عمودان لتقليل الارتفاع -->
        <div v-if="planFeatureList(plan).length" class="grid grid-cols-2 gap-x-2 gap-y-1 mb-3 flex-1 min-h-0 content-start">
          <div v-for="f in planFeatureList(plan)" :key="f"
               class="flex items-start gap-1 text-[11px] text-gray-600 dark:text-slate-400 leading-snug"
          >
            <SparklesIcon class="w-3.5 h-3.5 text-primary-500 flex-shrink-0 mt-0.5" />
            <span class="break-words">{{ featureLabel(f) }}</span>
          </div>
        </div>
        <div v-else class="mb-3 text-[10px] text-gray-400 dark:text-slate-500 leading-snug flex-1">
          POS + فواتير + تقارير + إدارة مستخدمين
        </div>

        <button
          type="button"
          :disabled="plan.slug === currentPlanSlug || changingPlan === plan.slug"
          class="w-full mt-auto py-2 rounded-lg font-semibold text-xs transition-colors"
          :class="plan.slug === currentPlanSlug
            ? 'bg-gray-100 dark:bg-slate-800 text-gray-400 cursor-not-allowed'
            : 'bg-primary-600 text-white hover:bg-primary-700'"
          @click="changePlan(plan.slug)"
        >
          {{ plan.slug === currentPlanSlug ? 'باقتك الحالية' : (changingPlan === plan.slug ? 'جاري التغيير...' : 'الترقية لهذه الباقة') }}
        </button>
      </div>
    </div>

    <p v-else-if="!loading && !plans.length && !loadError" class="text-center text-gray-500 dark:text-slate-400 py-12 text-sm leading-relaxed">
      لا تتوفر باقات نشطة للعرض حالياً. جرّب تحديث الصفحة؛ وإن استمر الأمر فتواصل مع دعم المنصة.
    </p>

    <div v-if="editPlan" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="editPlan = null">
      <div class="bg-white rounded-2xl w-full max-w-xl p-5 space-y-4">
        <h3 class="text-lg font-bold">تعديل الباقة: {{ editForm.name_ar || editForm.name }}</h3>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">الاسم</label><input v-model="editForm.name" class="field" /></div>
          <div><label class="label">الاسم العربي</label><input v-model="editForm.name_ar" class="field" /></div>
          <div><label class="label">السعر الشهري</label><input v-model.number="editForm.price_monthly" type="number" class="field" /></div>
          <div><label class="label">السعر السنوي</label><input v-model.number="editForm.price_yearly" type="number" class="field" /></div>
          <div><label class="label">حد الفروع</label><input v-model.number="editForm.max_branches" type="number" class="field" /></div>
          <div><label class="label">حد المستخدمين</label><input v-model.number="editForm.max_users" type="number" class="field" /></div>
          <div><label class="label">حد المنتجات</label><input v-model.number="editForm.max_products" type="number" class="field" /></div>
          <div><label class="label">أيام السماح</label><input v-model.number="editForm.grace_period_days" type="number" class="field" /></div>
        </div>
        <div>
          <label class="label">خصائص الباقة (مفصولة بفاصلة)</label>
          <input v-model="featureInput" class="field" />
        </div>
        <div class="flex justify-end gap-2">
          <button class="btn btn-outline" @click="editPlan = null">إلغاء</button>
          <button class="btn btn-primary" :disabled="savingEdit" @click="saveEdit">{{ savingEdit ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { CheckBadgeIcon, CheckIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'

const toast = useToast()
const plans = ref<any[]>([])
const currentSub = ref<any>(null)
const usage = ref<any>(null)
const loading = ref(true)
const loadError = ref('')
const changingPlan = ref('')
const auth = useAuthStore()
const editPlan = ref<any | null>(null)
const savingEdit = ref(false)
const editForm = ref<any>({})
const featureInput = ref('')

const PLAN_SLUG_LABELS_AR: Record<string, string> = {
  trial: 'تجريبي',
  basic: 'الأساسي',
  starter: 'المبتدئ',
  professional: 'المهني',
  enterprise: 'المؤسسي',
}

const FEATURE_LABELS_AR: Record<string, string> = {
  pos: 'نقطة البيع',
  invoices: 'فواتير',
  work_orders: 'أوامر عمل',
  fleet: 'أسطول',
  wallet: 'محفظة',
  governance: 'حوكمة',
  bookings: 'حجوزات',
  reports: 'تقارير',
  employees: 'موارد بشرية',
  api: 'واجهة برمجية',
  api_access: 'واجهة برمجية',
  zatca: 'زاتكا',
  basic_reports: 'تقارير أساسية',
  work_order_advanced_pricing: 'تسعير أوامر عمل متقدم',
  dedicated_support: 'دعم مخصص',
  sla: 'اتفاقية مستوى خدمة',
  saas_admin: 'إدارة منصة',
}

function formatNum(n: number) { return Number(n || 0).toLocaleString('ar-SA') }
function formatDate(d: string) { return new Date(d).toLocaleDateString('ar-SA') }

function normalizePlansPayload(res: any): any[] {
  const body = res?.data
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

function planPrice(plan: any): number {
  const n = plan?.price_monthly ?? plan?.price
  return Number(n ?? 0)
}

function planLimits(plan: any) {
  const limits = plan.limits && typeof plan.limits === 'object' && !Array.isArray(plan.limits) ? plan.limits : {}
  const result: Record<string, string> = {}
  const mb = limits.max_branches ?? plan.max_branches
  const mu = limits.max_users ?? plan.max_users
  const mv = limits.max_vehicles ?? plan.max_vehicles
  const mp = limits.max_products ?? plan.max_products
  const mi = limits.max_monthly_invoices
  if (mb) result.branches = `${mb} فرع`
  if (mu) result.users = `${mu} مستخدم`
  if (mv) result.vehicles = `${mv} مركبة`
  if (mp) result.products = `${mp} منتج`
  if (mi) result.invoices = `${mi} فاتورة/شهر`
  return result
}

function planFeatureList(plan: any): string[] {
  const f = plan?.features
  if (!f) return []
  if (Array.isArray(f)) return f.filter((x: unknown) => typeof x === 'string') as string[]
  if (typeof f === 'object') {
    return Object.entries(f as Record<string, unknown>)
      .filter(([, v]) => v === true || v === 1 || v === '1')
      .map(([k]) => k)
  }
  return []
}

function featureLabel(key: string): string {
  return FEATURE_LABELS_AR[key] ?? key.replace(/_/g, ' ')
}

const currentPlanSlug = computed(() => {
  const sub = currentSub.value
  if (!sub) return ''
  return String(sub.plan ?? sub.plan_slug ?? '').trim()
})

const currentPlanLabel = computed(() => {
  const slug = currentPlanSlug.value
  if (!slug) return '—'
  const row = plans.value.find(x => x.slug === slug)
  if (row) return row.name_ar || row.name || PLAN_SLUG_LABELS_AR[slug] || slug
  return PLAN_SLUG_LABELS_AR[slug] || slug
})

async function changePlan(slug: string) {
  const row = plans.value.find(x => x.slug === slug)
  const label = row?.name_ar || row?.name || PLAN_SLUG_LABELS_AR[slug] || slug
  const ok = await appConfirm({
    title: 'تغيير باقة الاشتراك',
    message: `هل تريد تغيير باقتك إلى «${label}»؟`,
    confirmLabel: 'تأكيد التغيير',
  })
  if (!ok) return
  changingPlan.value = slug
  try {
    await apiClient.post('/subscription/change', { plan_slug: slug })
    const r = await apiClient.get('/subscription')
    currentSub.value = r.data?.data?.subscription
    const pr = await apiClient.get('/plans')
    plans.value = normalizePlansPayload(pr)
    toast.success('تم تغيير الباقة', 'تم تحديث اشتراكك.')
  } catch (e: any) {
    toast.error('تعذّر تغيير الباقة', e?.response?.data?.message ?? 'حدث خطأ')
  } finally { changingPlan.value = '' }
}

function openEdit(plan: any) {
  editPlan.value = plan
  editForm.value = { ...plan }
  const f = plan.features
  if (Array.isArray(f)) featureInput.value = f.join(', ')
  else if (f && typeof f === 'object') featureInput.value = Object.keys(f as object).join(', ')
  else featureInput.value = ''
}

async function saveEdit() {
  if (!editPlan.value) return
  savingEdit.value = true
  try {
    const payload = {
      ...editForm.value,
      features: featureInput.value.split(',').map((x: string) => x.trim()).filter(Boolean),
    }
    await apiClient.put(`/plans/${editPlan.value.slug}`, payload)
    const p = await apiClient.get('/plans')
    plans.value = normalizePlansPayload(p)
    editPlan.value = null
    toast.success('تم الحفظ', 'تم تحديث بيانات الباقة.')
  } catch (e: any) {
    toast.error('تعذّر التحديث', e?.response?.data?.message ?? 'تعذر تحديث الباقة')
  } finally {
    savingEdit.value = false
  }
}

onMounted(async () => {
  loading.value = true
  loadError.value = ''
  try {
    try {
      const p = await apiClient.get('/plans')
      plans.value = normalizePlansPayload(p)
    } catch (e: any) {
      plans.value = []
      loadError.value = e?.response?.data?.message ?? 'تعذّر تحميل قائمة الباقات.'
    }
    try {
      const s = await apiClient.get('/subscription')
      currentSub.value = s.data?.data?.subscription
    } catch {
      currentSub.value = null
    }
    try {
      const u = await apiClient.get('/subscription/usage')
      usage.value = u.data
    } catch {
      usage.value = null
    }
  } finally {
    loading.value = false
  }
})
</script>
