<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <header class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl flex items-center gap-2">
          <ChatBubbleLeftRightIcon class="w-6 h-6 text-indigo-600" />
          نظام الاتصالات الإدارية
        </h1>
        <p class="page-subtitle">إنشاء المعاملات، التحويل بين الإدارات، التوقيع، والأرشفة الذكية بسجل زمني كامل</p>
      </div>
      <div class="flex gap-2">
        <button class="btn btn-outline" @click="refresh">تحديث</button>
        <button class="btn btn-primary" @click="openCreate = !openCreate">إنشاء معاملة</button>
      </div>
    </header>

    <section v-if="openCreate" class="panel p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">بيانات المعاملة</h3>
      <div class="grid md:grid-cols-2 gap-3">
        <input v-model="draft.subject" class="field" placeholder="عنوان المعاملة" />
        <input v-model="draft.category" class="field" placeholder="التصنيف (مالي، إداري، موارد بشرية...)" />
      </div>
      <div class="grid md:grid-cols-3 gap-3">
        <select v-model="draft.priority" class="field">
          <option value="low">منخفضة</option>
          <option value="normal">عادية</option>
          <option value="high">عالية</option>
          <option value="critical">حرجة</option>
        </select>
        <select v-model="draft.confidentiality" class="field">
          <option value="public">عامة</option>
          <option value="internal">داخلية</option>
          <option value="confidential">سرية</option>
          <option value="strictly_confidential">سرية جدًا</option>
        </select>
        <SmartDatePicker :model-value="draft.dueDate" mode="single" @change="onDraftDueDateChange" />
      </div>
      <div class="grid md:grid-cols-3 gap-3">
        <input v-model="draft.origin" class="field" placeholder="من (الإدارة المرسلة)" />
        <input v-model="draft.destination" class="field" placeholder="إلى (الإدارة المستلمة)" />
        <input v-model="draft.assignedTo" class="field" placeholder="المسند إليه" />
      </div>
      <textarea v-model="draft.body" class="field" rows="4" placeholder="تفاصيل المعاملة..." />
      <div class="flex gap-2">
        <button class="btn btn-primary" :disabled="saving" @click="createTransaction">حفظ المعاملة</button>
        <button class="btn btn-outline" @click="resetDraft">مسح</button>
      </div>
    </section>

    <section class="panel p-3">
      <div class="flex flex-wrap items-center gap-2">
        <button v-for="t in tabs" :key="t.key" class="btn" :class="activeTab === t.key ? 'btn-primary' : 'btn-outline'" @click="setTab(t.key)">
          {{ t.label }}
        </button>
        <div class="mr-auto w-full md:w-80">
          <input v-model="search" class="field" placeholder="بحث بالمرجع أو العنوان أو الجهة..." @input="debouncedLoad" />
        </div>
      </div>
    </section>

    <div class="table-shell">
      <div class="panel-head">
        <h3 class="panel-title">قائمة المعاملات</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>المرجع</th>
              <th>الموضوع</th>
              <th>الأولوية</th>
              <th>السرية</th>
              <th>الجهة/المسؤول</th>
              <th>الاستحقاق</th>
              <th>الحالة</th>
              <th>الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="table-empty">جاري تحميل المعاملات...</td>
            </tr>
            <tr v-for="item in rows" :key="item.id">
              <td class="font-mono text-xs">{{ item.reference }}</td>
              <td>
                <p class="font-medium">{{ item.subject }}</p>
                <p class="text-xs text-gray-500">{{ item.category }}</p>
              </td>
              <td><span class="badge" :class="priorityBadge(item.priority)">{{ priorityLabel(item.priority) }}</span></td>
              <td><span class="badge badge-gray">{{ confidentialityLabel(item.confidentiality) }}</span></td>
              <td>
                <p class="text-xs">{{ item.destination || '—' }}</p>
                <p class="text-xs text-gray-500">{{ item.assigned_to || 'غير مسند' }}</p>
              </td>
              <td>{{ item.due_date || '—' }}</td>
              <td><span class="badge" :class="stateBadge(item.state)">{{ stateLabel(item.state) }}</span></td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <button class="btn btn-outline !px-2 !py-1 text-xs" @click="selectItem(item)">تفاصيل</button>
                  <button class="btn btn-outline !px-2 !py-1 text-xs" :disabled="item.state !== 'draft'" @click="submitItem(item)">إرسال</button>
                  <button class="btn btn-outline !px-2 !py-1 text-xs" @click="openTransfer(item)">تحويل</button>
                  <button class="btn btn-outline !px-2 !py-1 text-xs" @click="openSignature(item)">توقيع</button>
                  <button class="btn btn-outline !px-2 !py-1 text-xs" :disabled="item.archived" @click="openArchive(item)">أرشفة</button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && !rows.length"><td colspan="8" class="table-empty">لا توجد معاملات في هذا التبويب</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <section v-if="selected" class="panel p-4 space-y-3">
      <h3 class="text-sm font-semibold">تفاصيل المعاملة: {{ selected.reference }}</h3>
      <p class="text-sm text-gray-700 dark:text-slate-300">{{ selected.body || 'لا توجد تفاصيل نصية' }}</p>
      <div class="grid md:grid-cols-3 gap-2 text-xs">
        <div class="badge badge-gray">الموقع الحالي: {{ selected.destination || '—' }}</div>
        <div class="badge badge-gray">المسند إليه: {{ selected.assigned_to || '—' }}</div>
        <div class="badge badge-gray">التوقيع: {{ signatureStatusLabel(selected?.signature?.status) }}</div>
      </div>
      <div>
        <h4 class="text-xs font-semibold mb-2">السجل الزمني</h4>
        <ul class="space-y-2">
          <li v-for="(t, idx) in selected.timeline || []" :key="idx" class="text-xs text-gray-600 dark:text-slate-300">
            {{ t.at }} - {{ t.actor }} - {{ t.note }}
          </li>
        </ul>
      </div>
    </section>

    <section v-if="transferTarget" class="panel p-4 space-y-2">
      <h3 class="text-sm font-semibold">تحويل المعاملة: {{ transferTarget.reference }}</h3>
      <div class="grid md:grid-cols-3 gap-2">
        <input v-model="transferForm.to" class="field" placeholder="التحويل إلى (إدارة/مستخدم)" />
        <SmartDatePicker :model-value="transferForm.deadline" mode="single" @change="onTransferDeadlineChange" />
        <input v-model="transferForm.reason" class="field" placeholder="سبب التحويل" />
      </div>
      <div class="flex gap-2">
        <button class="btn btn-primary" @click="confirmTransfer">تأكيد التحويل</button>
        <button class="btn btn-outline" @click="transferTarget = null">إلغاء</button>
      </div>
    </section>

    <section v-if="signatureTarget" class="panel p-4 space-y-2">
      <h3 class="text-sm font-semibold">طلب التوقيع: {{ signatureTarget.reference }}</h3>
      <div class="grid md:grid-cols-3 gap-2">
        <input v-model="signatureForm.signers" class="field" placeholder="الأسماء مفصولة بفاصلة" />
        <SmartDatePicker :model-value="signatureForm.deadline" mode="single" @change="onSignatureDeadlineChange" />
        <label class="inline-flex items-center gap-2 text-xs text-gray-600 px-2">
          <input v-model="signatureForm.ordered" type="checkbox" />
          توقيع متسلسل
        </label>
      </div>
      <div class="flex gap-2">
        <button class="btn btn-primary" @click="requestSignature">طلب التوقيع</button>
        <button class="btn btn-outline" @click="approveByCurrentUser">اعتماد الآن</button>
        <button class="btn btn-outline" @click="signatureTarget = null">إغلاق</button>
      </div>
    </section>

    <section v-if="archiveTarget" class="panel p-4 space-y-2">
      <h3 class="text-sm font-semibold">أرشفة المعاملة: {{ archiveTarget.reference }}</h3>
      <div class="grid md:grid-cols-3 gap-2">
        <input v-model="archiveForm.boxCode" class="field" placeholder="رمز الصندوق" />
        <input v-model="archiveForm.folderCode" class="field" placeholder="رمز المجلد" />
        <input v-model.number="archiveForm.retention" type="number" min="1" class="field" placeholder="مدة الحفظ (بالأشهر)" />
      </div>
      <div class="flex gap-2">
        <button class="btn btn-primary" @click="archiveItem">تأكيد الأرشفة</button>
        <button class="btn btn-outline" @click="archiveTarget = null">إلغاء</button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

