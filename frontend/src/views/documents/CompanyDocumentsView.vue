<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <header class="page-head no-print">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">مستندات المنشأة</h1>
        <p class="page-subtitle">
          تنظيم مستندات المنشأة: إضافة بيانات وصفية، تواريخ انتهاء، تذكيرات محلية، تصدير CSV، ومشاركة النص — دون الادّعاء بأن ملفات المرفقات تُخزَّن على الخادم.
        </p>
        <p
          class="mt-2 rounded-xl border border-amber-200/90 bg-amber-50/95 px-3 py-2 text-xs font-medium text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/35 dark:text-amber-100"
          role="status"
        >
          <strong>مهم — ما الذي يُحفظ أين؟</strong>
          أسماء المستندات والنوع والمرجع والتاريخ والملاحظات تُحفظ <strong>على خادم المنشأة</strong> (ضمن إعدادات الشركة) ويستطيع رؤيتها من لديهم صلاحية المنشأة.
          <strong>ملفات PDF والصور والمرفقات لا تُرفع إلى الخادم في هذا العرض:</strong> إن أعدت فتح الصفحة أو استخدمت جهازاً آخر قد ترى اسم الملف فقط دون إمكانية تنزيل المحتوى — أعد اختيار الملف هنا عند الحاجة.
          تنبيهات الانتهاء أدناه <strong>في هذه الجلسة</strong> بناءً على القنوات المحمّلة من الإعدادات؛ لا يُضمن إرسال بريد/واتساب من هذه الشاشة وحدها.
        </p>
      </div>
      <div class="page-toolbar">
        <button type="button" class="btn btn-outline" :disabled="listLoading || saving" @click="exportAll">حفظ نسخة CSV</button>
        <button type="button" class="btn btn-outline" :disabled="listLoading" @click="printView">طباعة</button>
      </div>
    </header>

    <div v-if="listLoading" class="no-print flex justify-center py-10 text-sm text-gray-500 dark:text-slate-400">
      جاري تحميل قائمة المستندات…
    </div>

    <template v-else>
      <div class="panel p-4 space-y-3 no-print">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div><label class="label">اسم المستند</label><input v-model="draft.title" class="field" /></div>
          <div>
            <label class="label">النوع</label>
            <select v-model="draft.type" class="field">
              <option value="license">رخصة</option>
              <option value="insurance">تأمين</option>
              <option value="contract">عقد</option>
              <option value="tax">ضريبي</option>
              <option value="other">أخرى</option>
            </select>
          </div>
          <div><label class="label">رقم مرجعي</label><input v-model="draft.reference" class="field" placeholder="CR/Policy/ID" /></div>
          <div>
            <label class="label">انتهاء</label>
            <SmartDatePicker :model-value="draft.expires" mode="single" @change="onExpiryDateChange" />
          </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
          <div><label class="label">رابط أو ملاحظة</label><input v-model="draft.note" class="field" /></div>
          <div>
            <label class="label">طريقة التنبيه</label>
            <select v-model="draft.notifyMethod" class="field">
              <option value="in_app">داخل النظام</option>
              <option value="email">بريد إلكتروني</option>
              <option value="whatsapp">واتساب</option>
            </select>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
          <label class="btn btn-outline cursor-pointer" :class="{ 'pointer-events-none opacity-50': saving }">
            رفع ملف
            <input type="file" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx" :disabled="saving" @change="onFileChange" />
          </label>
          <button type="button" class="btn btn-outline" :disabled="saving" @click="scanDraft">مسح وتحليل</button>
          <button type="button" class="btn btn-primary inline-flex items-center gap-2" :disabled="saving || !auth.user?.company_id" @click="addDoc">
            <span v-if="saving" class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true" />
            {{ saving ? 'جاري الحفظ…' : 'إضافة مستند' }}
          </button>
          <span v-if="draft.fileName" class="text-xs text-gray-500 dark:text-slate-400">الملف (لهذا الجهاز فقط حتى إعادة التحميل): {{ draft.fileName }}</span>
          <span v-if="!auth.user?.company_id" class="text-xs text-amber-700 dark:text-amber-300">يلزم تسجيل دخول مرتبط بمنشأة لحفظ القائمة على الخادم.</span>
        </div>
        <p v-if="scanResult" class="text-xs rounded-lg bg-primary-50 dark:bg-primary-950/30 border border-primary-100 dark:border-primary-900 px-3 py-2 text-primary-800 dark:text-primary-300">{{ scanResult }}</p>
      </div>

      <div v-if="alerts.length" class="no-print state-error !border-amber-300 !bg-amber-50 dark:!bg-amber-950/40 dark:!border-amber-900 !text-amber-900 dark:!text-amber-200">
        <p class="font-semibold mb-1">تنبيهات المستندات</p>
        <ul class="text-xs space-y-1">
          <li v-for="(a, i) in alerts" :key="i">{{ a }}</li>
        </ul>
      </div>

      <div class="table-shell print-container">
        <div class="table-toolbar no-print">
          <input v-model="search" class="table-search" placeholder="بحث بالاسم أو المرجع أو النوع..." />
          <select v-model="filterType" class="table-filter">
            <option value="">كل الأنواع</option>
            <option value="license">رخصة</option>
            <option value="insurance">تأمين</option>
            <option value="contract">عقد</option>
            <option value="tax">ضريبي</option>
            <option value="other">أخرى</option>
          </select>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>المستند</th><th>النوع</th><th>مرجع</th><th>الانتهاء</th><th>الملف</th><th class="no-print">إجراءات</th></tr></thead>
            <tbody>
              <tr v-for="d in filteredDocs" :key="d.id">
                <td><p class="font-medium">{{ d.title }}</p><p class="text-xs text-gray-400">{{ d.note || '—' }}</p></td>
                <td><span class="badge badge-blue">{{ d.type }}</span></td>
                <td class="font-mono text-xs">{{ d.reference || '—' }}</td>
                <td><span v-if="d.expires" class="text-xs px-2 py-0.5 rounded-full" :class="urgencyClass(d.expires)">{{ d.expires }}</span><span v-else>—</span></td>
                <td class="text-xs text-gray-500">
                  <span>{{ d.fileName || 'بدون ملف' }}</span>
                  <span v-if="d.fileName && !d.fileDataUrl" class="block text-[10px] text-amber-700 dark:text-amber-300 mt-0.5">المرفق غير مخزّن على الخادم — أعد الرفع إن احتجت الملف.</span>
                </td>
                <td class="text-xs no-print">
                  <button type="button" class="text-primary-600 hover:underline ml-2" :disabled="saving" @click="readDetails(d)">قراءة</button>
                  <button type="button" class="text-sky-600 hover:underline ml-2" :disabled="saving" @click="shareDoc(d)">مشاركة</button>
                  <button type="button" class="text-emerald-600 hover:underline ml-2" :disabled="saving" @click="sendDoc(d)">إرسال</button>
                  <button type="button" class="text-red-600 hover:underline" :disabled="saving" @click="remove(d.id)">حذف</button>
                </td>
              </tr>
              <tr v-if="!filteredDocs.length"><td colspan="6" class="table-empty">لا توجد مستندات مطابقة</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { printDocument } from '@/composables/useAppPrint'
