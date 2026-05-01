<template>
  <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
    <PlatformKpiCard v-for="item in items" :key="item.key" :label="item.label" :value="item.value" :hint="item.hint" />
  </div>
</template>

<script setup lang="ts">
import PlatformKpiCard from '@/components/platform-admin/ui/PlatformKpiCard.vue'

interface KpiItem {
  key: string
  label: string
  value: string
  hint?: string
}

defineProps<{
  items: KpiItem[]
}>()
</script>
