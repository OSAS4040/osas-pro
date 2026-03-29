<template>
  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="badgeClass">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const map: Record<string, { cls: string; label: string }> = {
  pending:      { cls: 'bg-yellow-100 text-yellow-800', label: 'معلق' },
  approved:     { cls: 'bg-blue-100 text-blue-800',     label: 'معتمد' },
  in_progress:  { cls: 'bg-indigo-100 text-indigo-800', label: 'جاري' },
  completed:    { cls: 'bg-green-100 text-green-800',   label: 'مكتمل' },
  invoiced:     { cls: 'bg-purple-100 text-purple-800', label: 'مفوتر' },
  cancelled:    { cls: 'bg-red-100 text-red-800',       label: 'ملغي' },
  active:       { cls: 'bg-green-100 text-green-800',   label: 'نشط' },
  inactive:     { cls: 'bg-gray-100 text-gray-700',     label: 'غير نشط' },
  confirmed:    { cls: 'bg-green-100 text-green-800',   label: 'مؤكد' },
  reserved:     { cls: 'bg-blue-100 text-blue-800',     label: 'محجوز' },
  available:    { cls: 'bg-green-100 text-green-800',   label: 'متاح' },
  maintenance:  { cls: 'bg-red-100 text-red-800',       label: 'صيانة' },
  draft:        { cls: 'bg-gray-100 text-gray-700',     label: 'مسودة' },
  paid:         { cls: 'bg-green-100 text-green-800',   label: 'مدفوع' },
  overdue:      { cls: 'bg-red-100 text-red-800',       label: 'متأخر' },
}

const badgeClass = computed(() => map[props.status]?.cls ?? 'bg-gray-100 text-gray-700')
const label      = computed(() => map[props.status]?.label ?? props.status)
</script>
