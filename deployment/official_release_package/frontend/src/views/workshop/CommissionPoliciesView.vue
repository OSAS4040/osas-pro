<template>
  <div class="max-w-5xl mx-auto space-y-6 pb-10" dir="rtl">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
          <AdjustmentsHorizontalIcon class="w-8 h-8 text-primary-600 dark:text-primary-400" />
          سياسات العمولات
        </h1>
        <p class="text-sm text-gray-600 dark:text-slate-400 mt-2 max-w-2xl">
          قواعد على الخادم مرتبطة بـ <strong>موظف</strong> و/أو <strong>عميل</strong> مع أولوية وسقف للمبلغ؛ تُدمج مع
          <strong>انتظام الحضور</strong> تلقائياً عند احتساب العمولة.
        </p>
      </div>
      <RouterLink
        to="/workshop/commissions"
        class="px-4 py-2 rounded-xl border border-gray-300 dark:border-slate-600 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800"
      >
        العودة للعمولات
      </RouterLink>
    </header>

    <!-- ذكاء: ملخص -->
    <div
      v-if="!loading && rules.length"
      class="rounded-xl border border-violet-200/90 bg-violet-50/90 dark:bg-violet-950/40 dark:border-violet-900/50 px-4 py-3 text-sm text-violet-950 dark:text-violet-100"
    >
      <span class="font-semibold">ملخص:</span>
      {{ activeRules }} قاعدة نشطة من أصل {{ rules.length }} —
      أعلى أولوية حالياً: <strong>{{ topPriorityLabel }}</strong>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 space-y-4">
      <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100">إضافة أو تعديل قاعدة</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          اسم القاعدة (اختياري)
          <input v-model="form.name" type="text" placeholder="مثال: عمولة مبيعات الأسطول"
                 class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          />
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          ينطبق على
          <select v-model="form.applies_to"
                  class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          >
            <option value="invoice">فاتورة</option>
            <option value="work_order">أمر عمل</option>
            <option value="service">خدمة</option>
          </select>
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          موظف (اختياري — فارغ = عام للجميع)
          <select v-model="form.employee_id"
                  class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          >
            <option value="">— الكل —</option>
            <option v-for="e in employees" :key="e.id" :value="String(e.id)">{{ e.name }}</option>
          </select>
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          عميل (اختياري — فارغ = كل العملاء)
          <select v-model="form.customer_id"
                  class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          >
            <option value="">— الكل —</option>
            <option v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          النسبة %
          <input v-model.number="form.rate" type="number" min="0" max="100" step="0.1"
                 class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          />
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          أدنى مبلغ أساس (ر.س)
          <input v-model.number="form.min_amount" type="number" min="0" step="0.01"
                 class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          />
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          سقف عمولة (ر.س) — اختياري
          <input v-model.number="form.max_commission_amount" type="number" min="0" step="0.01"
                 class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          />
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          أولوية (أعلى = يُطبَّق أولاً)
          <input v-model.number="form.priority" type="number" min="0" max="65535"
                 class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          />
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
          مضاعف الحضور (افتراضي 1)
          <input v-model.number="form.attendance_multiplier" type="number" min="0" max="10" step="0.05"
                 class="mt-1 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"
          />
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300 mt-6">
          <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
          قاعدة نشطة
        </label>
      </div>

      <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
        meta (JSON) — للتكاملات الخارجية
        <textarea v-model="metaJson" rows="2" placeholder="{&quot;integration&quot;:&quot;mudad&quot;}"
                  class="mt-1 w-full font-mono text-xs rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2"
        />
      </label>

      <div class="flex flex-wrap gap-2">
        <button type="button" :disabled="saving"
                class="px-5 py-2.5 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
                @click="submit"
        >
          {{ saving ? 'جاري الحفظ...' : editingId ? 'تحديث القاعدة' : 'إنشاء قاعدة' }}
        </button>
        <button v-if="editingId" type="button" class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 text-sm text-gray-700 dark:text-slate-200"
                @click="resetForm"
        >
          إلغاء التعديل
        </button>
      </div>
      <p v-if="formError" class="text-sm text-red-600 dark:text-red-400">{{ formError }}</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100">القواعد المحفوظة</h2>
        <button type="button" class="text-xs text-primary-600 dark:text-primary-400 font-medium" @click="load">تحديث</button>
      </div>
      <div v-if="loading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-700">
            <tr>
              <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-slate-300">الاسم</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-slate-300">النطاق</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-slate-300">%</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-slate-300">أولوية</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-slate-300">حالة</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-slate-300">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr v-for="r in rules" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-slate-900/40">
              <td class="px-3 py-2 text-gray-900 dark:text-slate-100">{{ r.name || '—' }}</td>
              <td class="px-3 py-2 text-xs text-gray-600 dark:text-slate-400">
                {{ scopeLabel(r) }}
              </td>
              <td class="px-3 py-2 font-mono">{{ r.rate }}</td>
              <td class="px-3 py-2">{{ r.priority }}</td>
              <td class="px-3 py-2">
                <span :class="r.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600'"
                      class="px-2 py-0.5 rounded-full text-xs"
                >{{ r.is_active ? 'نشط' : 'موقوف' }}</span>
              </td>
              <td class="px-3 py-2 flex gap-2">
                <button type="button" class="text-primary-600 dark:text-primary-400 text-xs font-medium" @click="edit(r)">تعديل</button>
                <button type="button" class="text-red-600 dark:text-red-400 text-xs font-medium" @click="remove(r)">حذف</button>
              </td>
            </tr>
            <tr v-if="!rules.length">
              <td colspan="6" class="text-center py-10 text-gray-400">لا توجد قواعد بعد</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- تفضيلات محلية (معاينة ذكاء إضافي) -->
    <div class="rounded-2xl border border-dashed border-gray-300 dark:border-slate-600 p-4 bg-gray-50/80 dark:bg-slate-900/40">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200 mb-2">تفضيلات محلية (المتصفح فقط)</h3>
      <p class="text-xs text-gray-500 dark:text-slate-500 mb-3">تُستخدم لاحقاً مع لوحات التحليل؛ لا تؤثر على احتساب الخادم.</p>
      <div class="flex flex-wrap gap-3 items-end">
        <label class="text-sm text-gray-700 dark:text-slate-300">
          عامل عرض للمخططات
          <input v-model.number="localPrefs.chartFactor" type="number" min="0.5" max="2" step="0.05"
                 class="mt-1 block w-32 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-2 py-1 text-sm"
          />
        </label>
        <button type="button" class="px-3 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 text-sm" @click="saveLocal">حفظ محلياً</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { appConfirm } from '@/services/appConfirmDialog'

