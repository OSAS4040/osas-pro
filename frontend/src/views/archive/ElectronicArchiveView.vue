<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <header class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">الأرشفة الإلكترونية</h1>
        <p class="page-subtitle">
          عرض سجل المستندات الوصفية المحفوظ في إعدادات المنشأة (<code class="text-xs bg-gray-100 dark:bg-slate-800 px-1 rounded">documents_registry</code>).
          الملفات الثنائية لا تُخزَّن على الخادم؛ يظهر «عرض/تحميل» فقط إذا كان الملف متاحاً في هذه الجلسة (بعد إضافته من صفحة مستندات المنشأة على نفس الجهاز).
        </p>
        <p
          class="mt-2 rounded-xl border border-amber-200/90 bg-amber-50/95 px-3 py-2 text-xs font-medium text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/35 dark:text-amber-100"
          role="status"
        >
          <strong>لا يوجد تخزين مركزي للمرفقات:</strong>
          إن أعدت تحميل الصفحة أو فتحت من جهاز آخر ستظهر البيانات الوصفية فقط دون ملف قابل للتنزيل — هذا سلوك متوقع وليس خطأ.
        </p>
      </div>
      <div class="page-toolbar">
        <RouterLink to="/documents/company" class="btn btn-outline text-sm">
          إدارة مستندات المنشأة
        </RouterLink>
      </div>
    </header>

    <div v-if="loading" class="flex justify-center py-12 text-sm text-gray-500 dark:text-slate-400">
      جاري تحميل سجل المستندات…
    </div>

    <template v-else>
      <div class="panel p-4 space-y-3">
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-[200px]">
            <label class="label">بحث بالاسم</label>
            <input v-model="search" type="search" class="field" placeholder="اسم المستند…" />
          </div>
          <div class="w-full sm:w-44">
            <label class="label">النوع</label>
            <select v-model="filterType" class="field">
              <option value="">كل الأنواع</option>
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="w-full sm:w-40">
            <label class="label">من تاريخ الإنشاء</label>
            <input v-model="filterCreatedFrom" type="date" class="field" />
          </div>
          <div class="w-full sm:w-40">
            <label class="label">إلى تاريخ الإنشاء</label>
            <input v-model="filterCreatedTo" type="date" class="field" />
          </div>
        </div>
      </div>

      <div v-if="!rows.length" class="rounded-xl border border-dashed border-gray-200 dark:border-slate-600 bg-gray-50/80 dark:bg-slate-900/40 px-6 py-14 text-center text-sm text-gray-500 dark:text-slate-400">
        لا توجد مستندات محفوظة في السجل. أضف مستندات وصفية من
        <RouterLink to="/documents/company" class="text-primary-600 dark:text-primary-400 underline">مستندات المنشأة</RouterLink>.
      </div>

      <div v-else class="table-shell overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>اسم المستند</th>
              <th>النوع</th>
              <th>تاريخ الإنشاء</th>
              <th>الحالة</th>
              <th>وجود ملف</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in filteredRows" :key="r.id">
              <td>
                <p class="font-medium text-gray-900 dark:text-slate-100">{{ r.title }}</p>
                <p v-if="r.reference" class="text-xs text-gray-400 font-mono">{{ r.reference }}</p>
              </td>
              <td><span class="badge badge-blue">{{ typeLabel(r.type) }}</span></td>
              <td class="text-sm whitespace-nowrap">{{ formatDate(r.created) }}</td>
              <td><span class="text-xs px-2 py-0.5 rounded-full" :class="statusClass(r)">{{ statusLabel(r) }}</span></td>
              <td class="text-sm">
                <span v-if="r.hasFileInSession" class="text-emerald-700 dark:text-emerald-400">نعم (هذه الجلسة)</span>
                <span v-else class="text-gray-500 dark:text-slate-400">لا</span>
              </td>
              <td class="text-xs space-y-1">
                <template v-if="r.hasFileInSession">
                  <div class="flex flex-wrap gap-2">
                    <a
                      :href="r.effectiveDataUrl"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-primary-600 dark:text-primary-400 hover:underline"
                    >عرض</a>
                    <a
                      :href="r.effectiveDataUrl"
                      :download="safeDownloadName(r.fileName, r.title)"
                      class="text-sky-600 dark:text-sky-400 hover:underline"
                    >تحميل</a>
                  </div>
                </template>
                <p v-else class="text-amber-800 dark:text-amber-200/90 max-w-[14rem] leading-snug">
                  ملف المستند غير متاح — تم حفظ الوصف فقط
                </p>
              </td>
            </tr>
            <tr v-if="!filteredRows.length">
              <td colspan="6" class="table-empty text-center py-8">لا توجد نتائج مطابقة للبحث أو الفلاتر</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import { getSessionDocBlob } from '@/utils/documentsSessionBlobs'

