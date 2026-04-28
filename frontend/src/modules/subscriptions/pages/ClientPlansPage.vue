<template>
  <div class="mx-auto max-w-7xl space-y-10 pb-12" dir="rtl">
    <!-- رأس الصفحة -->
    <header class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-primary-50/60 px-5 py-8 shadow-sm dark:border-slate-700/80 dark:from-slate-900 dark:via-slate-900 dark:to-primary-950/40 md:px-8">
      <div class="pointer-events-none absolute -left-20 -top-20 h-56 w-56 rounded-full bg-primary-400/15 blur-3xl dark:bg-primary-500/10" aria-hidden="true" />
      <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <p class="text-[11px] font-bold tracking-wide text-primary-700 dark:text-primary-300">الاشتراك والتشغيل</p>
          <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white md:text-3xl">باقات الاشتراك</h1>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            قارن الحدود والميزات بشكل واضح، ثم اختر ترقية مباشرة أو طلب دفع بنكي. تظهر أدناه
            <span class="font-semibold text-slate-800 dark:text-slate-200">طلباتك الأخيرة وحالتها</span>
            لمتابعة أي طلب قيد التنفيذ.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink
            to="/subscription"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white/90 px-4 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition hover:border-primary-400 hover:text-primary-800 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-100 dark:hover:border-primary-500"
          >
            اشتراكي الحالي
          </RouterLink>
          <RouterLink
            to="/subscription/payment"
            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-primary-700"
          >
            صفحة الدفع والتحويل
          </RouterLink>
        </div>
      </div>
    </header>

    <!-- باقتك الحالية -->
    <section
      v-if="currentSubscription"
      class="flex flex-wrap items-center gap-4 rounded-2xl border border-primary-200/80 bg-primary-50/90 px-4 py-4 dark:border-primary-900/50 dark:bg-primary-950/35"
    >
      <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-md">
        <CheckBadgeIcon class="h-7 w-7" aria-hidden="true" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-bold text-primary-950 dark:text-primary-50">الباقة المفعّلة على شركتك</p>
        <p class="mt-0.5 text-xs leading-relaxed text-primary-900/85 dark:text-primary-100/85">
          {{ currentPlanTitle }} · ينتهي الاشتراك: {{ formatDate(currentSubscription.ends_at) }}
        </p>
      </div>
    </section>

    <p v-if="pageError" class="rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
      {{ pageError }}
    </p>

    <!-- شبكة الباقات -->
    <section aria-labelledby="plans-heading">
      <div class="mb-6 flex flex-col gap-1 border-b border-slate-200 pb-4 dark:border-slate-700">
        <h2 id="plans-heading" class="text-lg font-bold text-slate-900 dark:text-white">الباقات المتاحة</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">كل الأسعار بالريال السعودي شهرياً ما لم يُذكر غير ذلك.</p>
      </div>

      <div v-if="loadingPlans" class="flex justify-center py-16">
        <span class="h-10 w-10 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" aria-label="جاري التحميل" />
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="p in plans"
          :key="String(p.id)"
          class="group relative flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition-all duration-200 dark:bg-slate-900"
          :class="[
            isCurrentPlan(p)
              ? 'border-primary-500 ring-2 ring-primary-500/25'
              : 'border-slate-200 hover:border-primary-300/80 hover:shadow-md dark:border-slate-700 dark:hover:border-primary-600/50',
            isHighlightPlan(p) ? 'xl:ring-2 xl:ring-amber-400/40' : '',
          ]"
        >
          <div
            v-if="isHighlightPlan(p)"
            class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-l from-amber-500 via-primary-500 to-emerald-500"
            aria-hidden="true"
          />
          <div class="flex flex-1 flex-col p-5 pt-6">
            <div class="mb-3 min-h-[1.75rem]">
              <span
                v-if="isCurrentPlan(p)"
                class="inline-flex rounded-full bg-primary-600 px-2.5 py-0.5 text-[10px] font-bold text-white"
              >
                باقتك الآن
              </span>
              <span
                v-else-if="isHighlightPlan(p)"
                class="inline-flex rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-sm"
              >
                موصى به للنمو
              </span>
            </div>

            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
              {{ p.name_ar || p.name }}
            </h3>
            <p class="mt-1 font-mono text-[10px] text-slate-400 dark:text-slate-500" dir="ltr">{{ p.slug }}</p>

            <div class="mt-4 flex flex-wrap items-baseline gap-1">
              <span class="text-3xl font-black tabular-nums text-primary-700 dark:text-primary-400">{{ moneyNoSuffix(Number(p.price_monthly || 0)) }}</span>
              <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">ر.س / شهر</span>
            </div>
            <p v-if="Number(p.price_yearly) > 0" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              أو {{ money(Number(p.price_yearly)) }} سنوياً
            </p>

            <!-- حدود تشغيلية -->
            <ul class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
              <li v-for="line in planLimitLines(p)" :key="line" class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
                <CheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                <span>{{ line }}</span>
              </li>
            </ul>

            <!-- ميزات مفعّلة -->
            <div v-if="planFeatureKeys(p).length" class="mt-4">
              <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">الميزات المفعّلة في الباقة</p>
              <ul class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                <li
                  v-for="key in planFeatureKeys(p)"
                  :key="key"
                  class="flex items-start gap-1.5 rounded-lg bg-slate-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-slate-700 dark:bg-slate-800/60 dark:text-slate-200"
                >
                  <SparklesIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-600 dark:text-primary-400" aria-hidden="true" />
                  <span>{{ featureLabelAr(key) }}</span>
                </li>
              </ul>
            </div>
            <p v-else class="mt-4 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
              تشغيل أساسي للفواتير والعمليات؛ التفاصيل الدقيقة تخضع لإعدادات منصتكم.
            </p>

            <div class="mt-auto flex flex-col gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
              <template v-if="canManageSubscription">
                <button
                  type="button"
                  class="w-full rounded-xl bg-primary-600 py-2.5 text-sm font-bold text-white shadow transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="isCurrentPlan(p) || actingSlug === p.slug"
                  @click="onUpgrade(p)"
                >
                  {{ isCurrentPlan(p) ? 'أنت على هذه الباقة' : actingSlug === p.slug ? 'جاري الإرسال…' : 'ترقية لهذه الباقة' }}
                </button>
                <button
                  type="button"
                  class="w-full rounded-xl border border-slate-300 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                  :disabled="isCurrentPlan(p) || actingSlug === p.slug"
                  @click="onDowngrade(p)"
                >
                  جدولة خفض عند نهاية الدورة
                </button>
                <button
                  type="button"
                  class="w-full rounded-xl border border-dashed border-primary-300 py-2 text-xs font-bold text-primary-800 transition hover:bg-primary-50 dark:border-primary-700 dark:text-primary-200 dark:hover:bg-primary-950/50"
                  :disabled="creatingOrderFor === Number(p.id)"
                  @click="onCreatePaymentOrder(p)"
                >
                  {{ creatingOrderFor === Number(p.id) ? 'جاري إنشاء طلب الدفع…' : 'إنشاء طلب دفع بنكي لهذه الباقة' }}
                </button>
              </template>
              <p v-else class="rounded-lg bg-slate-100 px-3 py-2 text-center text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                تغيير الباقة أو إنشاء طلب دفع يتطلب صلاحية
                <span class="whitespace-nowrap font-mono text-[10px]" dir="ltr">subscriptions.manage</span>
                — يمكنك الاطلاع على الباقات والطلبات أدناه.
              </p>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- طلبات الدفع -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="orders-heading">
      <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 id="orders-heading" class="text-lg font-bold text-slate-900 dark:text-white">طلبات الاشتراك والدفع</h2>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">آخر الطلبات المرتبطة بباقاتكم — الحالة تُحدَّث بعد مراجعة المنصة أو إكمال التحويل.</p>
        </div>
        <RouterLink
          to="/subscription/payment"
          class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white dark:bg-slate-100 dark:text-slate-900"
        >
          الانتقال لإتمام الدفع
        </RouterLink>
      </div>

      <div v-if="loadingOrders" class="px-5 py-10 text-center text-sm text-slate-500">جاري تحميل الطلبات…</div>
      <div v-else-if="!paymentOrders.length" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
        لا توجد طلبات دفع مسجّلة بعد. عند إنشاء طلب من زر «إنشاء طلب دفع بنكي» سيظهر هنا مع حالته.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-right text-sm dark:divide-slate-700">
          <thead class="bg-slate-50/80 dark:bg-slate-800/50">
            <tr>
              <th class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">المرجع</th>
              <th class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">الباقة</th>
              <th class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">الإجمالي</th>
              <th class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">الحالة</th>
              <th class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">تاريخ الطلب</th>
              <th class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">إجراء</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="o in paymentOrders" :key="String(o.id)" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30">
              <td class="whitespace-nowrap px-4 py-3 font-mono text-xs" dir="ltr">{{ o.reference_code || '—' }}</td>
              <td class="px-4 py-3">{{ o.plan?.name_ar || o.plan?.name || '—' }}</td>
              <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold">{{ money(Number(o.total ?? 0)) }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                  :class="orderStatusClass(o.status)"
                >
                  {{ orderStatusLabel(o.status) }}
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ formatDateTime(o.created_at) }}</td>
              <td class="px-4 py-3">
                <RouterLink
                  v-if="orderNeedsFollowUp(o.status)"
                  class="text-xs font-bold text-primary-700 underline hover:text-primary-900 dark:text-primary-300"
                  :to="{ path: '/subscription/payment', query: { order: String(o.id), total: String(o.total ?? '') } }"
                >
                  متابعة
                </RouterLink>
                <span v-else class="text-xs text-slate-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CheckBadgeIcon, CheckIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import { subscriptionsApi } from '../api'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import { appConfirm } from '@/services/appConfirmDialog'

