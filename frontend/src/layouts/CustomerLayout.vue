<template>
  <div class="min-h-screen bg-gray-50 dark:bg-slate-900 flex flex-col" dir="rtl">
    <!-- Header -->
    <header class="h-16 bg-gradient-to-l from-orange-500 to-amber-500 flex items-center justify-between px-5 shadow-md sticky top-0 z-30">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
          <UserCircleIcon class="w-5 h-5 text-white" />
        </div>
        <div>
          <p class="text-sm font-bold text-white leading-none">{{ auth.user?.name }}</p>
          <p class="text-xs text-orange-100 mt-0.5">بوابة العملاء والأساطيل</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <RouterLink to="/customer/notifications" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-white">
          <BellIcon class="w-4 h-4" />
        </RouterLink>
        <button @click="handleLogout"
          class="flex items-center gap-1.5 bg-white/10 hover:bg-white/20 text-white text-xs px-3 py-2 rounded-xl transition-colors">
          <ArrowLeftOnRectangleIcon class="w-4 h-4" />
          خروج
        </button>
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 p-4 max-w-2xl mx-auto w-full pb-24">
      <RouterView />
    </main>

    <!-- Bottom Navigation (Mobile-first) -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700 flex z-30 shadow-lg">
      <RouterLink v-for="item in navItems" :key="item.to" :to="item.to"
        class="flex-1 flex flex-col items-center justify-center py-2.5 text-xs gap-1 transition-colors"
        :class="$route.path.startsWith(item.to)
          ? 'text-orange-500 dark:text-orange-400'
          : 'text-gray-400 dark:text-slate-500 hover:text-orange-400'"
      >
        <component :is="item.icon" class="w-5 h-5" />
        <span class="text-[10px] font-medium">{{ item.label }}</span>
      </RouterLink>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'
import {
  UserCircleIcon, ArrowLeftOnRectangleIcon, BellIcon,
  HomeIcon, TruckIcon, DocumentTextIcon, CalendarDaysIcon, CreditCardIcon
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()
const $route = useRoute()

const navItems = [
  { to: '/customer/dashboard', icon: HomeIcon,          label: 'الرئيسية' },
  { to: '/customer/vehicles',  icon: TruckIcon,         label: 'مركباتي' },
  { to: '/customer/bookings',  icon: CalendarDaysIcon,  label: 'حجوزاتي' },
  { to: '/customer/invoices',  icon: DocumentTextIcon,  label: 'فواتيري' },
  { to: '/customer/wallet',    icon: CreditCardIcon,    label: 'المحفظة' },
]

async function handleLogout() {
  await auth.logout()
  router.push('/customer/login')
}
</script>