import { setSessionDocBlob, removeSessionDocBlob } from '@/utils/documentsSessionBlobs'

/** مفتاح قديم — يُستخدم لترحيل لمرة واحدة فقط ثم يُحذف. */
const LEGACY_LOCAL_STORAGE_KEY = 'company_docs_v2'

type NotifyMethod = 'in_app' | 'email' | 'whatsapp'
type DocType = 'license' | 'insurance' | 'contract' | 'tax' | 'other'
type Doc = {
  id: string
  title: string
  type: DocType
  reference: string
  note: string
  expires: string
  fileName: string
  /** يبقى في الذاكرة لهذه الجلسة فقط؛ لا يُرسل إلى الخادم (حجم/أمان). */
  fileDataUrl: string
  notifyMethod: NotifyMethod
  created: string
}

const DOC_TYPES: DocType[] = ['license', 'insurance', 'contract', 'tax', 'other']
const NOTIFY: NotifyMethod[] = ['in_app', 'email', 'whatsapp']

function isDocType(v: string): v is DocType {
  return (DOC_TYPES as string[]).includes(v)
}
function isNotifyMethod(v: string): v is NotifyMethod {
  return (NOTIFY as string[]).includes(v)
}

const docs = ref<Doc[]>([])
const draft = ref({
  title: '',
  type: 'license' as DocType,
  reference: '',
  note: '',
  expires: '',
  fileName: '',
  fileDataUrl: '',
  notifyMethod: 'in_app' as NotifyMethod,
})
const search = ref('')
const filterType = ref('')
const scanResult = ref('')
const listLoading = ref(true)
const saving = ref(false)
const auth = useAuthStore()
const toast = useToast()
const notifyChannels = ref({ in_app: true, email: false, whatsapp: false, reminder_days: [30, 7, 1] as number[] })