const LOCAL_KEY = 'workshop_commission_policy_prefs_v2'

const rules = ref<any[]>([])
const employees = ref<any[]>([])
const customers = ref<any[]>([])
const loading = ref(true)
const saving = ref(false)
const formError = ref('')
const editingId = ref<number | null>(null)
const metaJson = ref('')

const form = ref({
  name: '',
  applies_to: 'invoice' as 'invoice' | 'work_order' | 'service',
  employee_id: '',
  customer_id: '',
  rate: 5,
  min_amount: 0,
  max_commission_amount: null as number | null,
  priority: 0,
  attendance_multiplier: 1,
  is_active: true,
})

const localPrefs = ref({ chartFactor: 1 })

const activeRules = computed(() => rules.value.filter((r) => r.is_active).length)
const topPriorityLabel = computed(() => {
  if (!rules.value.length) return '—'
  const sorted = [...rules.value].sort((a, b) => (b.priority ?? 0) - (a.priority ?? 0))
  return `${sorted[0].name || 'قاعدة'} (${sorted[0].priority})`
})

function scopeLabel(r: any): string {
  const parts: string[] = [r.applies_to]
  if (r.employee?.name) parts.push(`موظف: ${r.employee.name}`)
  if (r.customer?.name) parts.push(`عميل: ${r.customer.name}`)
  return parts.join(' · ')
}

function resetForm() {
  editingId.value = null
  formError.value = ''
  metaJson.value = ''
  form.value = {
    name: '',
    applies_to: 'invoice',
    employee_id: '',
    customer_id: '',
    rate: 5,
    min_amount: 0,
    max_commission_amount: null,
    priority: 0,
    attendance_multiplier: 1,
    is_active: true,
  }
}

function edit(r: any) {
  editingId.value = r.id
  form.value = {
    name: r.name || '',
    applies_to: r.applies_to,
    employee_id: r.employee_id ? String(r.employee_id) : '',
    customer_id: r.customer_id ? String(r.customer_id) : '',
    rate: Number(r.rate),
    min_amount: Number(r.min_amount ?? 0),
    max_commission_amount: r.max_commission_amount != null ? Number(r.max_commission_amount) : null,
    priority: Number(r.priority ?? 0),
    attendance_multiplier: Number(r.attendance_multiplier ?? 1),
    is_active: !!r.is_active,
  }
  metaJson.value = r.meta ? JSON.stringify(r.meta, null, 2) : ''
}

async function load() {
  loading.value = true
  try {
    const [rulesRes, empRes, custRes] = await Promise.all([
      apiClient.get('/workshop/commission-rules'),
      apiClient.get('/workshop/employees'),
      apiClient.get('/customers', { params: { per_page: 50 } }),
    ])
    rules.value = rulesRes.data?.data ?? []
    employees.value = empRes.data?.data ?? []
    const cdata = custRes.data
    customers.value = cdata?.data ?? cdata ?? []
  } finally {
    loading.value = false
  }
}

async function submit() {
  formError.value = ''
  let meta: Record<string, unknown> | undefined
  if (metaJson.value.trim()) {
    try {
      meta = JSON.parse(metaJson.value)
    } catch {
      formError.value = 'meta يجب أن يكون JSON صالحاً'
      return
    }
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      name: form.value.name || null,
      applies_to: form.value.applies_to,
      employee_id: form.value.employee_id ? Number(form.value.employee_id) : null,
      customer_id: form.value.customer_id ? Number(form.value.customer_id) : null,
      rate: form.value.rate,
      min_amount: form.value.min_amount ?? 0,
      max_commission_amount: form.value.max_commission_amount,
      priority: form.value.priority,
      attendance_multiplier: form.value.attendance_multiplier,
      is_active: form.value.is_active,
      meta,
    }
    if (editingId.value) {
      await apiClient.put(`/workshop/commission-rules/${editingId.value}`, payload)
    } else {
      await apiClient.post('/workshop/commission-rules', payload)
    }
    resetForm()
    await load()
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? 'فشل الحفظ'
  } finally {
    saving.value = false
  }
}

async function remove(r: any) {
  const ok = await appConfirm({
    title: 'حذف القاعدة',
    message: 'حذف هذه القاعدة؟',
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/workshop/commission-rules/${r.id}`)
    if (editingId.value === r.id) resetForm()
    await load()
  } catch {
    /* toast optional */
  }
}

function saveLocal() {
  localStorage.setItem(LOCAL_KEY, JSON.stringify(localPrefs.value))
}

function loadLocal() {
  try {
    const raw = localStorage.getItem(LOCAL_KEY)
    if (raw) Object.assign(localPrefs.value, JSON.parse(raw))
  } catch {}
}

onMounted(() => {
  loadLocal()
  load()
})
</script>
