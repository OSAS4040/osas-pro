<template>
  <nav
    data-testid="platform-admin-quick-nav"
    class="sticky top-0 z-[8] border-b border-slate-200/90 bg-slate-50/95 shadow-sm backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/92"
    aria-label="تنقل سريع داخل مسار المنصة"
    dir="rtl"
  >
    <div class="mx-auto flex max-w-[1600px] flex-wrap items-center gap-1.5 px-4 py-2 sm:px-6">
      <span class="hidden shrink-0 text-[10px] font-bold text-slate-400 sm:inline dark:text-slate-500">قفزة:</span>
      <RouterLink
        v-for="item in quickNavItems"
        :key="'qn-' + item.id"
        :to="{ name: item.routeName }"
        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
        :class="isActive(item.routeName)
          ? 'bg-primary-600 text-white shadow-sm'
          : 'text-slate-600 hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
      >
        <component :is="item.icon" class="h-3.5 w-3.5 shrink-0 opacity-90" />
        {{ item.label }}
      </RouterLink>
      <RouterLink
        v-for="extra in quickNavExtras"
        :key="'qn-x-' + extra.routeName"
        :to="{ name: extra.routeName }"
        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
        :class="isActive(extra.routeName)
          ? 'bg-primary-600 text-white shadow-sm'
          : 'text-slate-600 hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
      >
        <component :is="extra.icon" class="h-3.5 w-3.5 shrink-0 opacity-90" />
        {{ extra.label }}
      </RouterLink>
    </div>
  </nav>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { CurrencyDollarIcon, TruckIcon } from '@heroicons/vue/24/outline'
import { platformAdminNavItems, type PlatformAdminNavItem } from '@/config/platformAdminNav'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

/** ترتيب يعكس أهمية التشغيل اليومي ثم الحوكمة والأدوات */
const QUICK_ORDER: PlatformAdminNavItem['id'][] = [
  'overview',
  'tenants',
  'customers',
  'finance',
  'incidents',
  'notifications',
  'command-surface',
  'support',
  'governance',
  'audit',
  'plans',
  'operator-commands',
]

const quickNavItems = computed(() => {
  const byId = new Map(platformAdminNavItems.map((i) => [i.id, i]))
  return QUICK_ORDER.map((id) => byId.get(id)).filter(Boolean) as PlatformAdminNavItem[]
})

type QuickExtra = { routeName: string; label: string; icon: Component }

const quickNavExtras = computed((): QuickExtra[] => {
  const out: QuickExtra[] = []
  if (auth.hasPermission('platform.pricing.view')) {
    out.push({ routeName: 'platform-pricing-requests', label: 'التسعير', icon: CurrencyDollarIcon })
  }
  if (auth.hasPermission('platform.providers.manage')) {
    out.push({ routeName: 'platform-providers-list', label: 'مزودون', icon: TruckIcon })
  }
  return out
})

function isActive(routeName: string): boolean {
  return route.name === routeName
}
</script>
