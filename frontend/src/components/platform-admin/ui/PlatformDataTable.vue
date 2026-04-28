<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
    <table class="w-full text-sm">
      <thead class="bg-slate-100 dark:bg-slate-800/70">
        <tr>
          <th v-for="col in columns" :key="col.key" class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <tr v-if="loading">
          <td :colspan="columns.length" class="px-3 py-4 text-center text-xs text-slate-500 dark:text-slate-400">جاري التحميل...</td>
        </tr>
        <tr v-else-if="rows.length === 0">
          <td :colspan="columns.length" class="px-3 py-4 text-center text-xs text-slate-500 dark:text-slate-400">{{ emptyLabel }}</td>
        </tr>
        <tr v-for="(row, idx) in rows" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/20">
          <td v-for="col in columns" :key="`${idx}-${col.key}`" class="px-3 py-2 text-xs text-slate-700 dark:text-slate-200">
            <slot name="cell" :row="row" :column="col">
              {{ formatCell(row, col.key) }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
interface ColumnDef {
  key: string
  label: string
}

const props = withDefaults(
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
</script>
