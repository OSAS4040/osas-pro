<template>
  <div class="min-h-screen bg-gray-50 flex" dir="rtl">
    <!-- Sidebar -->
    <aside data-print-chrome class="print:hidden w-56 bg-teal-800 flex flex-col flex-shrink-0">
      <!-- Logo -->
      <div class="h-16 flex items-center px-5 border-b border-teal-700">
        <div class="flex items-center gap-2">
          <TruckIcon class="w-6 h-6 text-white" />
          <div>
            <p class="text-sm font-bold text-white leading-none">بوابة الأسطول</p>
            <p class="text-xs text-teal-300 mt-0.5">{{ auth.user?.name }}</p>
          </div>
        </div>
      </div>

      <!-- Nav -->
      <nav class="flex-1 p-3 space-y-0.5">
        <RouterLink to="/fleet-portal"
                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-teal-200 hover:bg-teal-700 hover:text-white transition-colors"
                    active-class="bg-teal-700 text-white"
        >
          <HomeIcon class="w-4 h-4" />
          لوحة التحكم
        </RouterLink>
        <RouterLink to="/fleet-portal/new-order"
                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-teal-200 hover:bg-teal-700 hover:text-white transition-colors"
                    active-class="bg-teal-700 text-white"
        >
          <PlusCircleIcon class="w-4 h-4" />
          طلب خدمة
        </RouterLink>
        <RouterLink to="/fleet-portal/vehicles"
                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-teal-200 hover:bg-teal-700 hover:text-white transition-colors"
                    active-class="bg-teal-700 text-white"
        >
          <TruckIcon class="w-4 h-4" />
          مركباتي
        </RouterLink>
        <RouterLink to="/fleet-portal/top-up"
                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-teal-200 hover:bg-teal-700 hover:text-white transition-colors"
                    active-class="bg-teal-700 text-white"
        >
          <BanknotesIcon class="w-4 h-4" />
          شحن الرصيد
        </RouterLink>
        <RouterLink to="/fleet-portal/orders"
                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-teal-200 hover:bg-teal-700 hover:text-white transition-colors"
                    active-class="bg-teal-700 text-white"
        >
          <ClipboardDocumentListIcon class="w-4 h-4" />
          طلباتي
        </RouterLink>
      </nav>

      <!-- Logout -->
      <div class="p-3 border-t border-teal-700">
        <button class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-teal-300 hover:bg-teal-700 hover:text-white transition-colors"
                @click="handleLogout"
        >
          <ArrowLeftOnRectangleIcon class="w-4 h-4" />
          تسجيل الخروج
        </button>
      </div>
    </aside>

    <!-- Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <header
        data-print-chrome
        class="print:hidden h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6"
      >
        <h1 class="text-base font-semibold text-gray-900">{{ pageTitle }}</h1>
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-full bg-teal-600 flex items-center justify-center text-white text-xs font-bold">
            {{ auth.user?.name?.charAt(0) }}
          </div>
          <span class="text-sm text-gray-700">{{ auth.user?.name }}</span>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  HomeIcon, TruckIcon, BanknotesIcon, PlusCircleIcon,
  ClipboardDocumentListIcon, ArrowLeftOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const route  = useRoute()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/fleet/login')
}

const pageTitles: Record<string, string> = {
  'fleet-portal':           'لوحة التحكم',
  'fleet-portal.new-order': 'طلب خدمة جديد',
  'fleet-portal.vehicles':  'مركباتي',
  'fleet-portal.top-up':    'شحن الرصيد',
  'fleet-portal.orders':    'طلباتي',
}

const pageTitle = computed(() => pageTitles[route.name as string] ?? 'بوابة الأسطول')
</script>