interface TenantPlanRow {
  id: number | string
  slug: string
  name?: string
  name_ar?: string
  price_monthly?: number
  price_yearly?: number
  max_branches?: number
  max_users?: number
  max_products?: number
  grace_period_days?: number
  features?: unknown
}

interface TenantOrderPlan {
  name_ar?: string
  name?: string
  slug?: string
}

interface TenantPaymentOrderRow {
  id: number | string
  reference_code?: string
  total?: number
  status?: string
  created_at?: string
  plan?: TenantOrderPlan
}

const toast = useToast()
const router = useRouter()
const auth = useAuthStore()

const plans = ref<TenantPlanRow[]>([])
const paymentOrders = ref<TenantPaymentOrderRow[]>([])
const currentSubscription = ref<Record<string, unknown> | null>(null)
const currentPlanRow = ref<Record<string, unknown> | null>(null)

const loadingPlans = ref(true)
const loadingOrders = ref(true)
const pageError = ref('')
const actingSlug = ref('')
const creatingOrderFor = ref<number | null>(null)

const canManageSubscription = computed(() => auth.hasPermission('subscriptions.manage'))

const currentPlanSlug = computed(() => String(currentSubscription.value?.plan ?? '').trim())

const currentPlanTitle = computed(() => {
  const slug = currentPlanSlug.value
  if (!slug) return '—'
  const row = plans.value.find((x) => String(x.slug) === slug)
  if (row) return String(row.name_ar || row.name || slug)
  return String(currentPlanRow.value?.name_ar || currentPlanRow.value?.name || slug)
})

