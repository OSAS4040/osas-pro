<template>
  <div class="h-[calc(100vh-64px)] flex flex-col lg:flex-row gap-3 overflow-hidden p-2 lg:p-0">
    <!-- يسار: المنتجات -->
    <div class="lg:w-2/3 flex flex-col gap-3 overflow-hidden min-h-0">
      <div class="flex gap-2">
        <input v-model="search" type="text" placeholder="ابحث عن منتجات أو خدمات..."
          class="flex-1 border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none"
          @input="debouncedSearch" />
        <select v-model="tab" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white rounded-lg px-3 py-2 text-sm" @change="fetchItems">
          <option value="products">منتجات</option>
          <option value="services">خدمات</option>
        </select>
      </div>

      <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-2 content-start pb-4">
        <div v-if="loading" v-for="n in 8" :key="n" class="bg-gray-100 dark:bg-slate-700 animate-pulse rounded-xl h-24"></div>
        <div v-for="item in results" :key="`${tab}-${item.id}`"
          class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-3 cursor-pointer hover:border-primary-400 hover:shadow-sm active:scale-95 transition-all select-none"
          @click="addToCart(item)"
        >
          <p class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ item.name }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ item.sku ?? item.code ?? '—' }}</p>
          <p class="text-primary-600 dark:text-primary-400 font-semibold mt-2 text-sm">{{ price(item) }} ر.س</p>
        </div>
        <div v-if="!loading && !results.length" class="col-span-full py-10 text-center text-gray-400 text-sm">
          لا توجد {{ tab === 'products' ? 'منتجات' : 'خدمات' }}.
        </div>
      </div>
    </div>

    <!-- يمين: السلة والدفع -->
    <div class="lg:w-1/3 flex flex-col gap-2 min-h-0">
      <!-- اختيار العميل -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-3 relative">
        <label class="block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">العميل (اختياري)</label>

        <div class="relative">
          <input v-model="customerSearch" type="text" placeholder="ابحث عن عميل..."
            class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary-500"
            @input="searchCustomers" @focus="loadAllCustomers" @blur="onBlur" />
          <button v-if="customerSearch" @mousedown.prevent @click="clearCustomer"
            class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-400 text-sm">✕</button>
        </div>

        <!-- Dropdown -->
        <div v-if="showDropdown"
          class="absolute z-20 right-0 left-0 mx-3 mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-lg max-h-48 overflow-y-auto">
          <!-- إضافة عميل جديد -->
          <button class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 font-medium border-b border-gray-100 dark:border-slate-700"
            @mousedown.prevent @click="openQuickAdd">
            <span class="w-5 h-5 flex items-center justify-center bg-primary-100 dark:bg-primary-900/40 rounded-full text-xs">+</span>
            إضافة عميل جديد
          </button>
          <div v-for="c in customerResults" :key="c.id"
            class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 flex justify-between items-center"
            @mousedown.prevent @click="selectCustomer(c)">
            <span class="font-medium">{{ c.name }}</span>
            <span class="text-gray-400 text-xs">{{ c.phone }}</span>
          </div>
          <div v-if="!customerResults.length && customerSearch.length >= 2"
            class="px-3 py-2 text-xs text-gray-400 text-center">لا توجد نتائج</div>
        </div>

        <div v-if="selectedCustomer" class="mt-1.5 flex justify-between items-center bg-primary-50 dark:bg-primary-900/20 rounded-lg px-3 py-1.5">
          <div>
            <span class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ selectedCustomer.name }}</span>
            <span class="text-xs text-primary-400 mr-2">{{ selectedCustomer.phone }}</span>
          </div>
          <button class="text-gray-400 hover:text-red-400 text-xs" @click="clearCustomer">✕</button>
        </div>
      </div>

      <!-- عناصر السلة -->
      <div class="flex-1 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col min-h-0">
        <div class="px-4 py-2.5 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center flex-shrink-0">
          <h3 class="font-medium text-gray-800 dark:text-white text-sm">السلة <span class="text-xs text-gray-400">({{ cart.length }})</span></h3>
          <button v-if="cart.length" class="text-xs text-red-400 hover:text-red-600" @click="cart = []">مسح الكل</button>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="!cart.length" class="py-8 text-center text-gray-400 text-sm">السلة فارغة</div>
          <div class="divide-y divide-gray-100 dark:divide-slate-700">
            <div v-for="(item, i) in cart" :key="i" class="px-3 py-2.5 flex gap-2 items-center">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.name }}</p>
                <p class="text-xs text-gray-400">{{ Number(item.unit_price).toFixed(2) }} ر.س</p>
              </div>
              <div class="flex items-center gap-1 flex-shrink-0">
                <button class="w-6 h-6 bg-gray-100 dark:bg-slate-700 rounded text-sm hover:bg-gray-200 flex items-center justify-center" @click="item.quantity = Math.max(0.001, item.quantity - 1)">−</button>
                <input v-model="item.quantity" type="number" min="0.001" step="0.001"
                  class="w-14 text-center border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded px-1 py-0.5 text-sm" />
                <button class="w-6 h-6 bg-gray-100 dark:bg-slate-700 rounded text-sm hover:bg-gray-200 flex items-center justify-center" @click="item.quantity++">+</button>
              </div>
              <span class="text-xs font-medium text-gray-700 dark:text-slate-300 w-16 text-left flex-shrink-0">
                {{ (item.quantity * item.unit_price).toFixed(2) }}
              </span>
              <button class="text-red-400 hover:text-red-600 text-xs flex-shrink-0" @click="cart.splice(i, 1)">✕</button>
            </div>
          </div>
        </div>
      </div>

      <!-- الإجماليات والدفع -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-3 space-y-2 text-sm flex-shrink-0">
        <div class="flex justify-between text-gray-500 dark:text-slate-400">
          <span>المجموع قبل الضريبة</span><span>{{ subtotal.toFixed(2) }} ر.س</span>
        </div>
        <div class="flex justify-between text-gray-500 dark:text-slate-400">
          <span>الضريبة (15%)</span><span>{{ taxAmount.toFixed(2) }} ر.س</span>
        </div>
        <div v-if="discount > 0" class="flex justify-between text-green-600">
          <span>الخصم</span><span>-{{ discount.toFixed(2) }} ر.س</span>
        </div>
        <div class="flex justify-between font-bold text-gray-900 dark:text-white border-t dark:border-slate-700 pt-2 text-base">
          <span>الإجمالي</span><span>{{ total.toFixed(2) }} ر.س</span>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1">
          <div>
            <label class="text-xs text-gray-500 dark:text-slate-400">الخصم</label>
            <input v-model="discount" type="number" min="0" step="0.01"
              class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-xs text-gray-500 dark:text-slate-400">طريقة الدفع</label>
            <select v-model="paymentMethod" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded px-2 py-1.5 text-sm">
              <option value="cash">نقدي</option>
              <option value="card">بطاقة</option>
              <option value="wallet">محفظة</option>
              <option value="bank_transfer">تحويل بنكي</option>
            </select>
          </div>
        </div>

        <p v-if="error" class="text-red-600 text-xs bg-red-50 dark:bg-red-900/20 rounded p-2">{{ error }}</p>

        <button :disabled="!cart.length || processing"
          class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold text-sm hover:bg-primary-700 disabled:opacity-50 transition-colors"
          @click="checkout">
          {{ processing ? 'جارٍ المعالجة...' : `إتمام البيع — ${total.toFixed(2)} ر.س` }}
        </button>
      </div>
    </div>

    <!-- Quick Add Customer Modal -->
    <div v-if="showQuickAdd" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showQuickAdd = false">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">إضافة عميل جديد</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الاسم *</label>
            <input v-model="quickForm.name" type="text" placeholder="اسم العميل" autofocus
              class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">رقم الجوال *</label>
            <input v-model="quickForm.phone" type="tel" placeholder="05xxxxxxxx"
              class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">البريد الإلكتروني (اختياري)</label>
            <input v-model="quickForm.email" type="email" placeholder="email@example.com"
              class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
        </div>
        <p v-if="quickError" class="text-red-600 text-xs mt-2">{{ quickError }}</p>
        <div class="flex gap-3 mt-5">
          <button class="flex-1 border border-gray-300 dark:border-slate-600 rounded-xl py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 dark:text-white" @click="showQuickAdd = false">إلغاء</button>
          <button :disabled="quickSaving || !quickForm.name || !quickForm.phone"
            class="flex-1 bg-primary-600 text-white rounded-xl py-2.5 text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
            @click="saveQuickCustomer">
            {{ quickSaving ? 'جارٍ الحفظ...' : 'حفظ واختيار' }}
          </button>
        </div>
      </div>
    </div>

    <!-- نافذة تأكيد البيع -->
    <div v-if="lastInvoice" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-sm text-center space-y-4">
        <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto">
          <span class="text-3xl">✓</span>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white">تم البيع بنجاح!</h3>
        <p class="text-gray-600 dark:text-slate-400">فاتورة رقم <strong>{{ lastInvoice.invoice_number }}</strong></p>
        <p class="text-3xl font-bold text-primary-600">{{ Number(lastInvoice.total).toFixed(2) }} ر.س</p>
        <div class="flex gap-3 pt-2">
          <button class="flex-1 border border-gray-300 dark:border-slate-600 rounded-xl py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 dark:text-white" @click="viewInvoice">
            عرض الفاتورة
          </button>
          <button class="flex-1 bg-primary-600 text-white rounded-xl py-2.5 text-sm font-medium hover:bg-primary-700" @click="newSale">
            عملية جديدة
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { v4 as uuidv4 } from 'uuid'