/** نسخة للخادم: بدون بيانات الملف الثنائية. */
function registryPayload(list: Doc[]): Doc[] {
  return list.map((d) => ({ ...d, fileDataUrl: '' }))
}

function normalizeRegistryEntry(raw: unknown): Doc | null {
  if (!raw || typeof raw !== 'object') return null
  const o = raw as Record<string, unknown>
  const typeStr = typeof o.type === 'string' && isDocType(o.type) ? o.type : 'other'
  const notifyStr = typeof o.notifyMethod === 'string' && isNotifyMethod(o.notifyMethod) ? o.notifyMethod : 'in_app'
  const id = typeof o.id === 'string' && o.id ? o.id : crypto.randomUUID()
  const title = typeof o.title === 'string' ? o.title : ''
  return {
    id,
    title,
    type: typeStr,
    reference: typeof o.reference === 'string' ? o.reference : '',
    note: typeof o.note === 'string' ? o.note : '',
    expires: typeof o.expires === 'string' ? o.expires : '',
    fileName: typeof o.fileName === 'string' ? o.fileName : '',
    fileDataUrl: '',
    notifyMethod: notifyStr,
    created: typeof o.created === 'string' ? o.created : new Date().toISOString(),
  }
}

async function persistRegistry(): Promise<void> {
  const cid = auth.user?.company_id
  if (!cid) {
    throw new Error('no_company')
  }
  await apiClient.patch(`/companies/${cid}/settings`, {
    documents_registry: registryPayload(docs.value),
  })
}

async function tryMigrateLegacyLocalOnce(): Promise<void> {
  if (docs.value.length > 0) return
  let rawList: unknown[] = []
  try {
    const raw = localStorage.getItem(LEGACY_LOCAL_STORAGE_KEY)
    if (!raw) return
    const parsed = JSON.parse(raw)
    rawList = Array.isArray(parsed) ? parsed : []
  } catch (e) {
    console.warn('[CompanyDocuments] legacy parse', e)
    toast.warning('بيانات قديمة في المتصفح', 'تعذر قراءة قائمة المستندات المحفوظة سابقاً في هذا المتصفح.')
    return
  }
  if (!rawList.length) return
  const normalized = rawList
    .map(normalizeRegistryEntry)
    .filter((x): x is Doc => Boolean(x && x.title.trim()))
  if (!normalized.length) return
  docs.value = normalized
  try {
    await persistRegistry()
    localStorage.removeItem(LEGACY_LOCAL_STORAGE_KEY)
    toast.success('تم ترحيل المستندات', 'نُقلت قائمة المستندات الوصفية إلى الخادم. المرفقات لا تزال غير مركزية — أعد رفع الملفات عند الحاجة.')
  } catch (e) {
    console.warn('[CompanyDocuments] migrate failed', e)
    docs.value = []
    toast.error('تعذر ترحيل المستندات', 'لم نتمكن من حفظ القائمة على الخادم. تحقق من الاتصال والصلاحيات ثم أعد المحاولة.')
  }
}

