<template>
  <Teleport to="body">
    <!-- Floating trigger button -->
    <button
      type="button"
      data-print-chrome
      class="print:hidden fixed bottom-6 left-6 z-[9990] w-12 h-12 rounded-full shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-110 focus:outline-none"
      :class="open ? 'bg-gray-700 dark:bg-gray-600' : 'bg-primary-600 hover:bg-primary-700'"
      title="المساعد الذكي (Ctrl+K)"
      @click="togglePanel"
    >
      <span v-if="!open" class="text-xl leading-none">💡</span>
      <XMarkIcon v-else class="w-5 h-5 text-white" />
    </button>

    <!-- Side Panel -->
    <Transition name="panel-slide">
      <div
        v-if="open"
        data-print-chrome
        class="print:hidden fixed top-0 right-0 z-[9989] h-full w-[400px] max-w-full shadow-2xl flex flex-col"
        :class="['bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700']"
        dir="rtl"
      >
        <!-- Panel Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
          <div class="flex items-center gap-2">
            <span class="text-xl">💡</span>
            <h2 class="font-bold text-gray-900 dark:text-white text-sm">المساعد الذكي</h2>
          </div>
          <div class="flex items-center gap-2">
            <kbd class="hidden sm:inline text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-600">Ctrl+K</kbd>
            <button class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" @click="open = false">
              <XMarkIcon class="w-4 h-4 text-gray-500 dark:text-gray-400" />
            </button>
          </div>
        </div>

        <!-- Search Bar -->
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
          <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2 border border-gray-200 dark:border-gray-600 focus-within:border-primary-400 dark:focus-within:border-primary-500 transition-colors">
            <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
            <input
              ref="searchRef"
              v-model="searchQuery"
              placeholder="ابحث في الإجراءات..."
              class="flex-1 bg-transparent text-sm text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 outline-none"
              @keydown.esc="open = false"
            />
            <button v-if="searchQuery" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="searchQuery = ''">
              <XMarkIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 space-y-5">
          <!-- Alerts Section -->
          <div v-if="!searchQuery" class="space-y-2">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide px-1">تنبيهات</h3>
            <div v-if="alertsLoading" class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500 px-2 py-3">
              <span class="w-4 h-4 border-2 border-gray-300 border-t-primary-500 rounded-full animate-spin inline-block"></span>
              جارٍ التحميل...
            </div>
            <template v-else>
              <div
                v-if="overdueCount > 0"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl cursor-pointer bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                @click="navigate('/invoices')"
              >
                <ExclamationTriangleIcon class="w-4 h-4 text-red-500 flex-shrink-0" />
                <span class="text-sm text-red-700 dark:text-red-400 font-medium">{{ overdueCount }} فاتورة متأخرة</span>
                <ArrowLeftIcon class="w-3.5 h-3.5 text-red-400 mr-auto" />
              </div>
              <div
                v-if="lowStockCount > 0"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl cursor-pointer bg-orange-50 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-800 hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors"
                @click="navigate('/inventory')"
              >
                <ExclamationTriangleIcon class="w-4 h-4 text-orange-500 flex-shrink-0" />
                <span class="text-sm text-orange-700 dark:text-orange-400 font-medium">{{ lowStockCount }} منتج مخزونه منخفض</span>
                <ArrowLeftIcon class="w-3.5 h-3.5 text-orange-400 mr-auto" />
              </div>
              <div
                v-if="overdueCount === 0 && lowStockCount === 0"
                class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500 px-2 py-2"
              >
                <CheckCircleIcon class="w-4 h-4 text-green-400" />
                لا توجد تنبيهات حالياً
              </div>
            </template>
          </div>

          <!-- Quick Actions -->
          <div class="space-y-2">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide px-1">
              {{ searchQuery ? 'نتائج البحث' : 'إجراءات سريعة' }}
            </h3>
            <div v-if="filteredActions.length === 0" class="text-center py-8">
              <MagnifyingGlassIcon class="w-8 h-8 mx-auto mb-2 text-gray-200 dark:text-gray-700" />
              <p class="text-sm text-gray-400 dark:text-gray-500">لا توجد نتائج لـ "{{ searchQuery }}"</p>
            </div>
            <div
              v-for="action in filteredActions"
              :key="action.label"
              class="flex items-center gap-3 px-3 py-3 rounded-xl cursor-pointer group transition-all duration-150"
              :class="'bg-gray-50 dark:bg-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 border border-gray-100 dark:border-gray-700 hover:border-primary-200 dark:hover:border-primary-800'"
              @click="navigate(action.route)"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors"
                :class="[action.iconBg, 'group-hover:opacity-90']"
              >
                <component :is="action.icon" class="w-4 h-4" :class="action.iconColor" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-gray-100 group-hover:text-primary-700 dark:group-hover:text-primary-400 transition-colors">{{ action.label }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ action.description }}</p>
              </div>
              <ArrowLeftIcon class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-primary-400 transition-colors" />
            </div>
          </div>
        </div>

        <!-- Panel Footer -->
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 flex-shrink-0">
          <p class="text-[10px] text-gray-400 dark:text-gray-600 text-center">
            اضغط <kbd class="bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-1 rounded border border-gray-200 dark:border-gray-600">Ctrl+K</kbd> للفتح/الإغلاق &nbsp;·&nbsp; <kbd class="bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-1 rounded border border-gray-200 dark:border-gray-600">Esc</kbd> للإغلاق
          </p>
        </div>
      </div>
    </Transition>

    <!-- Backdrop (click outside to close) -->
    <Transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[9988] bg-black/20 dark:bg-black/40 backdrop-blur-[1px]"
        @click="open = false"
      />
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import {
  MagnifyingGlassIcon,
  XMarkIcon,
  ArrowLeftIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  DocumentPlusIcon,
  UserPlusIcon,
  ShoppingCartIcon,
  WrenchScrewdriverIcon,
  DocumentTextIcon,
  ChartBarIcon,
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'

const router = useRouter()
const { get } = useApi()

const open = ref(false)
const searchQuery = ref('')
const searchRef = ref<HTMLInputElement | null>(null)

const overdueCount = ref(0)
const lowStockCount = ref(0)
const alertsLoading = ref(false)

interface QuickAction {
  label: string
  description: string
  route: string
  icon: any
  iconBg: string
  iconColor: string
  keywords: string[]
}

const quickActions: QuickAction[] = [
  {
    label: 'إنشاء فاتورة جديدة',
    description: 'أنشئ فاتورة مبيعات جديدة',
    route: '/invoices/create',
    icon: DocumentPlusIcon,
    iconBg: 'bg-blue-100 dark:bg-blue-900/30',
    iconColor: 'text-blue-600 dark:text-blue-400',
    keywords: ['فاتورة', 'مبيعات', 'جديدة', 'انشاء', 'إنشاء'],
  },
  {
    label: 'إضافة عميل',
    description: 'أضف عميلاً جديداً إلى النظام',
    route: '/customers',
    icon: UserPlusIcon,
    iconBg: 'bg-green-100 dark:bg-green-900/30',
    iconColor: 'text-green-600 dark:text-green-400',
    keywords: ['عميل', 'اضافة', 'إضافة', 'جديد'],
  },
  {
    label: 'نقطة البيع',
    description: 'افتح واجهة نقطة البيع المباشرة',
    route: '/pos',
    icon: ShoppingCartIcon,
    iconBg: 'bg-purple-100 dark:bg-purple-900/30',
    iconColor: 'text-purple-600 dark:text-purple-400',
    keywords: ['pos', 'نقطة', 'البيع', 'بيع', 'كاشير'],
  },
  {
    label: 'أمر عمل جديد',
    description: 'أنشئ أمر عمل لمركز الخدمة أو المنفذ',
    route: '/work-orders/create',
    icon: WrenchScrewdriverIcon,
    iconBg: 'bg-yellow-100 dark:bg-yellow-900/30',
    iconColor: 'text-yellow-600 dark:text-yellow-400',
    keywords: ['امر', 'أمر', 'عمل', 'مركز خدمة', 'منفذ', 'صيانة'],
  },
  {
    label: 'عرض سعر',
    description: 'أنشئ عرض سعر لعميل',
    route: '/crm/quotes',
    icon: DocumentTextIcon,
    iconBg: 'bg-indigo-100 dark:bg-indigo-900/30',
    iconColor: 'text-indigo-600 dark:text-indigo-400',
    keywords: ['عرض', 'سعر', 'عروض', 'اسعار', 'أسعار', 'crm', 'قرم'],
  },
  {
    label: 'تقرير اليوم',
    description: 'اطلع على تقارير اليوم',
    route: '/reports',
    icon: ChartBarIcon,
    iconBg: 'bg-rose-100 dark:bg-rose-900/30',
    iconColor: 'text-rose-600 dark:text-rose-400',
    keywords: ['تقرير', 'تقارير', 'اليوم', 'احصاء', 'احصائيات'],
  },
]

const filteredActions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return quickActions
  return quickActions.filter(a =>
    a.label.includes(q) ||
    a.description.includes(q) ||
    a.keywords.some(k => k.includes(q))
  )
})