const FEATURE_LABELS_AR: Record<string, string> = {
  pos: 'نقطة البيع',
  invoices: 'الفواتير والتوريد',
  work_orders: 'أوامر العمل',
  fleet: 'إدارة الأسطول',
  wallet: 'المحفظة والرصيد',
  governance: 'الحوكمة والصلاحيات',
  bookings: 'الحجوزات',
  reports: 'التقارير',
  employees: 'الموارد البشرية',
  api: 'واجهة برمجية',
  api_access: 'واجهة برمجية',
  zatca: 'التوافق مع الزكاة والضريبة (زاتكا)',
  basic_reports: 'تقارير تشغيلية أساسية',
  work_order_advanced_pricing: 'تسعير متقدم لأوامر العمل',
  dedicated_support: 'دعم تشغيلي مخصص',
  sla: 'اتفاقية مستوى خدمة',
  saas_admin: 'إعدادات متقدمة للمستأجر',
}

function money(v: number) {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(v || 0)
}
function moneyNoSuffix(v: number) {
  return new Intl.NumberFormat('ar-SA', { maximumFractionDigits: 0 }).format(v || 0)
}

function formatDate(v?: unknown) {
  if (!v) return '—'
  return new Date(String(v)).toLocaleDateString('ar-SA')
}

function formatDateTime(v?: unknown) {
  if (!v) return '—'
  return new Date(String(v)).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
}