type TabKey = 'inbox' | 'outbox' | 'assigned' | 'archived'
type State = 'draft' | 'submitted' | 'under_review' | 'signed' | 'sent' | 'archived' | 'returned' | 'rejected'

const auth = useAuthStore()
const rows = ref<any[]>([])
const selected = ref<any | null>(null)
const loading = ref(false)
const saving = ref(false)
const openCreate = ref(true)
const activeTab = ref<TabKey>('inbox')
const search = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const tabs = [
  { key: 'inbox' as const, label: 'الوارد' },
  { key: 'outbox' as const, label: 'الصادر' },
  { key: 'assigned' as const, label: 'المسند لي' },
  { key: 'archived' as const, label: 'الأرشيف' },
]

const draft = reactive({
  subject: '',
  body: '',
  category: 'إداري',
  priority: 'normal',
  confidentiality: 'internal',
  origin: 'الإدارة العامة',
  destination: '',
  assignedTo: '',
  dueDate: '',
})

const transferTarget = ref<any | null>(null)
const transferForm = reactive({ to: '', reason: '', deadline: '' })
const signatureTarget = ref<any | null>(null)
const signatureForm = reactive({ signers: '', ordered: false, deadline: '' })
const archiveTarget = ref<any | null>(null)
const archiveForm = reactive({ boxCode: '', folderCode: '', retention: 36 })

