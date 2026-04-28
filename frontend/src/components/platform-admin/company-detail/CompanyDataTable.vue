<template>
  <PlatformDataTable :columns="columns" :rows="rows" :loading="loading" :empty-label="emptyLabel">
    <template #cell="{ row, column }">
      <RouterLink
        v-if="isInternalLink(row[column.key])"
        :to="String((row[column.key] as LinkCell).to)"
        class="font-semibold text-primary-700 underline dark:text-primary-400"
      >
        {{ String((row[column.key] as LinkCell).label) }}
      </RouterLink>
      <a
        v-else-if="isExternalLink(row[column.key])"
        :href="String((row[column.key] as LinkCell).to)"
        class="font-semibold text-primary-700 underline dark:text-primary-400"
      >
        {{ String((row[column.key] as LinkCell).label) }}
      </a>
      <span v-else>{{ formatCell(row, column.key) }}</span>
    </template>
  </PlatformDataTable>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import PlatformDataTable from '@/components/platform-admin/ui/PlatformDataTable.vue'

interface ColumnDef {
  key: string
  label: string
}
interface LinkCell {
  label: string
  to: string
  external?: boolean
}

withDefaults(
  defineProps<{
    columns: ColumnDef[]
    rows: Record<string, unknown>[]
    loading?: boolean
    emptyLabel?: string
  }>(),
  {
    loading: false,
    emptyLabel: 'لا توجد بيانات.',
  },
)

function formatCell(row: Record<string, unknown>, key: string): string {
  const value = row[key]
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

function isInternalLink(value: unknown): value is LinkCell {
  return typeof value === 'object' && value !== null && 'to' in value && 'label' in value && !(value as LinkCell).external
}

function isExternalLink(value: unknown): value is LinkCell {
  return typeof value === 'object' && value !== null && 'to' in value && 'label' in value && Boolean((value as LinkCell).external)
}
</script>