async function loadFromServer(): Promise<void> {
  listLoading.value = true
  docs.value = []
  const cid = auth.user?.company_id
  if (!cid) {
    listLoading.value = false
    toast.warning('مستندات المنشأة', 'يلزم تسجيل دخول مرتبط بمنشأة لعرض القائمة من الخادم.')
    return
  }
  try {
    const { data } = await apiClient.get(`/companies/${cid}/settings`)
    const rawReg = data?.data?.documents_registry
    if (Array.isArray(rawReg)) {
      docs.value = rawReg.map(normalizeRegistryEntry).filter((x): x is Doc => Boolean(x))
    }
    if (docs.value.length > 0) {
      try {
        localStorage.removeItem(LEGACY_LOCAL_STORAGE_KEY)
      } catch {
        /* ignore */
      }
    }
    const c = data?.data?.documents_notifications
    if (c && typeof c === 'object') {
      notifyChannels.value = {
        in_app: Boolean(c.in_app),
        email: Boolean(c.email),
        whatsapp: Boolean(c.whatsapp),
        reminder_days: Array.isArray(c.reminder_days)
          ? c.reminder_days.map((x: unknown) => Number(x)).filter((n: number) => Number.isFinite(n))
          : [30, 7, 1],
      }
    }
    await tryMigrateLegacyLocalOnce()
  } catch (e) {
    console.warn('[CompanyDocuments] load', e)
    toast.error('تعذر تحميل المستندات', 'لم نتمكن من جلب قائمة المستندات من الخادم. تحقق من الاتصال ثم أعد تحميل الصفحة.')
    docs.value = []
  } finally {
    listLoading.value = false
  }
}

const alerts = computed(() => {
  const out: string[] = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  for (const d of docs.value) {
    if (!d.expires) continue
    const e = new Date(d.expires)
    e.setHours(0, 0, 0, 0)
    const diff = Math.ceil((e.getTime() - today.getTime()) / 86400000)
    const channels = [
      notifyChannels.value.in_app ? 'داخل النظام' : '',
      notifyChannels.value.email ? 'البريد' : '',
      notifyChannels.value.whatsapp ? 'واتساب' : '',
    ].filter(Boolean).join(' + ')
    const days = notifyChannels.value.reminder_days
    if (diff < 0) out.push(`منتهٍ: ${d.title} — القنوات: ${channels || 'بدون قناة'}`)
    else if (days.includes(diff)) out.push(`تذكير (${diff} يوم): ${d.title} — القنوات: ${channels || 'بدون قناة'}`)
  }
  return out
})

const filteredDocs = computed(() => {
  const q = search.value.trim().toLowerCase()
  return docs.value.filter((d) => {
    if (filterType.value && d.type !== filterType.value) return false
    if (!q) return true
    return `${d.title} ${d.reference} ${d.type} ${d.note}`.toLowerCase().includes(q)
  })
})

function urgencyClass(exp: string) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const e = new Date(exp)
  e.setHours(0, 0, 0, 0)
  const diff = Math.ceil((e.getTime() - today.getTime()) / 86400000)
  if (diff < 0) return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
  if (diff <= 30) return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200'
  return 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-300'
}

async function addDoc() {
  if (!draft.value.title.trim()) {
    toast.warning('اسم المستند', 'يرجى إدخال عنوان للمستند قبل الإضافة.')
    return
  }
  const cid = auth.user?.company_id
  if (!cid) {
    toast.error('تعذر الحفظ', 'لا يوجد معرّف منشأة في الجلسة الحالية.')
    return
  }
  const newDoc: Doc = {
    id: crypto.randomUUID(),
    title: draft.value.title.trim(),
    type: draft.value.type,
    reference: draft.value.reference.trim(),
    note: draft.value.note.trim(),
    expires: draft.value.expires,
    fileName: draft.value.fileName,
    fileDataUrl: draft.value.fileDataUrl,
    notifyMethod: draft.value.notifyMethod,
    created: new Date().toISOString(),
  }
  const previous = docs.value.slice()
  docs.value = [newDoc, ...previous]
  draft.value = { title: '', type: 'license', reference: '', note: '', expires: '', fileName: '', fileDataUrl: '', notifyMethod: 'in_app' }
  scanResult.value = ''
  saving.value = true
  try {
    await persistRegistry()
    if (newDoc.fileDataUrl) {
      setSessionDocBlob(newDoc.id, newDoc.fileDataUrl)
    }
    toast.success('تم الحفظ', 'أُضيف المستند إلى قائمة المنشأة على الخادم. المرفقات الثنائية تبقى على هذا الجهاز فقط لهذه الجلسة.')
  } catch (e) {
    console.warn('[CompanyDocuments] add', e)
    docs.value = previous
    draft.value = {
      title: newDoc.title,
      type: newDoc.type,
      reference: newDoc.reference,
      note: newDoc.note,
      expires: newDoc.expires,
      fileName: newDoc.fileName,
      fileDataUrl: newDoc.fileDataUrl,
      notifyMethod: newDoc.notifyMethod,
    }
    toast.error('تعذر الحفظ', 'لم نتمكن من حفظ المستند على الخادم. أعد المحاولة أو تحقق من الصلاحيات.')
  } finally {
    saving.value = false
  }
}

