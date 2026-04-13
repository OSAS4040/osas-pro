<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">إدارة العقود</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">عقود الشركات ومراكز الخدمة ومنفذي البيع</p>
      </div>
      <button class="btn btn-primary flex items-center gap-2 text-sm" @click="showForm = true">
        <span class="text-lg">+</span> عقد جديد
      </button>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
      <div class="rounded-2xl border border-primary-200/80 dark:border-primary-900/50 bg-gradient-to-br from-primary-50/90 to-white dark:from-slate-900 dark:to-slate-800 p-5 space-y-3">
        <h2 class="text-sm font-bold text-primary-900 dark:text-primary-200">قالب التعاقد الإلكتروني</h2>
        <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed">
          عند إنشاء عقد جديد انسخ هذا القالب إلى حقل «وصف العقد» واستبدل العناصر:
          <code class="block mt-1 text-[10px] bg-white/80 dark:bg-slate-800 px-2 py-1 rounded font-mono dir-ltr" v-text="templatePlaceholders" />
        </p>
        <textarea v-model="contractTemplate" rows="12" class="field w-full font-mono text-xs leading-relaxed dark:bg-slate-900" />
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary text-xs" @click="saveTemplate">حفظ القالب</button>
          <button type="button" class="btn btn-secondary text-xs" @click="resetTemplate">استعادة الافتراضي</button>
          <button type="button" class="btn btn-secondary text-xs" @click="applyTemplateToForm">إدراج في نموذج العقد</button>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5 space-y-3">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white">مسار التعاقد مع الأطراف</h2>
        <ol class="text-xs text-gray-600 dark:text-slate-400 space-y-2 list-decimal list-inside leading-relaxed">
          <li>تحديد الطرفين: المنشأة (الطرف الأول) والطرف الآخر (شركة، مركز خدمة، فرد...).</li>
          <li>صياغة البنود من القالب أعلاه أو تعديلها يدوياً مع سياسة الدفع والتواريخ.</li>
          <li>حفظ كمسودة، ثم «إرسال للتوقيع» عبر البريد أو واتساب (قنوات تظهر حسب بيانات الطرف).</li>
          <li>أرشفة النسخة النهائية PDF وربطها لاحقاً بمزوّد توقيع إلكتروني معتمد عند التفعيل.</li>
        </ol>
        <p class="text-[11px] text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800 rounded-lg p-3 leading-relaxed">
          التوقيع الإلكتروني ذو المفعول القانوني الكامل يتطلب هوية موثّقة ومزوّداً معتمداً — الواجهة الحالية تُجهّز العقود والإرسال والأرشفة اليدوية حتى الربط التقني.
        </p>
      </div>
    </div>

    <!-- Expiring Soon Alert -->
    <div v-if="expiring.length" class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-4">
      <div class="flex items-start gap-3">
        <span class="text-xl">⚠️</span>
        <div>
          <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">{{ expiring.length }} عقد ينتهي قريباً</p>
          <ul class="mt-1 space-y-0.5">
            <li v-for="c in expiring" :key="c.id" class="text-xs text-amber-700 dark:text-amber-400">
              {{ c.party_name }} — {{ c.title }} — باقي {{ c.days_until_expiry }} يوم
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-2 items-center">
      <input v-model="search" type="text" placeholder="ابحث باسم الطرف أو العقد..." class="field text-sm w-60" @input="load" />
      <select v-model="filterStatus" class="field text-sm w-36" @change="load">
        <option value="">كل الحالات</option>
        <option value="draft">مسودة</option>
        <option value="pending_signature">بانتظار التوقيع</option>
        <option value="active">نشط</option>
        <option value="expired">منتهي</option>
        <option value="terminated">ملغي</option>
      </select>
      <select v-model="filterType" class="field text-sm w-36" @change="load">
        <option value="">كل الأنواع</option>
        <option value="company">شركة</option>
        <option value="pos">منفذ بيع</option>
        <option value="service_center">مركز خدمة</option>
        <option value="individual">فرد</option>
      </select>
    </div>

    <!-- Contracts Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="py-8 text-center"><div class="spinner mx-auto"></div></div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 dark:bg-slate-700/40 text-right border-b dark:border-slate-700">
            <th class="px-4 py-3 font-medium text-gray-600 dark:text-slate-400">العقد</th>
            <th class="px-4 py-3 font-medium text-gray-600 dark:text-slate-400">الطرف</th>
            <th class="px-4 py-3 font-medium text-gray-600 dark:text-slate-400">القيمة</th>
            <th class="px-4 py-3 font-medium text-gray-600 dark:text-slate-400">سريان</th>
            <th class="px-4 py-3 font-medium text-gray-600 dark:text-slate-400">الحالة</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50">
          <tr v-for="c in contracts" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900 dark:text-white">{{ c.title }}</p>
              <p class="text-xs text-gray-400">{{ paymentLabel(c.payment_policy) }}</p>
            </td>
            <td class="px-4 py-3">
              <p class="text-gray-800 dark:text-slate-200">{{ c.party_name }}</p>
              <p class="text-xs text-gray-400">{{ typeLabel(c.party_type) }}</p>
            </td>
            <td class="px-4 py-3 font-medium">{{ c.value ? fmt(c.value) + ' ر.س' : '—' }}</td>
            <td class="px-4 py-3 text-xs text-gray-500">
              {{ fmtDate(c.start_date) }}<br />{{ fmtDate(c.end_date) }}
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap items-center gap-1">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(c.status)">
                  {{ statusLabel(c.status) }}
                </span>
                <span
                  v-if="c.status === 'active' && Number(c.service_items_count ?? 0) === 0"
                  class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100"
                  title="عقد نشط بلا بنود كتالوج — العملاء المربوطون سيرون كتالوجًا فارغًا"
                >لا بنود</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-2">
                <RouterLink
                  v-if="canOpenContractCatalog()"
                  :to="{ name: 'contracts.catalog', params: { contractId: c.id } }"
                  class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                  بنود العقد
                </RouterLink>
                <button class="text-xs text-primary-600 hover:underline" @click="editContract(c)">تعديل</button>
                <button v-if="c.status === 'draft'" class="text-xs text-teal-600 hover:underline" @click="sendForSignature(c)">إرسال</button>
              </div>
            </td>
          </tr>
          <tr v-if="!contracts.length"><td colspan="6" class="text-center py-8 text-gray-400">لا توجد عقود</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Contract Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto" @click.self="showForm = false">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl my-8">
        <div class="p-5 border-b dark:border-slate-700">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editing ? 'تعديل عقد' : 'عقد جديد' }}</h3>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="label">عنوان العقد *</label>
            <input v-model="form.title" type="text" class="field" placeholder="عقد مركز خدمة سريع..." />
          </div>
          <div>
            <label class="label">اسم الطرف الآخر *</label>
            <input v-model="form.party_name" type="text" class="field" />
          </div>
          <div>
            <label class="label">نوع الطرف</label>
            <select v-model="form.party_type" class="field">
              <option value="company">شركة</option>
              <option value="pos">منفذ بيع</option>
              <option value="service_center">مركز خدمة</option>
              <option value="individual">فرد</option>
            </select>
          </div>
          <div>
            <label class="label">البريد الإلكتروني</label>
            <input v-model="form.party_email" type="email" class="field" />
          </div>
          <div>
            <label class="label">رقم الجوال</label>
            <input v-model="form.party_phone" type="tel" class="field" />
          </div>
          <div>
            <label class="label">السجل التجاري</label>
            <input v-model="form.party_cr" type="text" class="field" />
          </div>
          <div>
            <label class="label">الرقم الضريبي</label>
            <input v-model="form.party_tax_number" type="text" class="field" />
          </div>
          <div>
            <label class="label">قيمة العقد (ر.س)</label>
            <input v-model="form.value" type="number" min="0" class="field" />
          </div>
          <div>
            <label class="label">سياسة الدفع</label>
            <select v-model="form.payment_policy" class="field">
              <option value="monthly">شهري</option>
              <option value="quarterly">ربع سنوي</option>
              <option value="annually">سنوي</option>
              <option value="one_time">دفعة واحدة</option>
              <option value="custom">مخصص</option>
            </select>
          </div>
          <div>
            <label class="label">نطاق العقد *</label>
            <SmartDatePicker
              mode="range"
              :from-value="form.start_date"
              :to-value="form.end_date"
              @change="onContractRangeChange"
            />
          </div>
          <div>
            <label class="label">التنبيه قبل (يوم)</label>
            <input v-model="form.alert_days_before" type="number" min="1" max="365" class="field" />
          </div>
          <div class="md:col-span-2">
            <label class="label">وصف العقد</label>
            <textarea v-model="form.description" rows="3" class="field" placeholder="شروط وبنود العقد..."></textarea>
          </div>
        </div>
        <p v-if="formError" class="px-5 text-red-600 text-xs">{{ formError }}</p>
        <div class="p-5 border-t dark:border-slate-700 flex gap-3 justify-end">
          <button class="btn btn-secondary text-sm" @click="showForm = false">إلغاء</button>
          <button :disabled="saving" class="btn btn-primary text-sm" @click="saveContract">
            {{ saving ? 'جارٍ الحفظ...' : (editing ? 'تحديث' : 'إنشاء العقد') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Send for Signature Modal -->
    <div v-if="showSendModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showSendModal = false">
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 w-full max-w-sm space-y-4">
        <h3 class="font-bold text-gray-900 dark:text-white">إرسال للتوقيع</h3>
        <p class="text-sm text-gray-600 dark:text-slate-400">اختر قنوات الإرسال لـ {{ sendingContract?.party_name }}</p>
        <div class="space-y-2">
          <label v-if="sendingContract?.party_email" class="flex items-center gap-2 cursor-pointer">
            <input v-model="sendChannels.email" type="checkbox" class="rounded" />
            <span class="text-sm">📧 بريد إلكتروني ({{ sendingContract.party_email }})</span>
          </label>
          <label v-if="sendingContract?.party_phone" class="flex items-center gap-2 cursor-pointer">
            <input v-model="sendChannels.whatsapp" type="checkbox" class="rounded" />
            <span class="text-sm">💬 واتساب ({{ sendingContract.party_phone }})</span>
          </label>
        </div>
        <div class="flex gap-3">
          <button class="flex-1 btn btn-secondary text-sm" @click="showSendModal = false">إلغاء</button>
          <button :disabled="sending || (!sendChannels.email && !sendChannels.whatsapp)"
                  class="flex-1 btn btn-primary text-sm" @click="confirmSend"
          >
            {{ sending ? 'جارٍ الإرسال...' : 'إرسال' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

const api   = useApi()
const toast = useToast()
const auth  = useAuthStore()

function canOpenContractCatalog() {
  return auth.hasPermission('contracts.service_items.view')
}

const contracts    = ref<any[]>([])
const expiring     = ref<any[]>([])
const loading      = ref(false)
const showForm     = ref(false)
const saving       = ref(false)
const editing      = ref<any>(null)
const formError    = ref('')
const search       = ref('')
const filterStatus = ref('')
const filterType   = ref('')

const showSendModal   = ref(false)
const sendingContract = ref<any>(null)
const sendChannels    = ref({ email: true, whatsapp: false })
const sending         = ref(false)

const defaultForm = () => ({
  title: '', party_name: '', party_type: 'company', party_email: '', party_phone: '',
  party_cr: '', party_tax_number: '', description: '', value: '',
  payment_policy: 'monthly', payment_day: '', start_date: '', end_date: '',
  alert_days_before: 30,
})

const form = ref(defaultForm())

const templatePlaceholders = '{{party_name}} {{company_name}} {{start_date}} {{end_date}} {{value}}'

const TEMPLATE_KEY = 'workshopos_contract_template_v1'
const defaultTemplate = `عقد تعاون وتوريد خدمات (إلكتروني — نسخة مسودة)

الطرف الأول: {{company_name}} (المنشأة)
الطرف الثاني: {{party_name}}

1) موضوع العقد: تقديم خدمات / توريد وفق ما يُتفق عليه في الطلبات.
2) المدة: من {{start_date}} إلى {{end_date}}.
3) القيمة الإجمالية التقديرية: {{value}} ر.س (تفصيل حسب الفواتير أو أوامر العمل).
4) الالتزام بأنظمة المملكة والضريبة والفوترة الإلكترونية حيث ينطبق.
5) السرية وحماية البيانات بين الطرفين.
6) التسوية الودية أولاً، ثم الجهات المختصة في المملكة العربية السعودية.

تم الاتفاق والقبول بإنشاء هذا العقد إلكترونياً مع استكمال التوقيع عبر القنوات المعتمدة.`

const contractTemplate = ref(defaultTemplate)

function loadTemplate() {
  try {
    const s = localStorage.getItem(TEMPLATE_KEY)
    if (s) contractTemplate.value = s
  } catch { /* */ }
}

function saveTemplate() {
  try {
    localStorage.setItem(TEMPLATE_KEY, contractTemplate.value)
    toast.success('تم حفظ قالب العقد')
  } catch {
    toast.error('تعذر الحفظ')
  }
}

function resetTemplate() {
  contractTemplate.value = defaultTemplate
  try {
    localStorage.removeItem(TEMPLATE_KEY)
  } catch { /* */ }
  toast.info('تمت استعادة القالب الافتراضي')
}

function applyTemplateToForm() {
  form.value.description = contractTemplate.value
    .replace(/\{\{party_name\}\}/g, form.value.party_name || '……')
    .replace(/\{\{company_name\}\}/g, 'المنشأة')
    .replace(/\{\{start_date\}\}/g, form.value.start_date || '……')
    .replace(/\{\{end_date\}\}/g, form.value.end_date || '……')
    .replace(/\{\{value\}\}/g, String(form.value.value || '……'))
  showForm.value = true
  toast.success('تم إدراج القالب في الوصف — راجع التفاصيل')
}

async function load() {
  loading.value = true
  try {
    const r = await api.get('/governance/contracts', { status: filterStatus.value, party_type: filterType.value, search: search.value })
    contracts.value = r.data?.data ?? r.data ?? []
  } finally { loading.value = false }
}

async function loadExpiring() {
  try { const r = await api.get('/governance/contracts-expiring'); expiring.value = r.data ?? [] } catch {}
}

function editContract(c: any) {
  editing.value = c
  form.value = { ...defaultForm(), ...c, start_date: c.start_date?.slice(0,10), end_date: c.end_date?.slice(0,10) }
  showForm.value = true
}

function onContractRangeChange(val: { from: string; to: string }) {
  form.value.start_date = val.from
  form.value.end_date = val.to
}

async function saveContract() {
  if (!form.value.title || !form.value.party_name || !form.value.start_date || !form.value.end_date) {
    formError.value = 'يرجى ملء الحقول المطلوبة'
    return
  }
  saving.value = true; formError.value = ''
  try {
    if (editing.value) {
      await api.put(`/governance/contracts/${editing.value.id}`, form.value)
      toast.success('تم تحديث العقد')
    } else {
      await api.post('/governance/contracts', form.value)
      toast.success('تم إنشاء العقد')
    }
    showForm.value = false; editing.value = null; form.value = defaultForm()
    await load()
  } catch (e: any) {
    formError.value = e.response?.data?.message ?? 'فشل الحفظ'
  } finally { saving.value = false }
}

function sendForSignature(c: any) {
  sendingContract.value = c
  sendChannels.value = { email: !!c.party_email, whatsapp: !!c.party_phone }
  showSendModal.value = true
}

async function confirmSend() {
  sending.value = true
  const channels: string[] = []
  if (sendChannels.value.email)    channels.push('email')
  if (sendChannels.value.whatsapp) channels.push('whatsapp')
  try {
    await api.post(`/governance/contracts/${sendingContract.value.id}/send-for-signature`, { channels })
    toast.success('تم إرسال العقد للتوقيع')
    showSendModal.value = false
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? 'فشل الإرسال')
  } finally { sending.value = false }
}

const fmt = (n: any) => new Intl.NumberFormat('ar-SA').format(parseFloat(n) || 0)
const fmtDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'
const typeLabel = (t: string) => ({ company: 'شركة', pos: 'منفذ بيع', service_center: 'مركز خدمة', individual: 'فرد' }[t] ?? t)
const paymentLabel = (p: string) => ({ monthly: 'شهري', quarterly: 'ربع سنوي', annually: 'سنوي', one_time: 'دفعة واحدة', custom: 'مخصص' }[p] ?? p)
const statusLabel = (s: string) => ({ draft: 'مسودة', pending_signature: 'بانتظار التوقيع', active: 'نشط', expired: 'منتهي', terminated: 'ملغي' }[s] ?? s)
const statusClass = (s: string) => ({
  draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  pending_signature: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  expired: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  terminated: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
}[s] ?? '')

onMounted(() => {
  loadTemplate()
  load()
  loadExpiring()
})
</script>
