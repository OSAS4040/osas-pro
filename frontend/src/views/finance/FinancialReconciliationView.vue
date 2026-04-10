<template>
  <div class="space-y-6 pb-10" dir="rtl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">المطابقة المالية</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
          مراجعة نتائج تطابق الفواتير مع القيود والسجلات — بيانات مقيّدة بمنشأتك فقط.
        </p>
      </div>
      <button
        type="button"
        class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800"
        :disabled="loading"
        @click="reloadAll"
      >
        {{ loading ? 'جارٍ التحديث...' : 'تحديث' }}
      </button>
    </div>

    <p v-if="pageError" class="text-sm text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg px-3 py-2">{{ pageError }}</p>

    <!-- ملخص -->
    <section v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-4">
        <p class="text-xs text-gray-500">حالة المطابقة</p>
        <p
          class="text-lg font-semibold mt-1"
          :class="reconciliationHealthTextClass(summary.latest_reconciliation_health)"
        >
          {{ reconciliationHealthDisplay(summary.latest_reconciliation_health).label }}
        </p>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-4">
        <p class="text-xs text-gray-500">مفتوحة</p>
        <p class="text-lg font-semibold text-amber-700 dark:text-amber-400 mt-1">{{ summary.open_findings ?? 0 }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-4">
        <p class="text-xs text-gray-500">غير محسومة</p>
        <p class="text-lg font-semibold text-gray-900 dark:text-slate-100 mt-1">{{ summary.unresolved_findings ?? 0 }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-4">
        <p class="text-xs text-gray-500">آخر تشغيل ناجح</p>
        <p class="text-sm text-gray-800 dark:text-slate-200 mt-1 truncate tabular-nums">
          {{ formatReconciliationDateTime(summary.last_successful_run?.completed_at) }}
        </p>
      </div>
    </section>

    <!-- تبويبات داخلية -->
    <div class="flex gap-1 border-b border-gray-200 dark:border-slate-700">
      <button
        v-for="t in subTabs"
        :key="t.id"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="subTab === t.id ? 'border-primary-600 text-primary-700 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700'"
        @click="subTab = t.id"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- الملاحظات -->
    <div v-show="subTab === 'findings'" class="rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800/40">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-gray-500 bg-gray-50 dark:bg-slate-900/50">
            <tr>
              <th class="px-3 py-2 text-right">#</th>
              <th class="px-3 py-2 text-right">النوع</th>
              <th class="px-3 py-2 text-right">الحالة</th>
              <th class="px-3 py-2 text-right">مرجع</th>
              <th class="px-3 py-2 text-right">تاريخ</th>
              <th class="px-3 py-2 w-28"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in findingsRows" :key="f.id" class="border-t border-gray-100 dark:border-slate-700">
              <td class="px-3 py-2 font-mono">{{ f.id }}</td>
              <td class="px-3 py-2">{{ reconciliationFindingTypeLabelAr(f.finding_type) }}</td>
              <td class="px-3 py-2">
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="findingStatusPillClass(f.status)"
                >{{ reconciliationFindingStatusLabelAr(f.status) }}</span>
              </td>
              <td class="px-3 py-2 text-xs">
                <span class="text-gray-700 dark:text-slate-300">{{ reconciliationReferenceTypeLabelAr(f.reference_type) }}</span>
                <span v-if="f.reference_id != null" dir="ltr" class="font-mono ms-1.5 inline-block">{{ f.reference_id }}</span>
              </td>
              <td class="px-3 py-2 text-xs tabular-nums">{{ formatReconciliationDateTime(f.created_at) }}</td>
              <td class="px-3 py-2">
                <button type="button" class="text-xs text-primary-600 hover:underline" @click="openFinding(f.id)">تفاصيل</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!findingsRows.length && !loading" class="p-6 text-center text-sm text-gray-400">لا توجد ملاحظات مسجّلة لمنشأتك.</p>
    </div>

    <!-- التشغيلات -->
    <div v-show="subTab === 'runs'" class="rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800/40">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-gray-500 bg-gray-50 dark:bg-slate-900/50">
            <tr>
              <th class="px-3 py-2 text-right">#</th>
              <th class="px-3 py-2 text-right">التاريخ</th>
              <th class="px-3 py-2 text-right">الحالة</th>
              <th class="px-3 py-2 text-right">حالات مكتشفة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in runsRows" :key="r.id" class="border-t border-gray-100 dark:border-slate-700">
              <td class="px-3 py-2 font-mono">{{ r.id }}</td>
              <td class="px-3 py-2 tabular-nums">{{ formatReconciliationDateOnly(r.run_date) }}</td>
              <td class="px-3 py-2">
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="runStatusPillClass(r.execution_status)"
                >{{ reconciliationRunStatusLabelAr(r.execution_status) }}</span>
              </td>
              <td class="px-3 py-2">{{ r.detected_cases ?? 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!runsRows.length && !loading" class="p-6 text-center text-sm text-gray-400">لا توجد تشغيلات مسجّلة.</p>
    </div>

    <!-- تفاصيل ملاحظة + تغيير حالة (مديرون بصلاحية users.update على الخادم) -->
    <Teleport to="body">
      <div
        v-if="detailOpen"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50"
        @click.self="detailOpen = false"
      >
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-600 max-w-lg w-full max-h-[85vh] overflow-y-auto p-5 space-y-3" @click.stop>
          <h3 class="font-bold text-gray-900 dark:text-slate-100">تفاصيل الملاحظة</h3>
          <pre v-if="detailJson" class="body-text-sm font-mono bg-gray-50 dark:bg-slate-800 p-3 rounded-lg overflow-x-auto whitespace-pre-wrap">{{ detailJson }}</pre>
          <div v-if="canUpdateStatus" class="space-y-2 border-t border-gray-100 dark:border-slate-700 pt-3">
            <p class="text-xs font-medium text-gray-700 dark:text-slate-300">تحديث الحالة (يتطلب صلاحية إدارة المستخدمين)</p>
            <select v-model="statusForm.status" class="w-full px-3 py-2 border rounded-lg text-sm border-gray-300 dark:border-slate-600 dark:bg-slate-800">
              <option value="acknowledged">مُقرّ بها</option>
              <option value="resolved">محسومة</option>
              <option value="false_positive">إيجابية خاطئة</option>
            </select>
            <textarea
              v-model="statusForm.note"
              rows="2"
              class="w-full px-3 py-2 border rounded-lg text-sm border-gray-300 dark:border-slate-600 dark:bg-slate-800"
              placeholder="ملاحظة المراجعة (مطلوبة عند المحسومة / إيجابية خاطئة)"
            />
            <button
              type="button"
              class="w-full py-2 rounded-lg bg-primary-600 text-white text-sm disabled:opacity-50"
              :disabled="statusSaving"
              @click="submitStatus"
            >
              {{ statusSaving ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
            <p v-if="statusError" class="text-xs text-red-600">{{ statusError }}</p>
          </div>
          <button type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="detailOpen = false">إغلاق</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import {
  reconciliationFindingStatusLabelAr,
  reconciliationFindingTypeLabelAr,
  reconciliationHealthDisplay,
  reconciliationReferenceTypeLabelAr,
  reconciliationRunStatusLabelAr,
  type ReconciliationHealthTone,
} from '@/utils/financialReconciliationLabels'
import { localizeBackendMessage } from '@/utils/runtimeLocale'

const auth = useAuthStore()
const loading = ref(false)
const pageError = ref('')

type ReconciliationSummary = {
  latest_reconciliation_health?: string
  open_findings?: number
  unresolved_findings?: number
  last_successful_run?: { completed_at?: string | null } | null
}

type FindingRow = {
  id: number
  finding_type?: string
  status?: string
  reference_type?: string
  reference_id?: number | null
  created_at?: string
}

type RunRow = {
  id: number
  run_date?: string
  execution_status?: string
  detected_cases?: number
}

const summary = ref<ReconciliationSummary | null>(null)
const findingsRaw = ref<unknown>(null)
const runsRaw = ref<unknown>(null)
const subTab = ref<'findings' | 'runs'>('findings')
const subTabs = [
  { id: 'findings' as const, label: 'الملاحظات' },
  { id: 'runs' as const, label: 'التشغيلات' },
]

const detailOpen = ref(false)
const detailJson = ref('')
const selectedFindingId = ref<number | null>(null)
const statusForm = ref({ status: 'acknowledged', note: '' })
const statusSaving = ref(false)
const statusError = ref('')

const canUpdateStatus = computed(() => auth.hasPermission('users.update'))

function paginatedFindings(raw: unknown): FindingRow[] {
  if (raw == null) return []
  const asRows = (x: unknown[]): FindingRow[] =>
    x.filter((r): r is FindingRow => typeof r === 'object' && r !== null && typeof (r as FindingRow).id === 'number')
  if (Array.isArray(raw)) return asRows(raw)
  if (typeof raw === 'object' && raw !== null && Array.isArray((raw as { data?: unknown[] }).data)) {
    return asRows((raw as { data: unknown[] }).data)
  }
  return []
}

function paginatedRuns(raw: unknown): RunRow[] {
  if (raw == null) return []
  const asRows = (x: unknown[]): RunRow[] =>
    x.filter((r): r is RunRow => typeof r === 'object' && r !== null && typeof (r as RunRow).id === 'number')
  if (Array.isArray(raw)) return asRows(raw)
  if (typeof raw === 'object' && raw !== null && Array.isArray((raw as { data?: unknown[] }).data)) {
    return asRows((raw as { data: unknown[] }).data)
  }
  return []
}

const findingsRows = computed(() => paginatedFindings(findingsRaw.value))

const runsRows = computed(() => paginatedRuns(runsRaw.value))

function reconciliationHealthTextClass(raw: string | undefined): string {
  const { tone } = reconciliationHealthDisplay(raw)
  const map: Record<ReconciliationHealthTone, string> = {
    ok: 'text-emerald-700 dark:text-emerald-400',
    warn: 'text-amber-700 dark:text-amber-400',
    bad: 'text-red-700 dark:text-red-400',
    neutral: 'text-gray-900 dark:text-slate-100',
  }
  return map[tone]
}

function findingStatusPillClass(status: string | undefined): string {
  const v = String(status ?? '')
    .trim()
    .toLowerCase()
  if (v === 'open') return 'bg-amber-100 text-amber-900 dark:bg-amber-900/35 dark:text-amber-200'
  if (v === 'resolved' || v === 'false_positive') {
    return 'bg-slate-200 text-slate-800 dark:bg-slate-600 dark:text-slate-100'
  }
  if (v === 'acknowledged') return 'bg-sky-100 text-sky-900 dark:bg-sky-900/35 dark:text-sky-200'
  return 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-200'
}

function runStatusPillClass(status: string | undefined): string {
  const v = String(status ?? '')
    .trim()
    .toLowerCase()
  if (v === 'succeeded') return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/35 dark:text-emerald-200'
  if (v === 'failed') return 'bg-red-100 text-red-900 dark:bg-red-900/35 dark:text-red-200'
  if (v === 'running') return 'bg-amber-100 text-amber-900 dark:bg-amber-900/35 dark:text-amber-200'
  return 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-200'
}

function formatReconciliationDateTime(v: unknown): string {
  if (v == null || v === '') return '—'
  const d = new Date(String(v))
  if (Number.isNaN(d.getTime())) {
    return String(v).replace('T', ' ').slice(0, 19)
  }
  try {
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    }).format(d)
  } catch {
    return d.toISOString().replace('T', ' ').slice(0, 19)
  }
}

function formatReconciliationDateOnly(v: unknown): string {
  if (v == null || v === '') return '—'
  const s = String(v).trim()
  const d = /^\d{4}-\d{2}-\d{2}$/.test(s) ? new Date(s + 'T12:00:00') : new Date(s)
  if (Number.isNaN(d.getTime())) return s
  try {
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }).format(d)
  } catch {
    return s
  }
}

async function reloadAll() {
  pageError.value = ''
  loading.value = true
  try {
    const [sumRes, findRes, runRes] = await Promise.all([
      apiClient.get('/financial-reconciliation/summary'),
      apiClient.get('/financial-reconciliation/findings', { params: { per_page: 50 } }),
      apiClient.get('/financial-reconciliation/runs', { params: { per_page: 30 } }),
    ])
    summary.value = (sumRes.data?.data as ReconciliationSummary) ?? null
    findingsRaw.value = findRes.data?.data ?? null
    runsRaw.value = runRes.data?.data ?? null
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    pageError.value = localizeBackendMessage(msg) ?? 'تعذّر تحميل بيانات المطابقة.'
  } finally {
    loading.value = false
  }
}

async function openFinding(id: number) {
  selectedFindingId.value = id
  statusForm.value = { status: 'acknowledged', note: '' }
  statusError.value = ''
  try {
    const { data } = await apiClient.get(`/financial-reconciliation/findings/${id}`)
    const payload = data?.data
    detailJson.value = JSON.stringify(payload, null, 2)
    detailOpen.value = true
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    pageError.value = localizeBackendMessage(msg) ?? 'تعذّر تحميل التفاصيل.'
  }
}

async function submitStatus() {
  const id = selectedFindingId.value
  if (!id) return
  statusError.value = ''
  statusSaving.value = true
  try {
    await apiClient.patch(`/financial-reconciliation/findings/${id}/status`, {
      status: statusForm.value.status,
      note: statusForm.value.note.trim() || undefined,
    })
    detailOpen.value = false
    await reloadAll()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    statusError.value = localizeBackendMessage(msg) ?? 'تعذّر التحديث.'
  } finally {
    statusSaving.value = false
  }
}

onMounted(() => {
  void reloadAll()
})
</script>
