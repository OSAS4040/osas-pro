<template>
  <div class="relative" ref="containerRef">
    <!-- Input Field -->
    <div
      @click="open = !open"
      class="flex items-center gap-2 w-full border rounded-lg px-3 py-2 text-sm cursor-pointer transition-all"
      :class="[
        open
          ? 'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-800'
          : 'border-gray-300 dark:border-gray-600 hover:border-primary-400',
        'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200'
      ]"
    >
      <CalendarDaysIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
      <span class="flex-1 truncate">{{ displayValue }}</span>
      <ChevronDownIcon class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" />
    </div>

    <!-- Dropdown Panel -->
    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        :style="panelStyle"
        class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl w-72 overflow-hidden"
        dir="rtl"
      >
        <!-- Quick Presets -->
        <div class="p-2 border-b border-gray-100 dark:border-gray-700">
          <p class="text-xs text-gray-400 px-2 mb-1">اختيار سريع</p>
          <div class="grid grid-cols-2 gap-1">
            <button
              v-for="preset in presets"
              :key="preset.key"
              @click="selectPreset(preset)"
              class="text-right px-3 py-1.5 text-xs rounded-lg transition-all"
              :class="activePreset === preset.key
                ? 'bg-primary-600 text-white'
                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
            >
              {{ preset.label }}
            </button>
          </div>
        </div>

        <!-- Custom Range -->
        <div class="p-3">
          <p class="text-xs text-gray-400 mb-2">نطاق مخصص</p>
          <div class="space-y-2">
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">من</label>
              <input
                type="date"
                v-model="customFrom"
                :max="customTo || undefined"
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 text-sm dark:bg-gray-700 dark:text-white outline-none focus:border-primary-500"
              />
            </div>
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">إلى</label>
              <input
                type="date"
                v-model="customTo"
                :min="customFrom || undefined"
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 text-sm dark:bg-gray-700 dark:text-white outline-none focus:border-primary-500"
              />
            </div>
            <button
              @click="applyCustom"
              :disabled="!customFrom || !customTo"
              class="w-full py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-40 text-white rounded-lg text-sm font-medium transition-all"
            >
              تطبيق
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { CalendarDaysIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{
  modelValue?: string
  placeholder?: string
  mode?: 'single' | 'range'
  fromValue?: string
  toValue?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: string): void
  (e: 'update:fromValue', val: string): void
  (e: 'update:toValue', val: string): void
  (e: 'change', val: { from: string; to: string; preset?: string }): void
}>()

const open = ref(false)
const containerRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const panelStyle = ref<Record<string, string>>({})
const activePreset = ref('')
const customFrom = ref('')
const customTo = ref('')

const presets = [
  { key: 'today',    label: 'اليوم' },
  { key: 'yesterday',label: 'أمس' },
  { key: 'week',     label: 'آخر 7 أيام' },
  { key: 'month',    label: 'هذا الشهر' },
  { key: 'last30',   label: 'آخر 30 يوم' },
  { key: 'quarter',  label: 'هذا الربع' },
  { key: 'year',     label: 'هذه السنة' },
  { key: 'last12',   label: 'آخر 12 شهر' },
]

function getRange(key: string): { from: string; to: string } {
  const now = new Date()
  const fmt = (d: Date) => d.toISOString().split('T')[0]
  const start = (y: number, m: number, d: number) => fmt(new Date(y, m, d))

  switch (key) {
    case 'today':
      return { from: fmt(now), to: fmt(now) }
    case 'yesterday': {
      const y = new Date(now); y.setDate(y.getDate() - 1)
      return { from: fmt(y), to: fmt(y) }
    }
    case 'week': {
      const w = new Date(now); w.setDate(w.getDate() - 6)
      return { from: fmt(w), to: fmt(now) }
    }
    case 'month':
      return { from: start(now.getFullYear(), now.getMonth(), 1), to: fmt(now) }
    case 'last30': {
      const l = new Date(now); l.setDate(l.getDate() - 29)
      return { from: fmt(l), to: fmt(now) }
    }
    case 'quarter': {
      const q = Math.floor(now.getMonth() / 3)
      return { from: start(now.getFullYear(), q * 3, 1), to: fmt(now) }
    }
    case 'year':
      return { from: start(now.getFullYear(), 0, 1), to: fmt(now) }
    case 'last12': {
      const l = new Date(now); l.setFullYear(l.getFullYear() - 1)
      return { from: fmt(l), to: fmt(now) }
    }
    default:
      return { from: fmt(now), to: fmt(now) }
  }
}

function selectPreset(preset: { key: string; label: string }) {
  activePreset.value = preset.key
  const range = getRange(preset.key)
  customFrom.value = range.from
  customTo.value = range.to
  emit('update:modelValue', range.from)
  emit('update:fromValue', range.from)
  emit('update:toValue', range.to)
  emit('change', { from: range.from, to: range.to, preset: preset.key })
  open.value = false
}

function applyCustom() {
  if (!customFrom.value || !customTo.value) return
  activePreset.value = 'custom'
  emit('update:modelValue', customFrom.value)
  emit('update:fromValue', customFrom.value)
  emit('update:toValue', customTo.value)
  emit('change', { from: customFrom.value, to: customTo.value })
  open.value = false
}

const displayValue = computed(() => {
  if (activePreset.value && activePreset.value !== 'custom') {
    return presets.find(p => p.key === activePreset.value)?.label || ''
  }
  if (customFrom.value && customTo.value) {
    if (customFrom.value === customTo.value) return fmtAr(customFrom.value)
    return `${fmtAr(customFrom.value)} – ${fmtAr(customTo.value)}`
  }
  if (props.modelValue) return fmtAr(props.modelValue)
  return props.placeholder || 'اختر الفترة الزمنية'
})

function fmtAr(d: string) {
  try { return new Date(d).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' }) }
  catch { return d }
}

function positionPanel() {
  if (!containerRef.value) return
  const rect = containerRef.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const top = spaceBelow > 320 ? rect.bottom + 4 : rect.top - 320 - 4
  panelStyle.value = {
    top: `${top}px`,
    left: `${rect.left}px`,
    width: `${Math.max(rect.width, 288)}px`,
  }
}

watch(open, async (v) => {
  if (v) {
    await nextTick()
    positionPanel()
  }
})

function handleOutsideClick(e: MouseEvent) {
  if (!containerRef.value?.contains(e.target as Node) &&
      !panelRef.value?.contains(e.target as Node)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleOutsideClick)
  if (props.modelValue) {
    customFrom.value = props.modelValue
    customTo.value = props.modelValue
  }
  if (props.fromValue) customFrom.value = props.fromValue
  if (props.toValue) customTo.value = props.toValue
})
onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick))
</script>