type DocType = 'license' | 'insurance' | 'contract' | 'tax' | 'other'
type NotifyMethod = 'in_app' | 'email' | 'whatsapp'

type RegistryDoc = {
  id: string
  title: string
  type: DocType
  reference: string
  note: string
  expires: string
  fileName: string
  notifyMethod: NotifyMethod
  created: string
}

type Row = RegistryDoc & {
  effectiveDataUrl: string
  hasFileInSession: boolean
}

const DOC_TYPES: DocType[] = ['license', 'insurance', 'contract', 'tax', 'other']
const NOTIFY: NotifyMethod[] = ['in_app', 'email', 'whatsapp']

function isDocType(v: string): v is DocType {
  return (DOC_TYPES as string[]).includes(v)
}
function isNotifyMethod(v: string): v is NotifyMethod {
  return (NOTIFY as string[]).includes(v)
}

function normalizeRegistryEntry(raw: unknown): RegistryDoc | null {
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
    notifyMethod: notifyStr,
    created: typeof o.created === 'string' ? o.created : '',
  }
}

const auth = useAuthStore()
const toast = useToast()
const loading = ref(true)
const docs = ref<RegistryDoc[]>([])
const search = ref('')
const filterType = ref('')
const filterCreatedFrom = ref('')
const filterCreatedTo = ref('')

const typeOptions = [
  { value: 'license', label: 'رخصة' },
  { value: 'insurance', label: 'تأمين' },
  { value: 'contract', label: 'عقد' },
  { value: 'tax', label: 'ضريبي' },
  { value: 'other', label: 'أخرى' },
]

const rows = computed((): Row[] =>
  docs.value.map((d) => {
    const sessionBlob = getSessionDocBlob(d.id)
    const hasFileInSession = Boolean(sessionBlob)
    return {
      ...d,
      effectiveDataUrl: sessionBlob,
      hasFileInSession,
    }
  }),
)

const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  const from = filterCreatedFrom.value ? parseYmdStart(filterCreatedFrom.value) : null
  const to = filterCreatedTo.value ? parseYmdEnd(filterCreatedTo.value) : null

  return rows.value.filter((r) => {
    if (filterType.value && r.type !== filterType.value) return false
    if (q && !r.title.toLowerCase().includes(q)) return false
    if (from || to) {
      const c = r.created ? new Date(r.created).getTime() : NaN
      if (Number.isNaN(c)) return false
      if (from && c < from) return false
      if (to && c > to) return false
    }
    return true
  })
})

function parseYmdStart(ymd: string): number {
  const d = new Date(ymd + 'T00:00:00')
  return d.getTime()
}

function parseYmdEnd(ymd: string): number {
  const d = new Date(ymd + 'T23:59:59.999')
  return d.getTime()
}

function typeLabel(t: DocType): string {
  return typeOptions.find((x) => x.value === t)?.label ?? t
}

function formatDate(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusLabel(r: RegistryDoc): string {
  if (!r.expires) return 'بدون انتهاء'
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const e = new Date(r.expires)
  e.setHours(0, 0, 0, 0)
  const diff = Math.ceil((e.getTime() - today.getTime()) / 86400000)
  if (diff < 0) return 'منتهٍ'
  if (diff <= 30) return 'قريب الانتهاء'
  return 'ساري'
}

function statusClass(r: RegistryDoc): string {
  if (!r.expires) return 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-200'
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const e = new Date(r.expires)
  e.setHours(0, 0, 0, 0)
  const diff = Math.ceil((e.getTime() - today.getTime()) / 86400000)
  if (diff < 0) return 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200'
  if (diff <= 30) return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
  return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
}

function safeDownloadName(fileName: string, title: string): string {
  const base = (fileName && fileName.trim()) || `${title.replace(/[^\w\u0600-\u06FF.-]+/g, '_').slice(0, 80) || 'document'}`
  return base.includes('.') ? base : `${base}.bin`
}

async function load(): Promise<void> {
  loading.value = true
  docs.value = []
  const cid = auth.user?.company_id
  if (!cid) {
    loading.value = false
    toast.warning('الأرشفة الإلكترونية', 'يلزم تسجيل دخول مرتبط بمنشأة لعرض السجل.')
    return
  }
  try {
    const { data } = await apiClient.get(`/companies/${cid}/settings`, { skipGlobalErrorToast: true })
    const rawReg = data?.data?.documents_registry
    if (Array.isArray(rawReg)) {
      docs.value = rawReg.map(normalizeRegistryEntry).filter((x): x is RegistryDoc => Boolean(x && x.title.trim()))
    }
  } catch (e: unknown) {
    toast.error('تعذر التحميل', summarizeAxiosError(e))
    docs.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>
