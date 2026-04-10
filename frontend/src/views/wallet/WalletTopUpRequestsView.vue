<template>
  <div class="app-shell-page" dir="rtl">
    <NavigationSourceHint />
    <div class="page-head flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="page-title-xl flex items-center gap-2">
          <QueueListIcon class="w-7 h-7 text-primary-600" />
          طلبات شحن الرصيد
        </h1>
        <p class="page-subtitle">
          تقديم طلب شحن مع إيصال؛ لا يُضاف الرصيد إلا بعد اعتماد المراجعة.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink to="/wallet" class="btn btn-outline text-sm">
          المحفظة
        </RouterLink>
        <button
          v-if="canCreate"
          type="button"
          class="btn btn-primary text-sm"
          @click="openCreate"
        >
          <PlusCircleIcon class="w-4 h-4" />
          طلب شحن جديد
        </button>
      </div>
    </div>

    <div v-if="!canAccessPage" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
      لا تملك صلاحية الوصول إلى طلبات الشحن.
    </div>

    <template v-else>
      <div class="flex flex-wrap gap-2 mb-5">
        <button
          v-if="showMyTab"
          type="button"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors"
          :class="tab === 'my' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-200'"
          @click="tab = 'my'"
        >
          طلباتي
        </button>
        <button
          v-if="canReview"
          type="button"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors"
          :class="tab === 'review' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-200'"
          @click="tab = 'review'"
        >
          مراجعة الطلبات
        </button>
      </div>

      <!-- طلباتي -->
      <div v-show="tab === 'my'" class="space-y-4">
        <div v-if="myLoading" class="flex justify-center py-16">
          <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin" />
        </div>
        <div v-else-if="!myRows.length" class="text-center py-16 text-gray-500 dark:text-slate-400 text-sm">
          لا توجد طلبات مسجّلة لك بعد.
        </div>
        <div v-else class="table-shell overflow-x-auto">
          <table class="data-table min-w-[720px]">
            <thead>
              <tr>
                <th class="px-4 py-3 text-right font-semibold">#</th>
                <th class="px-4 py-3 text-right font-semibold">العميل</th>
                <th class="px-4 py-3 text-right font-semibold">المبلغ</th>
                <th class="px-4 py-3 text-right font-semibold">الطريقة</th>
                <th class="px-4 py-3 text-right font-semibold">الحالة</th>
                <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in myRows" :key="r.id">
                <td class="px-4 py-3 font-mono text-xs">{{ r.id }}</td>
                <td class="px-4 py-3">{{ r.customer?.name ?? '—' }}</td>
                <td class="px-4 py-3 font-bold tabular-nums">{{ fmt(r.amount) }}</td>
                <td class="px-4 py-3 text-sm">{{ payLabel(r.payment_method) }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ fmtDate(r.created_at) }}</td>
                <td class="px-4 py-3 text-left">
                  <button type="button" class="text-xs font-semibold text-primary-600 hover:underline" @click="openDetail(r.id)">
                    تفاصيل
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- مراجعة -->
      <div v-show="tab === 'review'" class="space-y-4">
        <div class="table-toolbar flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-[11px] font-semibold text-gray-500 mb-1">الحالة</label>
            <select v-model="revFilters.status" class="field py-2 text-sm min-w-[140px]" @change="loadReview(1)">
              <option value="">الكل</option>
              <option value="pending">قيد المراجعة</option>
              <option value="returned_for_revision">مُرجع للتعديل</option>
              <option value="approved">معتمد</option>
              <option value="rejected">مرفوض</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-gray-500 mb-1">طريقة الدفع</label>
            <select v-model="revFilters.payment_method" class="field py-2 text-sm min-w-[140px]" @change="loadReview(1)">
              <option value="">الكل</option>
              <option value="bank_transfer">تحويل بنكي</option>
              <option value="cash">نقد</option>
              <option value="other">أخرى</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-gray-500 mb-1">من</label>
            <input v-model="revFilters.from" type="date" class="field py-2 text-sm" @change="loadReview(1)">
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-gray-500 mb-1">إلى</label>
            <input v-model="revFilters.to" type="date" class="field py-2 text-sm" @change="loadReview(1)">
          </div>
        </div>
        <div v-if="revLoading" class="flex justify-center py-16">
          <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin" />
        </div>
        <div v-else-if="!revRows.length" class="text-center py-16 text-gray-500 text-sm">لا طلبات مطابقة.</div>
        <div v-else class="table-shell overflow-x-auto">
          <table class="data-table min-w-[900px]">
            <thead>
              <tr>
                <th class="px-4 py-3 text-right font-semibold">#</th>
                <th class="px-4 py-3 text-right font-semibold">العميل</th>
                <th class="px-4 py-3 text-right font-semibold">مقدّم الطلب</th>
                <th class="px-4 py-3 text-right font-semibold">المبلغ</th>
                <th class="px-4 py-3 text-right font-semibold">الطريقة</th>
                <th class="px-4 py-3 text-right font-semibold">إيصال</th>
                <th class="px-4 py-3 text-right font-semibold">الحالة</th>
                <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in revRows" :key="r.id">
                <td class="px-4 py-3 font-mono text-xs">{{ r.id }}</td>
                <td class="px-4 py-3">{{ r.customer?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm">{{ r.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 font-bold tabular-nums">{{ fmt(r.amount) }}</td>
                <td class="px-4 py-3 text-sm">{{ payLabel(r.payment_method) }}</td>
                <td class="px-4 py-3">
                  <span v-if="r.has_receipt" class="text-green-600 text-xs font-semibold">مرفوع</span>
                  <span v-else class="text-gray-400 text-xs">—</span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ fmtDate(r.created_at) }}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-1 justify-end">
                    <button
                      v-if="r.has_receipt"
                      type="button"
                      class="text-[11px] font-semibold text-primary-600 hover:underline"
                      @click="downloadReceipt(r.id)"
                    >
                      إيصال
                    </button>
                    <template v-if="r.status === 'pending'">
                      <button type="button" class="text-[11px] font-semibold text-green-600 hover:underline disabled:opacity-50" :disabled="actionBusyId === r.id" @click="confirmApprove(r)">
                        اعتماد
                      </button>
                      <button type="button" class="text-[11px] font-semibold text-amber-600 hover:underline" @click="openReturn(r)">
                        إرجاع
                      </button>
                      <button type="button" class="text-[11px] font-semibold text-red-600 hover:underline" @click="openReject(r)">
                        رفض
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreate" class="modal-overlay" dir="rtl" @click.self="showCreate = false">
        <div class="modal-box max-w-lg shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-bold text-gray-900 dark:text-white">طلب شحن جديد</h3>
            <button type="button" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" @click="showCreate = false">
              <XMarkIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>
          <div class="form-shell px-6 py-4 space-y-3">
            <div>
              <label class="block text-xs font-semibold mb-1">العميل <span class="text-red-500">*</span></label>
              <p class="text-[11px] text-gray-500 dark:text-slate-400 mb-1.5 leading-relaxed">
                صاحب المحفظة التي تُزاد بعد الاعتماد: نفس العميل الذي دفع الشحن (محفظة فردية أو محفظة أسطول حسب «الهدف» أدناه).
              </p>
              <select v-model="createForm.customer_id" class="field" :disabled="customersLoading">
                <option value="">اختر عميلاً</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-grid-2">
              <div>
                <label class="block text-xs font-semibold mb-1">الهدف</label>
                <select v-model="createForm.target" class="field">
                  <option value="individual">محفظة فردية</option>
                  <option value="fleet">محفظة أسطول</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold mb-1">المبلغ <span class="text-red-500">*</span></label>
                <input v-model.number="createForm.amount" type="number" min="0.01" step="0.01" class="field" placeholder="0.00">
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">طريقة الدفع <span class="text-red-500">*</span></label>
              <select v-model="createForm.payment_method" class="field">
                <option value="cash">نقد</option>
                <option value="bank_transfer">تحويل بنكي</option>
                <option value="other">أخرى</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">رقم المرجع / الحوالة</label>
              <input v-model="createForm.reference_number" type="text" class="field" maxlength="120">
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">ملاحظات</label>
              <textarea v-model="createForm.notes_from_customer" class="field min-h-[72px]" rows="2" />
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">
                إيصال التحويل
                <span v-if="createForm.payment_method === 'bank_transfer'" class="text-red-500">*</span>
              </label>
              <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="text-sm w-full" @change="onCreateReceipt">
            </div>
            <p v-if="formError" class="text-sm text-red-600 bg-red-50 dark:bg-red-900/20 rounded-xl p-3">{{ formError }}</p>
          </div>
          <div class="form-actions px-6 pb-5 flex gap-2">
            <button type="button" class="btn btn-outline flex-1" @click="showCreate = false">إلغاء</button>
            <button type="button" class="btn btn-primary flex-1 disabled:opacity-50" :disabled="createSubmitting" @click="submitCreate">
              {{ createSubmitting ? 'جارٍ الإرسال…' : 'إرسال الطلب' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Detail drawer -->
    <Teleport to="body">
      <div v-if="detail" class="modal-overlay z-[60]" dir="rtl" @click.self="detail = null">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto m-4 mr-auto ml-4 border border-gray-100 dark:border-slate-700">
          <div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700 px-5 py-3 flex justify-between items-center">
            <h3 class="font-bold">تفاصيل الطلب #{{ detail.id }}</h3>
            <button type="button" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" @click="detail = null">
              <XMarkIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>
          <div class="p-5 space-y-3 text-sm">
            <p><span class="text-gray-500">الحالة:</span> <span class="font-semibold" :class="statusTextClass(detail.status)">{{ statusLabel(detail.status) }}</span></p>
            <p><span class="text-gray-500">المبلغ:</span> {{ fmt(detail.amount) }}</p>
            <p><span class="text-gray-500">الطريقة:</span> {{ payLabel(detail.payment_method) }}</p>
            <p v-if="detail.reference_number"><span class="text-gray-500">المرجع:</span> {{ detail.reference_number }}</p>
            <p v-if="detail.notes_from_customer"><span class="text-gray-500">ملاحظاتك:</span> {{ detail.notes_from_customer }}</p>
            <p v-if="detail.review_notes"><span class="text-gray-500">ملاحظات المراجعة:</span> {{ detail.review_notes }}</p>
            <p v-if="detail.approved_wallet_transaction">
              <span class="text-gray-500">حركة الاعتماد:</span> #{{ detail.approved_wallet_transaction.id }}
              ({{ fmt(detail.approved_wallet_transaction.amount) }})
            </p>
            <div class="flex flex-wrap gap-2 pt-2">
              <button v-if="detail.has_receipt" type="button" class="btn btn-outline text-xs py-1.5" @click="downloadReceipt(detail.id)">
                تنزيل الإيصال
              </button>
              <template v-if="detail.status === 'returned_for_revision' && canCreate && isMine(detail)">
                <button type="button" class="btn btn-primary text-xs py-1.5" @click="openEditReturned">تعديل وإعادة إرسال</button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit returned -->
    <Teleport to="body">
      <div v-if="showEditReturned && editRow" class="modal-overlay z-[70]" dir="rtl" @click.self="showEditReturned = false">
        <div class="modal-box max-w-lg shadow-2xl">
          <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 font-bold">تعديل الطلب المُرجع</div>
          <div class="form-shell px-6 py-4 space-y-3">
            <div class="form-grid-2">
              <div>
                <label class="block text-xs font-semibold mb-1">المبلغ</label>
                <input v-model.number="editForm.amount" type="number" min="0.01" step="0.01" class="field">
              </div>
              <div>
                <label class="block text-xs font-semibold mb-1">طريقة الدفع</label>
                <select v-model="editForm.payment_method" class="field">
                  <option value="cash">نقد</option>
                  <option value="bank_transfer">تحويل بنكي</option>
                  <option value="other">أخرى</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">المرجع</label>
              <input v-model="editForm.reference_number" type="text" class="field">
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">ملاحظات</label>
              <textarea v-model="editForm.notes_from_customer" class="field min-h-[64px]" rows="2" />
            </div>
            <div>
              <label class="block text-xs font-semibold mb-1">إيصال جديد (اختياري)</label>
              <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="text-sm" @change="onEditReceipt">
            </div>
            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </div>
          <div class="form-actions px-6 pb-5 flex flex-col gap-2">
            <button type="button" class="btn btn-outline w-full" :disabled="editSubmitting" @click="saveEditReturned(false)">حفظ التعديلات</button>
            <button type="button" class="btn btn-primary w-full" :disabled="editSubmitting" @click="saveEditReturned(true)">حفظ وإعادة الإرسال</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Reject -->
    <Teleport to="body">
      <div v-if="rejectRow" class="modal-overlay z-[70]" dir="rtl" @click.self="rejectRow = null">
        <div class="modal-box max-w-md shadow-2xl">
          <div class="px-6 py-4 font-bold border-b border-gray-100 dark:border-slate-700">رفض الطلب #{{ rejectRow.id }}</div>
          <div class="px-6 py-4">
            <label class="block text-xs font-semibold mb-1">سبب الرفض <span class="text-red-500">*</span></label>
            <textarea v-model="rejectNotes" class="field min-h-[88px]" rows="3" placeholder="مطلوب" />
          </div>
          <div class="form-actions px-6 pb-5 flex gap-2">
            <button type="button" class="btn btn-outline flex-1" @click="rejectRow = null">إلغاء</button>
            <button type="button" class="btn btn-danger flex-1 disabled:opacity-50" :disabled="actionBusyId === rejectRow.id || !rejectNotes.trim()" @click="doReject">
              رفض
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Return -->
    <Teleport to="body">
      <div v-if="returnRow" class="modal-overlay z-[70]" dir="rtl" @click.self="returnRow = null">
        <div class="modal-box max-w-md shadow-2xl">
          <div class="px-6 py-4 font-bold border-b border-gray-100 dark:border-slate-700">إرجاع للتعديل #{{ returnRow.id }}</div>
          <div class="px-6 py-4">
            <label class="block text-xs font-semibold mb-1">ملاحظات للعميل <span class="text-red-500">*</span></label>
            <textarea v-model="returnNotes" class="field min-h-[88px]" rows="3" />
          </div>
          <div class="form-actions px-6 pb-5 flex gap-2">
            <button type="button" class="btn btn-outline flex-1" @click="returnRow = null">إلغاء</button>
            <button type="button" class="btn btn-primary flex-1 disabled:opacity-50" :disabled="actionBusyId === returnRow.id || !returnNotes.trim()" @click="doReturn">
              إرجاع
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Approve confirm -->
    <Teleport to="body">
      <div v-if="approveCandidate" class="modal-overlay z-[70]" dir="rtl" @click.self="approveCandidate = null">
        <div class="modal-box max-w-sm shadow-2xl text-center">
          <p class="px-6 pt-6 font-semibold text-gray-900 dark:text-white">تأكيد اعتماد الشحن</p>
          <p class="px-6 py-2 text-sm text-gray-600 dark:text-slate-300">
            سيتم إضافة {{ fmt(approveCandidate.amount) }} إلى المحفظة الرئيسية للعميل بعد الاعتماد. لن يُكرَّر الخصم عند النقر المتكرر.
          </p>
          <div class="px-6 pb-6 flex gap-2">
            <button type="button" class="btn btn-outline flex-1" @click="approveCandidate = null">إلغاء</button>
            <button type="button" class="btn btn-success flex-1 disabled:opacity-50" :disabled="actionBusyId === approveCandidate.id" @click="doApprove">
              اعتماد
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  QueueListIcon, PlusCircleIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'

const auth = useAuthStore()
const toast = useToast()

const canCreate = computed(() => auth.hasPermission('wallet.top_up_requests.create'))
const canReview = computed(() => auth.hasPermission('wallet.top_up_requests.review'))
const showMyTab = computed(() => auth.hasPermission('wallet.top_up_requests.view') || canCreate.value)
const canAccessPage = computed(() => showMyTab.value || canReview.value)

const tab = ref<'my' | 'review'>('my')
const myLoading = ref(false)
const revLoading = ref(false)
const myRows = ref<any[]>([])
const revRows = ref<any[]>([])
const customers = ref<any[]>([])
const customersLoading = ref(false)

const showCreate = ref(false)
const createSubmitting = ref(false)
const createReceipt = ref<File | null>(null)
const formError = ref('')
const createForm = reactive({
  customer_id: '' as string | number,
  target: 'individual' as 'individual' | 'fleet',
  amount: null as number | null,
  payment_method: 'cash' as 'bank_transfer' | 'cash' | 'other',
  reference_number: '',
  notes_from_customer: '',
})

const detail = ref<any | null>(null)
const showEditReturned = ref(false)
const editRow = ref<any | null>(null)
const editSubmitting = ref(false)
const editReceipt = ref<File | null>(null)
const editForm = reactive({
  amount: null as number | null,
  payment_method: 'cash' as string,
  reference_number: '',
  notes_from_customer: '',
})

const rejectRow = ref<any | null>(null)
const rejectNotes = ref('')
const returnRow = ref<any | null>(null)
const returnNotes = ref('')
const approveCandidate = ref<any | null>(null)
const actionBusyId = ref<number | null>(null)

const revFilters = reactive({ status: '', payment_method: '', from: '', to: '' })

const fmt = (n: any) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(parseFloat(n) || 0)
const fmtDate = (d: string) => (d ? new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' }) : '—')

function payLabel(m: string) {
  return { bank_transfer: 'تحويل بنكي', cash: 'نقد', other: 'أخرى' }[m] ?? m
}
function statusLabel(s: string) {
  return {
    pending: 'قيد المراجعة',
    approved: 'معتمد',
    rejected: 'مرفوض',
    returned_for_revision: 'مُرجع للتعديل',
  }[s] ?? s
}
function statusClass(s: string) {
  return {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    returned_for_revision: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
  }[s] ?? 'bg-gray-100 text-gray-700'
}
function statusTextClass(s: string) {
  return {
    pending: 'text-amber-700',
    approved: 'text-green-600',
    rejected: 'text-red-600',
    returned_for_revision: 'text-sky-700',
  }[s] ?? ''
}

function isMine(row: any) {
  return Number(row.requested_by) === Number(auth.user?.id)
}

watch([canAccessPage, showMyTab, canReview], () => {
  if (!canAccessPage.value) return
  if (!showMyTab.value && canReview.value) tab.value = 'review'
  else if (showMyTab.value && !canReview.value) tab.value = 'my'
}, { immediate: true })

async function loadMy() {
  if (!showMyTab.value) return
  myLoading.value = true
  try {
    const { data } = await apiClient.get('/wallet-top-up-requests/my', { params: { per_page: 50 } })
    myRows.value = data?.data ?? []
  } catch {
    myRows.value = []
    toast.error('تعذر تحميل طلباتك')
  } finally {
    myLoading.value = false
  }
}

async function loadReview(page = 1) {
  if (!canReview.value) return
  revLoading.value = true
  try {
    const { data } = await apiClient.get('/admin/wallet-top-up-requests', {
      params: {
        page,
        per_page: 50,
        status: revFilters.status || undefined,
        payment_method: revFilters.payment_method || undefined,
        from: revFilters.from || undefined,
        to: revFilters.to || undefined,
      },
    })
    revRows.value = data?.data ?? []
  } catch {
    revRows.value = []
    toast.error('تعذر تحميل قائمة المراجعة')
  } finally {
    revLoading.value = false
  }
}

async function loadCustomers() {
  customersLoading.value = true
  try {
    const { data } = await apiClient.get('/customers', { params: { per_page: 500 } })
    customers.value = data?.data ?? []
  } catch {
    customers.value = []
  } finally {
    customersLoading.value = false
  }
}

async function openCreate() {
  formError.value = ''
  createForm.customer_id = ''
  createForm.target = 'individual'
  createForm.amount = null
  createForm.payment_method = 'cash'
  createForm.reference_number = ''
  createForm.notes_from_customer = ''
  createReceipt.value = null
  showCreate.value = true
  await loadCustomers()
}

function onCreateReceipt(e: Event) {
  const t = e.target as HTMLInputElement
  createReceipt.value = t.files?.[0] ?? null
}

function onEditReceipt(e: Event) {
  const t = e.target as HTMLInputElement
  editReceipt.value = t.files?.[0] ?? null
}

async function submitCreate() {
  if (createSubmitting.value) return
  formError.value = ''
  if (!createForm.customer_id || !createForm.amount || createForm.amount <= 0) {
    formError.value = 'اختر عميلاً وأدخل مبلغاً صحيحاً'
    return
  }
  if (createForm.payment_method === 'bank_transfer' && !createReceipt.value) {
    formError.value = 'إيصال التحويل مطلوب للتحويل البنكي'
    return
  }
  createSubmitting.value = true
  try {
    const fd = new FormData()
    fd.append('customer_id', String(createForm.customer_id))
    fd.append('target', createForm.target)
    fd.append('amount', String(createForm.amount))
    fd.append('payment_method', createForm.payment_method)
    if (createForm.reference_number) fd.append('reference_number', createForm.reference_number)
    if (createForm.notes_from_customer) fd.append('notes_from_customer', createForm.notes_from_customer)
    if (createReceipt.value) fd.append('receipt', createReceipt.value)
    await apiClient.post('/wallet-top-up-requests', fd, { skipGlobalErrorToast: true })
    toast.success('تم إرسال طلب الشحن')
    showCreate.value = false
    await loadMy()
  } catch (e: unknown) {
    formError.value = summarizeAxiosError(e)
  } finally {
    createSubmitting.value = false
  }
}

async function openDetail(id: number) {
  try {
    const { data } = await apiClient.get(`/wallet-top-up-requests/${id}`)
    detail.value = data?.data ?? null
  } catch {
    toast.error('تعذر تحميل التفاصيل')
  }
}

function openEditReturned() {
  if (!detail.value) return
  editRow.value = detail.value
  editForm.amount = parseFloat(detail.value.amount)
  editForm.payment_method = detail.value.payment_method
  editForm.reference_number = detail.value.reference_number ?? ''
  editForm.notes_from_customer = detail.value.notes_from_customer ?? ''
  editReceipt.value = null
  formError.value = ''
  showEditReturned.value = true
}

async function saveEditReturned(andResubmit: boolean) {
  if (!editRow.value || editSubmitting.value) return
  formError.value = ''
  if (editForm.payment_method === 'bank_transfer' && !editRow.value.has_receipt && !editReceipt.value) {
    formError.value = 'أرفق إيصالاً للتحويل البنكي'
    return
  }
  editSubmitting.value = true
  try {
    const fd = new FormData()
    if (editForm.amount != null) fd.append('amount', String(editForm.amount))
    fd.append('payment_method', editForm.payment_method)
    fd.append('reference_number', editForm.reference_number || '')
    fd.append('notes_from_customer', editForm.notes_from_customer || '')
    if (editReceipt.value) fd.append('receipt', editReceipt.value)
    await apiClient.patch(`/wallet-top-up-requests/${editRow.value.id}`, fd, { skipGlobalErrorToast: true })
    if (andResubmit) {
      await apiClient.post(`/wallet-top-up-requests/${editRow.value.id}/resubmit`, {}, { skipGlobalErrorToast: true })
      toast.success('تم حفظ التعديلات وإعادة الطلب للمراجعة')
    } else {
      toast.success('تم حفظ التعديلات')
    }
    showEditReturned.value = false
    detail.value = null
    await loadMy()
    if (canReview.value) await loadReview(1)
  } catch (e: unknown) {
    formError.value = summarizeAxiosError(e)
  } finally {
    editSubmitting.value = false
  }
}

/** Laravel expects PATCH; axios + FormData: use real PATCH URL */
async function downloadReceipt(id: number) {
  try {
    const r = await apiClient.get(`/wallet-top-up-requests/${id}/receipt`, {
      responseType: 'blob',
      skipGlobalErrorToast: true,
    })
    const blob = r.data as Blob
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `receipt-${id}`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.error('تعذر تنزيل الإيصال')
  }
}

function confirmApprove(row: any) {
  approveCandidate.value = row
}

async function doApprove() {
  const row = approveCandidate.value
  if (!row) return
  actionBusyId.value = row.id
  try {
    await apiClient.post(`/admin/wallet-top-up-requests/${row.id}/approve`, {}, { skipGlobalErrorToast: true })
    toast.success('تم اعتماد طلب الشحن')
    approveCandidate.value = null
    await loadReview(1)
    await loadMy()
  } catch (e: unknown) {
    toast.error(summarizeAxiosError(e))
  } finally {
    actionBusyId.value = null
  }
}

function openReject(row: any) {
  rejectRow.value = row
  rejectNotes.value = ''
}

async function doReject() {
  const row = rejectRow.value
  if (!row || !rejectNotes.value.trim()) return
  actionBusyId.value = row.id
  try {
    await apiClient.post(`/admin/wallet-top-up-requests/${row.id}/reject`, { review_notes: rejectNotes.value.trim() }, { skipGlobalErrorToast: true })
    toast.success('تم رفض الطلب')
    rejectRow.value = null
    await loadReview(1)
  } catch (e: unknown) {
    toast.error(summarizeAxiosError(e))
  } finally {
    actionBusyId.value = null
  }
}

function openReturn(row: any) {
  returnRow.value = row
  returnNotes.value = ''
}

async function doReturn() {
  const row = returnRow.value
  if (!row || !returnNotes.value.trim()) return
  actionBusyId.value = row.id
  try {
    await apiClient.post(`/admin/wallet-top-up-requests/${row.id}/return`, { review_notes: returnNotes.value.trim() }, { skipGlobalErrorToast: true })
    toast.success('تم إرجاع الطلب للتعديل')
    returnRow.value = null
    await loadReview(1)
  } catch (e: unknown) {
    toast.error(summarizeAxiosError(e))
  } finally {
    actionBusyId.value = null
  }
}

onMounted(async () => {
  if (!canAccessPage.value) return
  await Promise.all([loadMy(), canReview.value ? loadReview(1) : Promise.resolve()])
})
</script>