async function remove(id: string) {
  const cid = auth.user?.company_id
  if (!cid) return
  const previous = docs.value.slice()
  docs.value = docs.value.filter((x) => x.id !== id)
  saving.value = true
  try {
    await persistRegistry()
    removeSessionDocBlob(id)
    toast.success('تم الحذف', 'حُذف المستند من قائمة المنشأة.')
  } catch (e) {
    console.warn('[CompanyDocuments] remove', e)
    docs.value = previous
    toast.error('تعذر الحذف', 'لم نتمكن من تحديث القائمة على الخادم.')
  } finally {
    saving.value = false
  }
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  draft.value.fileName = file.name
  const reader = new FileReader()
  reader.onload = () => {
    draft.value.fileDataUrl = String(reader.result ?? '')
  }
  reader.onerror = () => {
    draft.value.fileName = ''
    draft.value.fileDataUrl = ''
    toast.error('تعذر قراءة الملف', 'جرّب ملفاً أصغر أو صيغة أخرى.')
  }
  reader.readAsDataURL(file)
  input.value = ''
}

function scanDraft() {
  if (!draft.value.fileName && !draft.value.title) return
  const title = draft.value.title || draft.value.fileName
  const maybeType = title.toLowerCase()
  if (maybeType.includes('tax') || maybeType.includes('vat')) draft.value.type = 'tax'
  if (maybeType.includes('insurance')) draft.value.type = 'insurance'
  if (maybeType.includes('contract')) draft.value.type = 'contract'
  scanResult.value = `تم تحليل المستند: "${title}" واقتراح النوع "${draft.value.type}".`
}

function onExpiryDateChange(val: { from: string; to: string }) {
  draft.value.expires = val.from || val.to
}

function readDetails(d: Doc) {
  const info = [
    `المستند: ${d.title}`,
    `النوع: ${d.type}`,
    `المرجع: ${d.reference || '—'}`,
    `ينتهي: ${d.expires || 'غير محدد'}`,
    `طريقة التنبيه: ${d.notifyMethod}`,
  ].join('\n')
  toast.info('تفاصيل المستند', info)
}

async function shareDoc(d: Doc) {
  const text = `مستند: ${d.title}\nالنوع: ${d.type}\nالمرجع: ${d.reference || '—'}`
  if (typeof navigator.share === 'function') {
    try {
      await navigator.share({ title: d.title, text })
      return
    } catch (err: unknown) {
      const name = err && typeof err === 'object' && 'name' in err ? String((err as Error).name) : ''
      if (name === 'AbortError') return
      console.warn('[CompanyDocuments] share', err)
    }
  }
  try {
    await navigator.clipboard.writeText(text)
    toast.success('تم النسخ', 'نُسخت تفاصيل المستند إلى الحافظة.')
  } catch (e) {
    console.warn('[CompanyDocuments] clipboard', e)
    toast.error('تعذر المشاركة', 'انسخ التفاصيل يدوياً من زر «قراءة».')
  }
}

async function sendDoc(d: Doc) {
  const body = encodeURIComponent(`تفاصيل المستند:\n${d.title}\nالمرجع: ${d.reference || '—'}\nالانتهاء: ${d.expires || '—'}`)
  window.open(`mailto:?subject=${encodeURIComponent(`مستند ${d.title}`)}&body=${body}`, '_blank')
}

function exportAll() {
  try {
    const rows = ['title,type,reference,expires,note,fileName,notifyMethod']
    docs.value.forEach((d) =>
      rows.push(
        [d.title, d.type, d.reference, d.expires, d.note, d.fileName, d.notifyMethod]
          .map((x) => `"${String(x ?? '').split('"').join('""')}"`)
          .join(','),
      ),
    )
    const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'company-documents.csv'
    a.click()
    URL.revokeObjectURL(url)
    toast.success('تم التصدير', 'حُفظ ملف CSV على جهازك.')
  } catch (e) {
    console.warn('[CompanyDocuments] csv', e)
    toast.error('تعذر التصدير', 'لم نتمكن من إنشاء ملف CSV.')
  }
}

async function printView() {
  await printDocument()
}

onMounted(() => {
  void loadFromServer()
})
</script>
