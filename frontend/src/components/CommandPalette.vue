<template>
  <Teleport to="body">
    <Transition name="palette">
      <div
        v-if="open"
        class="fixed inset-0 z-[9998] bg-black/50 backdrop-blur-sm flex items-start justify-center pt-24 px-4"
        dir="rtl"
        @click.self="close"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
          <!-- Search Input -->
          <div class="flex items-center gap-3 px-4 py-3.5 border-b border-gray-100">
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
            <input
              ref="inputRef"
              v-model="query"
              placeholder="ابحث عن صفحة أو إجراء..."
              class="flex-1 text-base outline-none bg-transparent text-gray-900 placeholder-gray-400"
              @keydown.down.prevent="move(1)"
              @keydown.up.prevent="move(-1)"
              @keydown.enter.prevent="confirm"
              @keydown.esc="close"
            />
            <kbd class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded border border-gray-200">Esc</kbd>
          </div>

          <!-- Results -->
          <div class="max-h-80 overflow-y-auto p-2">
            <template v-if="filtered.length">
              <div
                v-for="(item, i) in filtered"
                :key="item.to"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl cursor-pointer transition-colors"
                :class="i === cursor ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50'"
                @click="go(item)"
                @mouseenter="cursor = i"
              >
                <component :is="item.icon" class="w-4 h-4 flex-shrink-0" :class="i === cursor ? 'text-primary-600' : 'text-gray-400'" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ item.label }}</p>
                  <p v-if="item.group" class="text-xs text-gray-400">{{ item.group }}</p>
                </div>
                <ArrowUpLeftIcon v-if="i === cursor" class="w-3.5 h-3.5 text-primary-400" />
              </div>
            </template>
            <div v-else class="py-10 text-center text-gray-400 text-sm">
              <MagnifyingGlassIcon class="w-8 h-8 mx-auto mb-2 text-gray-200" />
              لا توجد نتائج لـ "{{ query }}"
            </div>
          </div>

          <!-- Footer Hint -->
          <div class="flex items-center gap-4 px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-xs text-gray-400">
            <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1">↑↓</kbd> للتنقل</span>
            <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1">Enter</kbd> للانتقال</span>
            <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1">Esc</kbd> إغلاق</span>
            <span class="mr-auto flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1.5">Ctrl</kbd>+<kbd class="bg-white border border-gray-200 rounded px-1.5">K</kbd> فتح/إغلاق</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import {
  MagnifyingGlassIcon, ArrowUpLeftIcon, HomeIcon, DocumentTextIcon, CubeIcon,
  UsersIcon, ChartBarIcon, Cog6ToothIcon, TruckIcon, ShoppingCartIcon,
  ClipboardDocumentIcon, BuildingOfficeIcon, CalendarDaysIcon, FireIcon,
  UserGroupIcon, ClockIcon, CurrencyDollarIcon, CreditCardIcon, BookOpenIcon,
  TableCellsIcon, ArchiveBoxIcon, ShoppingBagIcon, ShieldCheckIcon, StarIcon,
  MagnifyingGlassCircleIcon, WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'

const router  = useRouter()
const open    = ref(false)
const query   = ref('')
const cursor  = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)