function isCurrentPlan(p: TenantPlanRow) {
  return currentPlanSlug.value !== '' && String(p.slug) === currentPlanSlug.value
}

function isHighlightPlan(p: TenantPlanRow) {
  return String(p.slug) === 'professional'
}

function planLimitLines(p: TenantPlanRow): string[] {
  const lines: string[] = []
  const mb = Number(p.max_branches ?? 0)
  const mu = Number(p.max_users ?? 0)
  const mp = Number(p.max_products ?? 0)
  const g = Number(p.grace_period_days ?? 0)
  if (mb > 0) lines.push(`حتى ${mb.toLocaleString('ar-SA')} فرع تشغيلي`)
  if (mu > 0) lines.push(`حتى ${mu.toLocaleString('ar-SA')} مستخدم للنظام`)
  if (mp > 0) lines.push(`حتى ${mp.toLocaleString('ar-SA')} صنف/منتج في الكتالوج`)
  if (g > 0) lines.push(`${g.toLocaleString('ar-SA')} يوم فترة سماح بعد انتهاء الفترة المدفوعة`)
  return lines
}

function planFeatureKeys(p: TenantPlanRow): string[] {
  const f = p.features
  if (!f) return []
  if (Array.isArray(f)) return f.filter((x): x is string => typeof x === 'string')
  if (typeof f === 'object') {
    return Object.entries(f as Record<string, unknown>)
      .filter(([, v]) => v === true || v === 1 || v === '1')
      .map(([k]) => k)
  }
  return []
}

function featureLabelAr(key: string) {
  return FEATURE_LABELS_AR[key] ?? key.replace(/_/g, ' ')
}

function orderStatusLabel(status: unknown): string {
  const s = String(status ?? '').toLowerCase()
  const map: Record<string, string> = {
    pending_transfer: 'بانتظار إدخال بيانات التحويل',
    awaiting_review: 'قيد مراجعة المنصة',
    matched: 'تمت مطابقة الدفع — بانتظار الموافقة النهائية',
    approved: 'مكتمل ومفعّل',
    rejected: 'مرفوض',
    expired: 'منتهي الصلاحية',
    cancelled: 'ملغى',
  }
  return map[s] || (s ? s : '—')
}

function orderStatusClass(status: unknown): string {
  const s = String(status ?? '').toLowerCase()
  if (s === 'approved') return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100'
  if (s === 'rejected' || s === 'expired' || s === 'cancelled') return 'bg-rose-100 text-rose-900 dark:bg-rose-900/35 dark:text-rose-100'
  if (s === 'awaiting_review' || s === 'matched') return 'bg-amber-100 text-amber-950 dark:bg-amber-900/40 dark:text-amber-100'
  if (s === 'pending_transfer') return 'bg-sky-100 text-sky-950 dark:bg-sky-900/40 dark:text-sky-100'
  return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200'
}