function onDraftDueDateChange(val: { from: string; to: string }) {
  draft.dueDate = val.from || val.to
}

function onTransferDeadlineChange(val: { from: string; to: string }) {
  transferForm.deadline = val.from || val.to
}

function onSignatureDeadlineChange(val: { from: string; to: string }) {
  signatureForm.deadline = val.from || val.to
}

function setTab(tab: TabKey) {
  activeTab.value = tab
  loadRows()
}

function resetDraft() {
  draft.subject = ''
  draft.body = ''
  draft.category = 'إداري'
  draft.priority = 'normal'
  draft.confidentiality = 'internal'
  draft.origin = 'الإدارة العامة'
  draft.destination = ''
  draft.assignedTo = ''
  draft.dueDate = ''
}

function debouncedLoad() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(loadRows, 250)
}

async function loadRows() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/workshop/communications', {
      params: { tab: activeTab.value, q: search.value.trim() || undefined },
    })
    rows.value = Array.isArray(data?.data) ? data.data : []
    if (selected.value) {
      const still = rows.value.find((r) => r.id === selected.value.id)
      selected.value = still ?? null
    }
  } finally {
    loading.value = false
  }
}

async function createTransaction() {
  if (!draft.subject.trim() || !auth.user?.company_id) return
  saving.value = true
  try {
    await apiClient.post('/workshop/communications', {
      subject: draft.subject.trim(),
      body: draft.body.trim(),
      category: draft.category.trim() || 'إداري',
      priority: draft.priority,
      confidentiality: draft.confidentiality,
      origin: draft.origin.trim(),
      destination: draft.destination.trim(),
      assigned_to: draft.assignedTo.trim(),
      due_date: draft.dueDate || null,
    })
    resetDraft()
    openCreate.value = false
    await loadRows()
  } finally {
    saving.value = false
  }
}