const allItems = [
  { label: 'الرئيسية',              to: '/',                      icon: HomeIcon,                group: 'الرئيسي' },
  { label: 'نقطة البيع',             to: '/pos',                   icon: ShoppingCartIcon,        group: 'الرئيسي' },
  { label: 'الفواتير',               to: '/invoices',              icon: DocumentTextIcon,        group: 'الرئيسي' },
  { label: 'أوامر العمل',            to: '/work-orders',           icon: ClipboardDocumentIcon,   group: 'الرئيسي' },
  { label: 'الرافعات والمنافذ',      to: '/bays',                  icon: BuildingOfficeIcon,      group: 'الورشة' },
  { label: 'الحجوزات',              to: '/bookings',              icon: CalendarDaysIcon,        group: 'الورشة' },
  { label: 'الخريطة الحرارية',       to: '/bays/heatmap',          icon: FireIcon,                group: 'الورشة' },
  { label: 'الموظفون',               to: '/workshop/employees',    icon: UserGroupIcon,           group: 'الورشة' },
  { label: 'المهام',                 to: '/workshop/tasks',        icon: ClipboardDocumentIcon,   group: 'الورشة' },
  { label: 'الحضور والانصراف',       to: '/workshop/attendance',   icon: ClockIcon,               group: 'الورشة' },
  { label: 'العمولات',               to: '/workshop/commissions',  icon: CurrencyDollarIcon,      group: 'الورشة' },
  { label: 'العملاء',                to: '/customers',             icon: UsersIcon,               group: 'العملاء' },
  { label: 'المركبات',               to: '/vehicles',              icon: TruckIcon,               group: 'العملاء' },
  { label: 'التحقق من اللوحة',       to: '/fleet/verify-plate',    icon: MagnifyingGlassIcon,     group: 'الأسطول' },
  { label: 'محافظ الأسطول',          to: '/fleet/wallet',          icon: CreditCardIcon,          group: 'الأسطول' },
  { label: 'المحفظة',                to: '/wallet',                icon: CreditCardIcon,          group: 'المالية' },
  { label: 'دفتر الأستاذ',           to: '/ledger',                icon: BookOpenIcon,            group: 'المالية' },
  { label: 'دليل الحسابات',          to: '/chart-of-accounts',     icon: TableCellsIcon,          group: 'المالية' },
  { label: 'المنتجات',               to: '/products',              icon: CubeIcon,                group: 'المخزون' },
  { label: 'المخزون',                to: '/inventory',             icon: ArchiveBoxIcon,          group: 'المخزون' },
  { label: 'الموردون',               to: '/suppliers',             icon: TruckIcon,               group: 'المخزون' },
  { label: 'المشتريات',              to: '/purchases',             icon: ShoppingBagIcon,         group: 'المخزون' },
  { label: 'الحوكمة والسياسات',      to: '/governance',            icon: ShieldCheckIcon,         group: 'الحوكمة' },
  { label: 'التقارير',               to: '/reports',               icon: ChartBarIcon,            group: 'التقارير' },
  { label: 'الإعدادات',              to: '/settings',              icon: Cog6ToothIcon,           group: 'أخرى' },
  { label: 'التكاملات',              to: '/settings/integrations', icon: WrenchScrewdriverIcon,   group: 'أخرى' },
  { label: 'اشتراكي',               to: '/subscription',          icon: StarIcon,                group: 'الاشتراك' },
  { label: 'الباقات',                to: '/plans',                 icon: StarIcon,                group: 'الاشتراك' },
]

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return allItems.slice(0, 8)
  return allItems.filter(i =>
    i.label.includes(q) || i.group.includes(q)
  ).slice(0, 10)
})

watch(query, () => { cursor.value = 0 })

function move(dir: number) {
  cursor.value = (cursor.value + dir + filtered.value.length) % filtered.value.length
}

function go(item: typeof allItems[0]) {
  router.push(item.to)
  close()
}

function confirm() {
  if (filtered.value[cursor.value]) go(filtered.value[cursor.value])
}

function close() {
  open.value = false
  query.value = ''
  cursor.value = 0
}

function handleKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    open.value = !open.value
    if (open.value) {
      nextTick(() => inputRef.value?.focus())
    }
  }
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))

watch(open, v => { if (v) nextTick(() => inputRef.value?.focus()) })
</script>

<style scoped>
.palette-enter-active  { transition: all 0.2s ease-out; }
.palette-leave-active  { transition: all 0.15s ease-in; }
.palette-enter-from    { opacity: 0; }
.palette-leave-to      { opacity: 0; }
.palette-enter-from .bg-white,
.palette-leave-to .bg-white { transform: scale(0.97) translateY(-8px); }
</style>
