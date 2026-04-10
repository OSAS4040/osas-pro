<template>
  <div class="app-shell-page space-y-6" dir="rtl">
    <NavigationSourceHint />
    <div class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">الحوكمة والسياسات</h1>
        <p class="page-subtitle">إدارة قواعد الأعمال، الموافقات، التدقيق، والتنبيهات</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div v-for="stat in stats" :key="stat.label"
           class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm"
      >
        <div class="flex items-center gap-3">
          <div :class="stat.iconBg" class="w-10 h-10 rounded-lg flex items-center justify-center text-xl">
            {{ stat.icon }}
          </div>
          <div>
            <div class="text-2xl font-bold text-gray-900">{{ stat.value }}</div>
            <div class="text-xs text-gray-500">{{ stat.label }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="border-b border-gray-100 flex">
        <button v-for="tab in tabs" :key="tab.key"
                :class="activeTab === tab.key
                  ? 'border-b-2 border-indigo-600 text-indigo-600 bg-indigo-50'
                  : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-3 text-sm font-medium transition-colors"
                @click="activeTab = tab.key"
        >
          {{ tab.label }}
          <span v-if="tab.badge" class="mr-1 inline-flex items-center justify-center w-5 h-5 text-xs rounded-full bg-red-100 text-red-600">
            {{ tab.badge }}
          </span>
        </button>
      </div>

      <!-- Policies Tab -->
      <div v-if="activeTab === 'policies'" class="p-5">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-semibold text-gray-800">قواعد السياسات</h2>
          <button class="btn btn-primary" @click="showPolicyForm = true">
            <span>＋</span> سياسة جديدة
          </button>
        </div>

        <div class="table-shell overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr class="text-right bg-gray-50">
                <th class="px-4 py-3 text-gray-500 font-medium">الكود</th>
                <th class="px-4 py-3 text-gray-500 font-medium">المشغّل</th>
                <th class="px-4 py-3 text-gray-500 font-medium">القيمة</th>
                <th class="px-4 py-3 text-gray-500 font-medium">الإجراء</th>
                <th class="px-4 py-3 text-gray-500 font-medium">الحالة</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingPolicies">
                <td colspan="6" class="text-center py-8 text-gray-400">جارٍ التحميل…</td>
              </tr>
              <tr v-else-if="!policies.length">
                <td colspan="6" class="text-center py-8 text-gray-400">لا توجد سياسات بعد</td>
              </tr>
              <tr v-for="p in policies" v-else :key="p.id">
                <td class="px-4 py-3 font-mono text-indigo-700">{{ p.code }}</td>
                <td class="px-4 py-3 text-gray-600">{{ operatorLabel(p.operator) }}</td>
                <td class="px-4 py-3 font-semibold">{{ p.value?.join?.(', ') ?? p.value }}</td>
                <td class="px-4 py-3">
                  <span :class="actionClass(p.action)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ actionLabel(p.action) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                        class="px-2 py-0.5 rounded-full text-xs"
                  >
                    {{ p.is_active ? 'فعّالة' : 'معطّلة' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button class="text-red-400 hover:text-red-600 text-xs" @click="deletePolicy(p.id)">حذف</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Policy Evaluator -->
        <div class="mt-6 p-4 bg-indigo-50 rounded-lg border border-indigo-100">
          <h3 class="font-semibold text-indigo-800 mb-3">اختبار سياسة</h3>
          <div class="flex gap-3 items-end">
            <div class="flex-1">
              <label class="text-xs text-gray-600 mb-1 block">كود السياسة</label>
              <input v-model="evalCode" type="text" placeholder="e.g. discount.max" class="field" />
            </div>
            <div class="flex-1">
              <label class="text-xs text-gray-600 mb-1 block">القيمة</label>
              <input v-model.number="evalValue" type="number" class="field" />
            </div>
            <button class="btn btn-primary" @click="evaluatePolicy">
              اختبر
            </button>
          </div>
          <div v-if="evalResult" class="mt-3">
            <span :class="evalResult.passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  class="px-3 py-1.5 rounded-lg text-sm font-medium"
            >
              {{ evalResult.passed ? '✅ مقبول' : `❌ ${actionLabel(evalResult.action)}` }}
            </span>
          </div>
        </div>
      </div>

      <!-- Workflows Tab -->
      <div v-if="activeTab === 'workflows'" class="p-5">
        <div class="flex gap-2 mb-4">
          <button v-for="s in ['pending','approved','rejected','all']" :key="s"
                  :class="wfStatus === s ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                  class="px-3 py-1.5 rounded-lg text-sm"
                  @click="wfStatus = s; loadWorkflows()"
          >
            {{ { pending: 'معلقة', approved: 'معتمدة', rejected: 'مرفوضة', all: 'الكل' }[s] }}
          </button>
        </div>

        <div v-if="loadingWf" class="text-center py-8 text-gray-400">جارٍ التحميل…</div>
        <div v-else-if="!workflows.data?.length" class="text-center py-8 text-gray-400">لا توجد طلبات</div>
        <div v-else class="space-y-3">
          <div v-for="wf in workflows.data" :key="wf.id"
               class="border border-gray-100 rounded-lg p-4 hover:bg-gray-50"
          >
            <div class="flex justify-between items-start">
              <div>
                <div class="font-medium text-gray-800">{{ wf.subject_type?.split('\\').pop() }} #{{ wf.subject_id }}</div>
                <div class="text-xs text-gray-500 mt-1">
                  طلب من: {{ wf.requester?.name }} — {{ formatDate(wf.created_at) }}
                </div>
                <div v-if="wf.requester_note" class="text-xs text-gray-600 mt-1 italic">{{ wf.requester_note }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span :class="statusClass(wf.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ statusLabel(wf.status) }}
                </span>
                <template v-if="wf.status === 'pending'">
                  <button class="bg-green-100 text-green-700 px-3 py-1 rounded-lg text-xs hover:bg-green-200"
                          @click="resolveWorkflow(wf.id, 'approve')"
                  >
                    موافقة
                  </button>
                  <button class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-xs hover:bg-red-200"
                          @click="resolveWorkflow(wf.id, 'reject')"
                  >
                    رفض
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Audit Tab -->
      <div v-if="activeTab === 'audit'" class="p-5">
        <div class="flex gap-3 mb-4">
          <input v-model="auditFilter.action" placeholder="نوع الفعل" class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-40"
                 @input="loadAuditLogs"
          />
          <div class="min-w-[260px]">
            <SmartDatePicker mode="range" :from-value="auditFilter.from" :to-value="auditFilter.to" @change="onAuditDateRangeChange" />
          </div>
        </div>

        <div v-if="loadingAudit" class="text-center py-8 text-gray-400">جارٍ التحميل…</div>
        <div v-else class="table-shell overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr class="bg-gray-50 text-right">
                <th class="px-4 py-2 text-gray-500 font-medium">الفعل</th>
                <th class="px-4 py-2 text-gray-500 font-medium">الكيان</th>
                <th class="px-4 py-2 text-gray-500 font-medium">المستخدم</th>
                <th class="px-4 py-2 text-gray-500 font-medium">التاريخ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!auditLogs.data?.length">
                <td colspan="4" class="text-center py-6 text-gray-400">لا توجد سجلات</td>
              </tr>
              <template v-else>
                <tr v-for="log in auditLogs.data" :key="log.id">
                  <td class="px-4 py-2 text-sm text-gray-800 dark:text-slate-200" :title="log.action">
                    {{ formatAuditAction(log.action) }}
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-600 dark:text-slate-300">
                    {{ formatAuditSubject(log.subject_type, log.subject_id) }}
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-600 dark:text-slate-300">
                    {{ formatAuditUserId(log.user_id) }}
                  </td>
                  <td class="px-4 py-2 text-gray-400 dark:text-slate-500 text-xs">{{ formatDate(log.created_at) }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Alerts Tab -->
      <div v-if="activeTab === 'alerts'" class="p-5">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-semibold text-gray-800">
            التنبيهات
            <span v-if="unreadCount" class="mr-2 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">{{ unreadCount }}</span>
          </h2>
          <button class="text-sm text-indigo-600 hover:underline" @click="markAllRead">تعليم الكل كمقروء</button>
        </div>

        <div v-if="!alerts.data?.length" class="text-center py-8 text-gray-400">لا توجد تنبيهات</div>
        <div v-else class="space-y-2">
          <div v-for="a in alerts.data" :key="a.id"
               :class="a.is_read ? 'opacity-60' : 'border-r-4 border-indigo-500'"
               class="bg-gray-50 rounded-lg p-3 text-sm"
          >
            <div class="flex justify-between">
              <span :class="severityClass(a.severity)" class="text-xs font-medium px-2 py-0.5 rounded-full">
                {{ ({ info: 'معلومة', warning: 'تحذير', critical: 'حرج' } as Record<string,string>)[a.severity] ?? a.severity }}
              </span>
              <span class="text-xs text-gray-400">{{ formatDate(a.created_at) }}</span>
            </div>
            <p class="mt-1 text-gray-700">{{ a.message }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Policy Form Modal -->
    <div v-if="showPolicyForm" class="modal-overlay">
      <div class="modal-box p-6 max-w-md">
        <h3 class="font-bold text-gray-900 text-lg mb-4">سياسة جديدة</h3>
        <form class="form-shell" @submit.prevent="savePolicy">
          <div>
            <label class="text-sm text-gray-600 mb-1 block">الكود <span class="text-red-500">*</span></label>
            <input v-model="policyForm.code" required placeholder="discount.max"
                   class="field"
            />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600 mb-1 block">المشغّل</label>
              <select v-model="policyForm.operator" class="field">
                <option value="lte">أقل من أو يساوي (≤)</option>
                <option value="gte">أكبر من أو يساوي (≥)</option>
                <option value="eq">يساوي (=)</option>
                <option value="between">بين (between)</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-gray-600 mb-1 block">القيمة (أو min,max)</label>
              <input v-model="policyForm.valueRaw" required placeholder="30"
                     class="field"
              />
            </div>
          </div>
          <div>
            <label class="text-sm text-gray-600 mb-1 block">الإجراء عند المخالفة</label>
            <select v-model="policyForm.action" class="field">
              <option value="require_approval">يحتاج موافقة</option>
              <option value="block">حجب العملية</option>
              <option value="alert">تنبيه فقط</option>
            </select>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-outline" @click="showPolicyForm = false">إلغاء</button>
            <button type="submit" :disabled="saving" class="btn btn-primary disabled:opacity-50">
              {{ saving ? 'جارٍ الحفظ…' : 'حفظ' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { appConfirm } from '@/services/appConfirmDialog'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { formatAuditAction, formatAuditSubject, formatAuditUserId } from '@/utils/governanceAuditLabels'

const activeTab  = ref<'policies'|'workflows'|'audit'|'alerts'>('policies')
const tabs = computed((): { key: 'policies'|'workflows'|'audit'|'alerts'; label: string; badge?: number }[] => [
  { key: 'policies',  label: 'السياسات' },
  { key: 'workflows', label: 'الموافقات', badge: pendingCount.value || 0 },
  { key: 'audit',     label: 'سجل التدقيق' },
  { key: 'alerts',    label: 'التنبيهات', badge: unreadCount.value || 0 },
])

// ── Policies ──
const policies       = ref<any[]>([])
const loadingPolicies = ref(false)
const showPolicyForm = ref(false)
const saving         = ref(false)
const policyForm     = ref({ code: '', operator: 'lte', valueRaw: '', action: 'require_approval' })

const evalCode   = ref('')
const evalValue  = ref(0)
const evalResult = ref<any>(null)

async function loadPolicies() {
  loadingPolicies.value = true
  try {
    const r = await api.get('/governance/policies')
    policies.value = r.data.data ?? []
  } finally { loadingPolicies.value = false }
}

async function savePolicy() {
  saving.value = true
  try {
    const parts = policyForm.value.valueRaw.split(',').map(Number)
    await api.post('/governance/policies', {
      code:      policyForm.value.code,
      operator:  policyForm.value.operator,
      value:     parts,
      action:    policyForm.value.action,
      is_active: true,
    })
    showPolicyForm.value = false
    policyForm.value = { code: '', operator: 'lte', valueRaw: '', action: 'require_approval' }
    await loadPolicies()
    await loadStats()
  } finally { saving.value = false }
}

async function deletePolicy(id: number) {
  const ok = await appConfirm({
    title: 'حذف السياسة',
    message: 'هل تريد حذف هذه السياسة؟',
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  await api.delete(`/governance/policies/${id}`)
  await loadPolicies()
  await loadStats()
}

async function evaluatePolicy() {
  const r = await api.post('/governance/policies/evaluate', { code: evalCode.value, value: evalValue.value })
  evalResult.value = r.data
}

// ── Workflows ──
const workflows   = ref<any>({})
const loadingWf   = ref(false)
const wfStatus    = ref('pending')
const pendingCount = ref(0)

async function loadWorkflows() {
  loadingWf.value = true
  try {
    const r = await api.get(`/governance/workflows?status=${wfStatus.value}`)
    workflows.value = r.data
    if (wfStatus.value === 'pending') pendingCount.value = r.data.total ?? 0
  } finally { loadingWf.value = false }
}

async function resolveWorkflow(id: number, action: 'approve'|'reject') {
  const note = prompt(action === 'approve' ? 'ملاحظة الاعتماد (اختياري)' : 'سبب الرفض') ?? ''
  await api.post(`/governance/workflows/${id}/${action}`, { note })
  await loadWorkflows()
  await loadStats()
}

// ── Audit Logs ──
const auditLogs   = ref<any>({})
const loadingAudit = ref(false)
const auditFilter  = ref({ action: '', from: '', to: '' })

async function loadAuditLogs() {
  loadingAudit.value = true
  try {
    const params = new URLSearchParams()
    if (auditFilter.value.action) params.set('action', auditFilter.value.action)
    if (auditFilter.value.from)   params.set('from', auditFilter.value.from)
    if (auditFilter.value.to)     params.set('to', auditFilter.value.to)
    const r = await api.get(`/governance/audit-logs?${params}`)
    auditLogs.value = r.data
  } finally { loadingAudit.value = false }
}

function onAuditDateRangeChange(val: { from: string; to: string }) {
  auditFilter.value.from = val.from
  auditFilter.value.to = val.to
  loadAuditLogs()
}

// ── Alerts ──
const alerts      = ref<any>({})
const unreadCount = ref(0)

async function loadAlerts() {
  const r = await api.get('/governance/alerts/me')
  alerts.value     = r.data
  unreadCount.value = r.data.unread_count ?? 0
}

async function markAllRead() {
  await api.post('/governance/alerts/mark-read', { ids: [] })
  unreadCount.value = 0
  await loadAlerts()
}

// ── Stats ──
const stats = ref([
  { label: 'السياسات الفعّالة', value: '—', icon: '📋', iconBg: 'bg-indigo-100' },
  { label: 'موافقات معلقة',    value: '—', icon: '⏳', iconBg: 'bg-amber-100'  },
  { label: 'سجلات التدقيق',   value: '—', icon: '🔍', iconBg: 'bg-blue-100'   },
  { label: 'تنبيهات غير مقروءة', value: '—', icon: '🔔', iconBg: 'bg-red-100'  },
])

async function loadStats() {
  stats.value[0].value = String(policies.value.filter(p => p.is_active).length)
  stats.value[1].value = String(pendingCount.value)
  stats.value[3].value = String(unreadCount.value)
}

// ── Helpers ──
function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleString('ar-SA', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function operatorLabel(op: string) {
  return { lte: '≤', gte: '≥', eq: '=', neq: '≠', in: 'ضمن', not_in: 'خارج', between: 'بين' }[op] ?? op
}
function actionLabel(a: string) {
  return { require_approval: 'يحتاج موافقة', block: 'محجوب', alert: 'تنبيه' }[a] ?? a
}
function actionClass(a: string) {
  return { require_approval: 'bg-amber-100 text-amber-700', block: 'bg-red-100 text-red-700', alert: 'bg-blue-100 text-blue-700' }[a] ?? ''
}
function statusLabel(s: string) {
  return { pending: 'معلق', approved: 'معتمد', rejected: 'مرفوض', cancelled: 'ملغى' }[s] ?? s
}
function statusClass(s: string) {
  return { pending: 'bg-amber-100 text-amber-700', approved: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700', cancelled: 'bg-gray-100 text-gray-500' }[s] ?? ''
}
function severityClass(s: string) {
  return { info: 'bg-blue-100 text-blue-700', warning: 'bg-amber-100 text-amber-700', critical: 'bg-red-100 text-red-700' }[s] ?? ''
}

onMounted(async () => {
  await Promise.all([loadPolicies(), loadWorkflows(), loadAuditLogs(), loadAlerts()])
  await loadStats()
  stats.value[2].value = String(auditLogs.value.total ?? 0)
})
</script>
