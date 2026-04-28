<template>
  <nav
    class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 mb-4 text-sm dark:border-slate-700"
    aria-label="اشتراكات المنصة"
  >
    <RouterLink
      v-for="l in items"
      :key="l.name"
      :to="{ name: l.name }"
      class="rounded-lg px-3 py-1.5 font-medium transition-colors"
      :class="
        isActive(l)
          ? 'bg-primary-600 text-white dark:bg-primary-500'
          : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700'
      "
    >
      <span class="inline-flex items-center gap-2">
        {{ l.label }}
        <span
          v-if="l.name === 'admin-subscriptions-review' && attentionTotalNum > 0"
          class="min-w-[1.25rem] rounded-full bg-rose-600 px-1.5 py-0.5 text-center text-[10px] font-extrabold leading-none text-white shadow-sm"
          :class="attentionTotalNum >= 10 ? 'animate-pulse' : ''"
        >
          {{ attentionTotalNum > 99 ? '99+' : attentionTotalNum }}
        </span>
      </span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { computed, unref } from 'vue'
import { useRoute } from 'vue-router'

const props = withDefaults(
  defineProps<{
    /** عدّاد يحتاج تدخل المنصة (من attention-summary) */
    attentionTotal?: number | import('vue').Ref<number>
  }>(),
  { attentionTotal: 0 },
)

const route = useRoute()

const attentionTotalNum = computed(() => Number(unref(props.attentionTotal) || 0))

const items = [
  { name: 'admin-subscriptions-list', label: 'قائمة الاشتراكات' },
  { name: 'admin-subscriptions-review', label: 'طابور المراجعة' },
  { name: 'admin-subscriptions-invoices', label: 'فواتير المنصة' },
  { name: 'admin-subscriptions-transactions', label: 'المعاملات البنكية' },
  { name: 'admin-subscriptions-wallets', label: 'المحافظ' },
  { name: 'admin-subscriptions-control', label: 'المؤشرات' },
] as const

function isActive(l: (typeof items)[number]): boolean {
  const n = route.name
  if (n === l.name) return true
  if (l.name === 'admin-subscriptions-list' && n === 'admin-subscriptions-detail') return true
  if (l.name === 'admin-subscriptions-invoices' && n === 'admin-subscriptions-invoice-detail') return true
  if (l.name === 'admin-subscriptions-transactions' && n === 'admin-subscriptions-bank-tx') return true
  if (l.name === 'admin-subscriptions-review' && n === 'admin-subscriptions-payment-order') return true
  return false
}
</script>