function orderNeedsFollowUp(status: unknown): boolean {
  const s = String(status ?? '').toLowerCase()
  return s === 'pending_transfer' || s === 'awaiting_review' || s === 'matched'
}

async function onUpgrade(p: TenantPlanRow) {
  const slug = String(p.slug ?? '')
  const label = String(p.name_ar || p.name || slug)
  const ok = await appConfirm({
    title: 'ترقية الباقة',
    message: `سيتم إرسال طلب ترقية إلى «${label}». هل تريد المتابعة؟`,
    confirmLabel: 'تأكيد الترقية',
  })
  if (!ok) return
  actingSlug.value = slug
  try {
    await subscriptionsApi.upgrade(slug)
    toast.success('تم الطلب', 'تم إرسال طلب الترقية بنجاح.')
    await refreshAll()
  } catch (e: unknown) {
    toast.error('تعذّرت الترقية', summarizeAxiosError(e))
  } finally {
    actingSlug.value = ''
  }
}

async function onDowngrade(p: TenantPlanRow) {
  const slug = String(p.slug ?? '')
  const label = String(p.name_ar || p.name || slug)
  const ok = await appConfirm({
    title: 'جدولة خفض الباقة',
    message: `سيتم جدولة الانتقال إلى «${label}» عند نهاية دورة الفوترة الحالية. هل تؤكد؟`,
    confirmLabel: 'تأكيد الجدولة',
  })
  if (!ok) return
  actingSlug.value = slug
  try {
    await subscriptionsApi.downgrade(slug)
    toast.success('تم الجدولة', 'تمت جدولة خفض الباقة بنهاية الدورة.')
    await refreshAll()
  } catch (e: unknown) {
    toast.error('تعذّر الطلب', summarizeAxiosError(e))
  } finally {
    actingSlug.value = ''
  }
}

async function onCreatePaymentOrder(p: TenantPlanRow) {
  const id = Number(p.id)
  if (!Number.isFinite(id) || id <= 0) return
  creatingOrderFor.value = id
  try {
    const created = await subscriptionsApi.createPaymentOrder(id)
    const raw = created.data?.data as Record<string, unknown> | undefined
    const newId = Number(raw?.id ?? raw?.payment_order_id ?? 0)
    const total = Number(raw?.total ?? 0)
    toast.success('طلب دفع', 'تم إنشاء طلب الدفع — يمكنك إكمال التحويل من صفحة الدفع.')
    await loadOrders()
    if (newId > 0) {
      await router.push({
        path: '/subscription/payment',
        query: { order: String(newId), total: String(total || '') },
      })
    }
  } catch (e: unknown) {
    toast.error('تعذّر الإنشاء', summarizeAxiosError(e))
  } finally {
    creatingOrderFor.value = null
  }
}

async function loadPlansAndCurrent() {
  loadingPlans.value = true
  pageError.value = ''
  try {
    const [pr, cur] = await Promise.all([subscriptionsApi.getPlans(), subscriptionsApi.getCurrent()])
    plans.value = Array.isArray(pr.data?.data) ? (pr.data.data as TenantPlanRow[]) : []
    currentSubscription.value = (cur.data?.data?.subscription as Record<string, unknown>) ?? null
    currentPlanRow.value = (cur.data?.data?.plan as Record<string, unknown>) ?? null
  } catch (e: unknown) {
    plans.value = []
    pageError.value = summarizeAxiosError(e)
  } finally {
    loadingPlans.value = false
  }
}

async function loadOrders() {
  loadingOrders.value = true
  try {
    const res = await subscriptionsApi.listPaymentOrders()
    paymentOrders.value = Array.isArray(res.data?.data) ? (res.data.data as TenantPaymentOrderRow[]) : []
  } catch {
    paymentOrders.value = []
  } finally {
    loadingOrders.value = false
  }
}

async function refreshAll() {
  await Promise.all([loadPlansAndCurrent(), loadOrders()])
}

onMounted(() => {
  void refreshAll()
})
</script>