function selectItem(item: any) {
  selected.value = item
}

async function submitItem(item: any) {
  await apiClient.post(`/workshop/communications/${item.id}/submit`)
  await loadRows()
}

function openTransfer(item: any) {
  transferTarget.value = item
  transferForm.to = item.destination || ''
  transferForm.reason = ''
  transferForm.deadline = ''
}

async function confirmTransfer() {
  if (!transferTarget.value || !transferForm.to.trim() || !transferForm.reason.trim()) return
  await apiClient.post(`/workshop/communications/${transferTarget.value.id}/transfer`, {
    to: transferForm.to.trim(),
    reason: transferForm.reason.trim(),
    deadline: transferForm.deadline || null,
  })
  transferTarget.value = null
  await loadRows()
}

function openSignature(item: any) {
  signatureTarget.value = item
  signatureForm.signers = ''
  signatureForm.ordered = false
  signatureForm.deadline = ''
}

async function requestSignature() {
  if (!signatureTarget.value || !signatureForm.signers.trim()) return
  const signers = signatureForm.signers.split(',').map((s) => s.trim()).filter(Boolean)
  if (!signers.length) return
  await apiClient.post(`/workshop/communications/${signatureTarget.value.id}/request-signature`, {
    signers,
    ordered: signatureForm.ordered,
    deadline: signatureForm.deadline || null,
  })
  await loadRows()
}

async function approveByCurrentUser() {
  if (!signatureTarget.value) return
  await apiClient.post(`/workshop/communications/${signatureTarget.value.id}/sign`, {
    signer: auth.user?.name || 'المستخدم الحالي',
  })
  signatureTarget.value = null
  await loadRows()
}

function openArchive(item: any) {
  archiveTarget.value = item
  archiveForm.boxCode = ''
  archiveForm.folderCode = ''
  archiveForm.retention = 36
}

async function archiveItem() {
  if (!archiveTarget.value) return
  await apiClient.post(`/workshop/communications/${archiveTarget.value.id}/archive`, {
    box_code: archiveForm.boxCode.trim() || null,
    folder_code: archiveForm.folderCode.trim() || null,
    retention_months: archiveForm.retention || null,
  })
  archiveTarget.value = null
  await loadRows()
}

async function refresh() {
  await loadRows()
}

function priorityLabel(v: string): string {
  return { low: 'منخفضة', normal: 'عادية', high: 'عالية', critical: 'حرجة' }[v] || v
}
function confidentialityLabel(v: string): string {
  return { public: 'عامة', internal: 'داخلية', confidential: 'سرية', strictly_confidential: 'سرية جدًا' }[v] || v
}
function stateLabel(v: State): string {
  return {
    draft: 'مسودة',
    submitted: 'مرسلة',
    under_review: 'تحت المراجعة',
    signed: 'موقعة',
    sent: 'مرسلة نهائيًا',
    archived: 'مؤرشفة',
    returned: 'معادة',
    rejected: 'مرفوضة',
  }[v] || v
}
function signatureStatusLabel(v?: string): string {
  return { not_requested: 'لم يطلب', pending: 'بانتظار التوقيع', completed: 'مكتمل' }[v || 'not_requested'] || (v || '—')
}
function priorityBadge(v: string): string {
  return v === 'critical' || v === 'high' ? 'badge-red' : v === 'normal' ? 'badge-yellow' : 'badge-gray'
}
function stateBadge(v: State): string {
  if (v === 'archived') return 'badge-gray'
  if (v === 'signed' || v === 'sent') return 'badge-green'
  if (v === 'under_review' || v === 'submitted') return 'badge-blue'
  if (v === 'rejected') return 'badge-red'
  return 'badge-yellow'
}

onMounted(loadRows)
</script>