function navigate(route: string) {
  router.push(route)
  open.value = false
}

function togglePanel() {
  open.value = !open.value
}

async function loadAlerts() {
  alertsLoading.value = true
  try {
    const [overdueRes, stockRes] = await Promise.allSettled([
      get('/reports/overdue-receivables'),
      get('/reports/inventory', { low_stock: 1 }),
    ])
    if (overdueRes.status === 'fulfilled') {
      const d = overdueRes.value
      overdueCount.value = d?.data?.total ?? d?.total ?? d?.count ?? (Array.isArray(d?.data) ? d.data.length : 0)
    }
    if (stockRes.status === 'fulfilled') {
      const d = stockRes.value
      lowStockCount.value = d?.data?.total ?? d?.total ?? d?.count ?? (Array.isArray(d?.data) ? d.data.length : 0)
    }
  } catch {
    // silent
  } finally {
    alertsLoading.value = false
  }
}

function handleKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    togglePanel()
  }
  if (e.key === 'Escape' && open.value) {
    open.value = false
  }
}

watch(open, (val) => {
  if (val) {
    nextTick(() => searchRef.value?.focus())
    loadAlerts()
    searchQuery.value = ''
  }
})

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))
</script>

<style scoped>
.panel-slide-enter-active {
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
}
.panel-slide-leave-active {
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;
}
.panel-slide-enter-from {
  transform: translateX(-30px);
  opacity: 0;
}
.panel-slide-leave-to {
  transform: translateX(-30px);
  opacity: 0;
}

.fade-enter-active { transition: opacity 0.2s ease; }
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