const router = useRouter()

const tab     = ref<'products' | 'services'>('products')
const search  = ref('')
const results = ref<any[]>([])
const loading = ref(false)
const cart    = ref<Array<{
  name: string; item_type: string; product_id: number | null;
  service_id: number | null; unit_price: number; tax_rate: number;
  quantity: number; sku?: string; cost_price?: number;
}>>([])

const discount      = ref(0)
const paymentMethod = ref('cash')
const processing    = ref(false)
const error         = ref('')
const lastInvoice   = ref<any>(null)

const customerSearch   = ref('')
const customerResults  = ref<any[]>([])
const selectedCustomer = ref<any>(null)
const showDropdown     = ref(false)

const showQuickAdd = ref(false)
const quickForm    = ref({ name: '', phone: '', email: '' })
const quickSaving  = ref(false)
const quickError   = ref('')

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchItems, 300)
}

async function fetchItems() {
  loading.value = true
  try {
    const endpoint = tab.value === 'products' ? '/products' : '/services'
    const params: Record<string, any> = { is_active: 1, per_page: 60 }
    if (search.value) params.search = search.value
    const { data } = await apiClient.get(endpoint, { params })
    results.value = data.data.data ?? data.data
  } finally { loading.value = false }
}

let custDebounce: ReturnType<typeof setTimeout>
async function searchCustomers() {
  clearTimeout(custDebounce)
  selectedCustomer.value = null
  custDebounce = setTimeout(async () => {
    const params: Record<string, any> = { per_page: 20 }
    if (customerSearch.value.length >= 1) params.search = customerSearch.value
    const { data } = await apiClient.get('/customers', { params })
    customerResults.value = data.data.data ?? data.data
  }, 200)
}

