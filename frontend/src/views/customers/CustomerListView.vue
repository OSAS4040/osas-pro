<template>
  <div class="space-y-5" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
        <UsersIcon class="w-6 h-6 text-primary-600" />
        العملاء
      </h2>
      <div class="flex items-center gap-2">
        <!-- View Toggle -->
        <div class="flex gap-1 bg-gray-100 p-1 rounded-lg">
          <button @click="viewMode = 'grid'"
            class="p-1.5 rounded-md transition-colors"
            :class="viewMode === 'grid' ? 'bg-white shadow-sm text-primary-600' : 'text-gray-500'">
            <Squares2X2Icon class="w-4 h-4" />
          </button>
          <button @click="viewMode = 'table'"
            class="p-1.5 rounded-md transition-colors"
            :class="viewMode === 'table' ? 'bg-white shadow-sm text-primary-600' : 'text-gray-500'">
            <Bars3Icon class="w-4 h-4" />
          </button>
        </div>
        <button @click="showModal = true"
          class="flex items-center gap-1.5 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
          <PlusIcon class="w-4 h-4" /> عميل جديد
        </button>
      </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl border border-gray-100 p-4">
      <div class="flex items-center gap-3">
        <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
        <input v-model="search" placeholder="البحث عن عميل..." class="flex-1 text-sm focus:outline-none" />
        <select v-model="searchBy" class="text-sm text-gray-600 focus:outline-none border-r pr-3">
          <option value="name">الاسم</option>
          <option value="phone">رقم الجوال</option>
          <option value="email">البريد الإلكتروني</option>
        </select>
      </div>
    </div>

    <!-- GRID VIEW (مثل احجزني) -->
    <div v-if="viewMode === 'grid'">
      <div v-if="loading" class="text-center py-10 text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="!filtered.length" class="text-center py-10 text-gray-400">
        <UsersIcon class="w-10 h-10 mx-auto mb-2 text-gray-300" />
        <p class="text-sm">لا يوجد عملاء</p>
      </div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div v-for="c in filtered" :key="c.id"
          class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-all hover:border-primary-200">
          <!-- Avatar -->
          <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white text-xl font-bold mb-3">
              {{ c.name?.charAt(0)?.toUpperCase() }}
            </div>
            <h3 class="font-semibold text-gray-800 text-sm">{{ c.name }}</h3>
            <p class="text-xs text-gray-400 mt-0.5">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-medium"
                :class="c.type === 'b2b' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                {{ c.type === 'b2b' ? 'شركة' : 'فرد' }}
              </span>
            </p>
          </div>

          <!-- Contact row -->
          <div v-if="c.phone" class="mt-4 flex items-center justify-between border border-gray-100 rounded-xl px-3 py-2">
            <span class="text-sm text-gray-700 font-medium" dir="ltr">{{ c.phone }}</span>
            <div class="flex gap-1.5">
              <a :href="`https://wa.me/${cleanPhone(c.phone)}`" target="_blank"
                class="w-7 h-7 bg-green-50 border border-green-200 rounded-lg flex items-center justify-center hover:bg-green-100 transition-colors"
                title="WhatsApp">
                <svg class="w-3.5 h-3.5 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
              </a>
              <a :href="`tel:${c.phone}`"
                class="w-7 h-7 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-center hover:bg-blue-100 transition-colors"
                title="اتصال">
                <PhoneIcon class="w-3.5 h-3.5 text-blue-600" />
              </a>
            </div>
          </div>

          <!-- Ref number -->
          <p v-if="c.tax_number" class="text-[10px] text-gray-400 text-center mt-2">
            رقم المرجع: <span class="font-medium text-gray-600">{{ c.tax_number }}</span>
          </p>

          <!-- Actions -->
          <div class="mt-3 flex gap-2">
            <button @click="viewBookings(c)"
              class="flex-1 py-1.5 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700 transition-colors">
              عرض المواعيد
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLE VIEW -->
    <div v-else class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 text-right">
            <tr>
              <th class="px-4 py-3 font-medium">الاسم</th>
              <th class="px-4 py-3 font-medium">النوع</th>
              <th class="px-4 py-3 font-medium">رقم الجوال</th>
              <th class="px-4 py-3 font-medium">البريد الإلكتروني</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
              <th class="px-4 py-3 font-medium">تواصل</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="c in filtered" :key="c.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-medium text-gray-800">{{ c.name }}</td>
              <td class="px-4 py-3">
                <span :class="c.type === 'b2b' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                  class="px-2 py-0.5 rounded-full text-xs font-medium">{{ c.type === 'b2b' ? 'شركة' : 'فرد' }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600" dir="ltr">{{ c.phone ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-600">{{ c.email ?? '—' }}</td>
              <td class="px-4 py-3">
                <span :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                  class="px-2 py-0.5 rounded-full text-xs font-medium">{{ c.is_active ? 'نشط' : 'غير نشط' }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex gap-1.5" v-if="c.phone">
                  <a :href="`https://wa.me/${cleanPhone(c.phone)}`" target="_blank"
                    class="w-7 h-7 bg-green-50 border border-green-200 rounded-lg flex items-center justify-center hover:bg-green-100 transition-colors">
                    <svg class="w-3.5 h-3.5 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                  </a>
                  <a :href="`tel:${c.phone}`"
                    class="w-7 h-7 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-center hover:bg-blue-100 transition-colors">
                    <PhoneIcon class="w-3.5 h-3.5 text-blue-600" />
                  </a>
                </div>
              </td>
            </tr>
            <tr v-if="!filtered.length">
              <td colspan="6" class="text-center py-10 text-gray-400">لا يوجد عملاء</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Customer Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg">عميل جديد</h3>
          <button @click="showModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>
        <form @submit.prevent="saveCustomer" class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label>
            <input v-model="cForm.name" required class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال</label>
              <input v-model="cForm.phone" type="tel" class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400" dir="ltr" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">النوع</label>
              <select v-model="cForm.type" class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                <option value="b2c">فرد</option>
                <option value="b2b">شركة</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input v-model="cForm.email" type="email" class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400" dir="ltr" />
          </div>
          <p v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-xl p-3">{{ modalError }}</p>
          <div class="flex gap-3 justify-end pt-1">
            <button type="button" @click="showModal = false" class="px-4 py-2 border rounded-xl text-sm text-gray-700 hover:bg-gray-50">إلغاء</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium disabled:opacity-50 hover:bg-primary-700">
              {{ saving ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, XMarkIcon, UsersIcon, MagnifyingGlassIcon, Squares2X2Icon, Bars3Icon, PhoneIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const router     = useRouter()
const customers  = ref<any[]>([])
const loading    = ref(true)
const search     = ref('')
const searchBy   = ref('name')
const viewMode   = ref<'grid'|'table'>('grid')
const showModal  = ref(false)
const saving     = ref(false)
const modalError = ref('')
const cForm      = ref({ name: '', phone: '', email: '', type: 'b2c' })

const filtered = computed(() => {
  if (!search.value) return customers.value
  const q = search.value.toLowerCase()
  return customers.value.filter(c => {
    if (searchBy.value === 'name')  return c.name?.toLowerCase().includes(q)
    if (searchBy.value === 'phone') return c.phone?.includes(q)
    if (searchBy.value === 'email') return c.email?.toLowerCase().includes(q)
    return true
  })
})

function cleanPhone(phone: string) {
  return phone.replace(/\D/g, '').replace(/^0/, '966')
}

function viewBookings(c: any) {
  router.push(`/bookings?customer_id=${c.id}`)
}

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/customers', { params: { per_page: 200 } })
    customers.value = data.data?.data ?? data.data ?? []
  } catch { /* silent */ } finally { loading.value = false }
}

async function saveCustomer() {
  saving.value = true; modalError.value = ''
  try {
    await apiClient.post('/customers', cForm.value)
    await load()
    showModal.value = false
    cForm.value = { name: '', phone: '', email: '', type: 'b2c' }
  } catch (e: any) {
    modalError.value = e.response?.data?.message ?? 'فشل الحفظ'
  } finally { saving.value = false }
}

onMounted(load)
</script>
