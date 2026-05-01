<template>
  <div class="space-y-4" :dir="locale.langInfo.value.dir">
    <div class="no-print border-b border-gray-200 dark:border-slate-700">
      <nav class="flex gap-1 overflow-x-auto" role="tablist">
        <RouterLink
          v-for="tab in tabs"
          :key="tab.name"
          :to="{ name: tab.name }"
          class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition whitespace-nowrap"
          :class="tabClass(tab.name)"
        >
          {{ tab.label }}
        </RouterLink>
      </nav>
    </div>
    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useLocale } from '@/composables/useLocale'

const route = useRoute()
const locale = useLocale()

function l(ar: string, en: string) {
  return locale.lang.value === 'ar' ? ar : en
}

const tabs = computed(() => [
  { name: 'catalog.services' as const, label: l('الخدمات', 'Services') },
  { name: 'catalog.products' as const, label: l('المنتجات', 'Products') },
])

function tabClass(name: string) {
  const active = route.matched.some((r) => r.name === name)
  return active
    ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400'
    : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300'
}
</script>