async function loadAllCustomers() {
  const { data } = await apiClient.get('/customers', { params: { per_page: 20 } })
  customerResults.value = data.data.data ?? data.data
  showDropdown.value = true
}

function selectCustomer(c: any) {
  selectedCustomer.value = c
  customerSearch.value   = c.name
  customerResults.value  = []
  showDropdown.value     = false
}

function clearCustomer() {
  selectedCustomer.value = null
  customerSearch.value   = ''
  customerResults.value  = []
}

function onBlur() {
  setTimeout(() => { showDropdown.value = false }, 200)
}

function openQuickAdd() {
  quickForm.value  = { name: customerSearch.value, phone: '', email: '' }
  quickError.value = ''
  showQuickAdd.value = true
  showDropdown.value = false
}

async function saveQuickCustomer() {
  quickSaving.value = true
  quickError.value  = ''
  try {
    const { data } = await apiClient.post('/customers', {
      name:  quickForm.value.name,
      phone: quickForm.value.phone,
      email: quickForm.value.email || undefined,
    })
    selectCustomer(data.data)
    showQuickAdd.value = false
  } catch (e: any) {
    quickError.value = e.response?.data?.message ?? 'فشل إضافة العميل'
  } finally { quickSaving.value = false }
}

function price(item: any): string {
  return Number(item.sale_price ?? item.base_price ?? 0).toFixed(2)
}

function addToCart(item: any) {
  const isProduct = tab.value === 'products'
  const existing  = cart.value.find(c => isProduct ? c.product_id === item.id : c.service_id === item.id)
  if (existing) { existing.quantity++; return }
  cart.value.push({
    name:       item.name,
    item_type:  isProduct ? 'part' : 'service',
    product_id: isProduct ? item.id : null,
    service_id: isProduct ? null : item.id,
    unit_price: Number(item.sale_price ?? item.base_price ?? 0),
    cost_price: isProduct ? Number(item.cost_price ?? 0) : undefined,
    tax_rate:   Number(item.tax_rate ?? 15),
    quantity:   1,
    sku:        item.sku ?? item.code ?? undefined,
  })
}

const subtotal  = computed(() => cart.value.reduce((s, i) => s + i.quantity * i.unit_price, 0))
const taxAmount = computed(() => cart.value.reduce((s, i) => s + (i.quantity * i.unit_price) * (i.tax_rate / 100), 0))
const total     = computed(() => Math.max(0, subtotal.value + taxAmount.value - discount.value))

async function checkout() {
  if (!cart.value.length) return
  processing.value = true
  error.value = ''
  try {
    const { data } = await apiClient.post('/pos/sale', {
      customer_id:     selectedCustomer.value?.id ?? null,
      customer_type:   'b2c',
      discount_amount: discount.value,
      items:           cart.value,
      payment:         { method: paymentMethod.value, amount: total.value },
    }, { headers: { 'Idempotency-Key': uuidv4() } })
    lastInvoice.value = data.data
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'فشلت عملية البيع. حاول مجدداً.'
  } finally { processing.value = false }
}

function newSale() {
  lastInvoice.value = null; cart.value = []; discount.value = 0
  paymentMethod.value = 'cash'; clearCustomer()
}

function viewInvoice() { router.push(`/invoices/${lastInvoice.value.id}`); lastInvoice.value = null }

onMounted(fetchItems)
</script>
