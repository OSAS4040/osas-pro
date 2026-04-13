<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">الموردون</h2>
      <button class="px-4 py-2 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700" @click="openCreate">+ مورد جديد</button>
    </div>

    <div class="flex gap-3">
      <input v-model="search" type="text" placeholder="ابحث بالاسم..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64" @input="debouncedLoad" />
      <select v-model="filterActive" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-40" @change="load">
        <option value="">الكل</option>
        <option value="true">نشط</option>
        <option value="false">غير نشط</option>
      </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">الاسم</th>
            <th class="px-4 py-3 text-right">الكود</th>
            <th class="px-4 py-3 text-right">الهاتف</th>
            <th class="px-4 py-3 text-right">الرقم الضريبي</th>
            <th class="px-4 py-3 text-right">المدينة</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="s in suppliers" :key="s.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-right">{{ s.name }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500 text-right">{{ s.code ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ s.phone ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs text-right">{{ s.tax_number ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ s.city ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <span :class="s.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs">
                {{ s.is_active ? 'نشط' : 'غير نشط' }}
              </span>
            </td>
            <td class="px-4 py-3 text-left flex flex-wrap gap-x-3 gap-y-1 justify-end">
              <button class="text-primary-600 hover:underline text-xs" @click="openEdit(s)">تعديل</button>
              <RouterLink :to="`/purchases?supplier_id=${s.id}`" class="text-gray-500 hover:underline text-xs">الطلبات</RouterLink>
              <button
                v-if="biz.isEnabled('supplier_contract_mgmt')"
                type="button"
                class="text-gray-700 hover:underline text-xs"
                @click="openContracts(s)"
              >
                عقود PDF
              </button>
            </td>
          </tr>
          <tr v-if="!suppliers.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا يوجد موردون.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="closeForm">
      <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-lg">
        <h3 class="text-base font-semibold mb-4">{{ editTarget ? 'تعديل مورد' : 'مورد جديد' }}</h3>
        <form class="space-y-3" @submit.prevent="saveSupplier">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label>
              <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الكود</label>
              <input v-model="form.code" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
              <input v-model="form.phone" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
              <input v-model="form.email" type="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الرقم الضريبي</label>
              <input v-model="form.tax_number" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">السجل التجاري</label>
              <input v-model="form.cr_number" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">المدينة</label>
              <input v-model="form.city" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">شروط الدفع</label>
              <input v-model="form.payment_terms" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="مثال: صافي 30" />
            </div>
          </div>
          <div class="flex items-center gap-2">
            <input id="isActive" v-model="form.is_active" type="checkbox" />
            <label for="isActive" class="text-sm text-gray-700">نشط</label>
          </div>
          <div v-if="formError" class="text-red-500 text-sm">{{ formError }}</div>
          <div class="flex gap-2 justify-end pt-2">
            <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded-lg" @click="closeForm">إلغاء</button>
            <button type="submit" class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg" :disabled="saving">
              {{ saving ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="contractsSupplier" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="closeContracts">
      <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h3 class="text-base font-semibold mb-1">عقود المورد: {{ contractsSupplier.name }}</h3>
        <p class="text-xs text-gray-500 mb-4">متاح عند تفعيل «عقود الموردين» في نشاط المنشأة.</p>
        <div v-if="contractsError" class="text-red-600 text-sm mb-2">{{ contractsError }}</div>
        <ul v-if="!contractsLoading" class="space-y-2 mb-4 text-sm border rounded-lg divide-y max-h-48 overflow-y-auto">
          <li v-for="c in contracts" :key="c.id" class="px-3 py-2 flex justify-between gap-2">
            <div>
              <div class="font-medium">{{ c.title }}</div>
              <div class="text-xs text-gray-500">ينتهي: {{ c.expires_at ?? '—' }}</div>
            </div>
            <div class="flex gap-2 shrink-0">
              <button type="button" class="text-primary-600 text-xs hover:underline" @click="downloadContract(c)">تنزيل</button>
              <button type="button" class="text-red-600 text-xs hover:underline" @click="deleteContract(c)">حذف</button>
            </div>
          </li>
          <li v-if="!contracts.length" class="px-3 py-4 text-center text-gray-400">لا عقود بعد.</li>
        </ul>
        <p v-else class="text-sm text-gray-500 mb-4">جارٍ التحميل…</p>
        <form class="space-y-2 border-t pt-4" @submit.prevent="uploadContract">
          <label class="block text-xs font-medium text-gray-600">عنوان العقد</label>
          <input v-model="uploadTitle" type="text" class="w-full border rounded-lg px-3 py-2 text-sm" required />
          <label class="block text-xs font-medium text-gray-600">تاريخ الانتهاء (اختياري)</label>
          <input v-model="uploadExpires" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
          <label class="block text-xs font-medium text-gray-600">ملف PDF</label>
          <input type="file" accept="application/pdf,.pdf" class="text-sm" @change="onContractFile" />
          <div class="flex gap-2 justify-end pt-2">
            <button type="button" class="px-4 py-2 text-sm border rounded-lg" @click="closeContracts">إغلاق</button>
            <button type="submit" class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg" :disabled="uploadingContract">
              {{ uploadingContract ? '…' : 'رفع' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { appConfirm } from '@/services/appConfirmDialog'

const biz = useBusinessProfileStore()

const suppliers    = ref<any[]>([])
const search       = ref('')
const filterActive = ref('')
const showForm     = ref(false)
const editTarget   = ref<any>(null)
const saving       = ref(false)
const formError    = ref('')

const emptyForm = () => ({
  name: '', code: '', phone: '', email: '',
  tax_number: '', cr_number: '', city: '',
  payment_terms: '', is_active: true,
})

const form = ref(emptyForm())

const contractsSupplier   = ref<any>(null)
const contracts           = ref<any[]>([])
const contractsLoading    = ref(false)
const contractsError      = ref('')
const uploadTitle         = ref('')
const uploadExpires       = ref('')
const contractPdf         = ref<File | null>(null)
const uploadingContract   = ref(false)

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(load, 300)
}

async function load() {
  const params: Record<string, any> = {}
  if (search.value) params.search = search.value
  if (filterActive.value !== '') params.is_active = filterActive.value
  const { data } = await apiClient.get('/suppliers', { params })
  suppliers.value = data.data.data ?? data.data
}

function openCreate() { editTarget.value = null; form.value = emptyForm(); formError.value = ''; showForm.value = true }
function openEdit(s: any) { editTarget.value = s; form.value = { ...emptyForm(), ...s }; formError.value = ''; showForm.value = true }
function closeForm() { showForm.value = false }

async function saveSupplier() {
  saving.value = true; formError.value = ''
  try {
    if (editTarget.value) await apiClient.put(`/suppliers/${editTarget.value.id}`, form.value)
    else await apiClient.post('/suppliers', form.value)
    closeForm(); await load()
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? 'فشل الحفظ.'
  } finally {
    saving.value = false
  }
}

async function openContracts(s: any) {
  await biz.load().catch(() => {})
  if (!biz.isEnabled('supplier_contract_mgmt')) return
  contractsSupplier.value = s
  contractsError.value = ''
  uploadTitle.value = ''
  uploadExpires.value = ''
  contractPdf.value = null
  await fetchContracts()
}

function closeContracts() {
  contractsSupplier.value = null
}

async function fetchContracts() {
  if (!contractsSupplier.value) return
  contractsLoading.value = true
  contractsError.value = ''
  try {
    const { data } = await apiClient.get(`/suppliers/${contractsSupplier.value.id}/contracts`)
    contracts.value = data.data ?? []
  } catch (e: any) {
    contractsError.value = e?.response?.data?.message ?? 'تعذر تحميل العقود.'
    contracts.value = []
  } finally {
    contractsLoading.value = false
  }
}

function onContractFile(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  contractPdf.value = f ?? null
}

async function uploadContract() {
  if (!contractsSupplier.value || !contractPdf.value) return
  uploadingContract.value = true
  contractsError.value = ''
  try {
    const fd = new FormData()
    fd.append('title', uploadTitle.value.trim())
    if (uploadExpires.value) fd.append('expires_at', uploadExpires.value)
    fd.append('file', contractPdf.value)
    await apiClient.post(`/suppliers/${contractsSupplier.value.id}/contracts`, fd)
    uploadTitle.value = ''
    uploadExpires.value = ''
    contractPdf.value = null
    await fetchContracts()
  } catch (e: any) {
    contractsError.value = e?.response?.data?.message ?? 'فشل الرفع.'
  } finally {
    uploadingContract.value = false
  }
}

async function downloadContract(c: any) {
  if (!contractsSupplier.value) return
  try {
    const res = await apiClient.get(`/suppliers/${contractsSupplier.value.id}/contracts/${c.id}/download`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = c.original_filename || 'contract.pdf'
    a.click()
    window.URL.revokeObjectURL(url)
  } catch {
    contractsError.value = 'تعذر التنزيل.'
  }
}

async function deleteContract(c: any) {
  if (!contractsSupplier.value) return
  const ok = await appConfirm({
    title: 'حذف العقد',
    message: 'حذف هذا العقد؟',
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/suppliers/${contractsSupplier.value.id}/contracts/${c.id}`)
    await fetchContracts()
  } catch (e: any) {
    contractsError.value = e?.response?.data?.message ?? 'تعذر الحذف.'
  }
}

onMounted(() => {
  biz.load().catch(() => {})
  load()
})
</script>
