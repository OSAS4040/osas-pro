<template>
  <aside
    class="rounded-3xl border border-slate-200/80 dark:border-slate-700/60 bg-white/90 dark:bg-slate-900/40 p-4 space-y-4 text-sm"
    :class="collapsed ? 'lg:block hidden' : ''"
  >
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ title }}</h2>
      <button type="button" class="lg:hidden text-xs text-primary-600" @click="$emit('toggle-collapse')">{{ collapseLabel }}</button>
    </div>
    <div class="space-y-3">
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ fromLabel }}</label>
        <input v-model="localFrom" type="date" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs" />
      </div>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ toLabel }}</label>
        <input v-model="localTo" type="date" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs" />
      </div>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ branchLabel }}</label>
        <input v-model="localBranch" type="text" inputmode="numeric" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs" :placeholder="optional" />
      </div>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ customerLabel }}</label>
        <input v-model="localCustomer" type="text" inputmode="numeric" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs" :placeholder="optional" />
      </div>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ userLabel }}</label>
        <input v-model="localUser" type="text" inputmode="numeric" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs" :placeholder="optional" />
      </div>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ typesLabel }}</label>
        <div class="flex flex-wrap gap-2">
          <label v-for="opt in typeOptions" :key="opt" class="inline-flex items-center gap-1 text-xs">
            <input type="checkbox" :value="opt" v-model="localTypes" class="rounded border-slate-300" />
            <span>{{ opt }}</span>
          </label>
        </div>
      </div>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ attentionLabel }}</label>
        <select v-model="localAttention" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs">
          <option value="">{{ any }}</option>
          <option value="normal">normal</option>
          <option value="watch">watch</option>
          <option value="important">important</option>
          <option value="critical">critical</option>
        </select>
      </div>
      <label class="inline-flex items-center gap-2 text-xs">
        <input v-model="localIncludeFinancial" type="checkbox" class="rounded border-slate-300" />
        {{ includeFinancialLabel }}
      </label>
      <div>
        <label class="block text-[11px] text-slate-500 mb-1">{{ perPageLabel }}</label>
        <select v-model.number="localPerPage" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 text-xs">
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </div>
      <button type="button" class="w-full btn btn-primary text-xs py-2" @click="apply">{{ applyLabel }}</button>
    </div>
    <div class="border-t border-slate-200 dark:border-slate-700 pt-3 space-y-1 text-[11px] text-slate-500">
      <p class="font-medium text-slate-600 dark:text-slate-300">{{ legendTitle }}</p>
      <p>{{ legendHint }}</p>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  from: string
  to: string
  branchId: string
  customerId: string
  userId: string
  types: string[]
  attentionLevel: string
  includeFinancial: boolean
  perPage: number
  collapsed: boolean
  title: string
  fromLabel: string
  toLabel: string
  branchLabel: string
  customerLabel: string
  userLabel: string
  typesLabel: string
  attentionLabel: string
  includeFinancialLabel: string
  perPageLabel: string
  applyLabel: string
  any: string
  optional: string
  legendTitle: string
  legendHint: string
  collapseLabel: string
}>()

const emit = defineEmits<{
  apply: [
    payload: {
      from: string
      to: string
      branchId: string
      customerId: string
      userId: string
      types: string[]
      attentionLevel: string
      includeFinancial: boolean
      perPage: number
    },
  ]
  'toggle-collapse': []
}>()

const typeOptions = ['work_order', 'invoice', 'payment', 'ticket']

const localFrom = ref(props.from)
const localTo = ref(props.to)
const localBranch = ref(props.branchId)
const localCustomer = ref(props.customerId)
const localUser = ref(props.userId)
const localTypes = ref([...props.types])
const localAttention = ref(props.attentionLevel)
const localIncludeFinancial = ref(props.includeFinancial)
const localPerPage = ref(props.perPage)

watch(
  () => props.from,
  (v) => {
    localFrom.value = v
  },
)
watch(
  () => props.to,
  (v) => {
    localTo.value = v
  },
)
watch(
  () => props.types,
  (v) => {
    localTypes.value = [...v]
  },
)

function apply(): void {
  emit('apply', {
    from: localFrom.value,
    to: localTo.value,
    branchId: localBranch.value,
    customerId: localCustomer.value,
    userId: localUser.value,
    types: [...localTypes.value],
    attentionLevel: localAttention.value,
    includeFinancial: localIncludeFinancial.value,
    perPage: localPerPage.value,
  })
}
</script>
