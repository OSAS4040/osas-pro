<template>
  <div class="space-y-1">
    <div class="relative">
      <input
        :value="modelValue"
        @input="onInput"
        @keypress="onKeypress"
        :placeholder="placeholder"
        :class="[
          'w-full px-3 py-2 border rounded-lg font-mono tracking-widest text-center text-lg uppercase transition focus:ring-2 focus:ring-primary-500 focus:border-primary-500',
          isValid ? 'border-gray-300 dark:border-slate-600' : (modelValue ? 'border-red-400' : 'border-gray-300 dark:border-slate-600'),
          'dark:bg-slate-800 dark:text-white',
        ]"
        :maxlength="12"
        dir="ltr"
      />
      <div v-if="modelValue && !isValid" class="absolute inset-y-0 left-2 flex items-center">
        <span class="text-red-500 text-xs">!</span>
      </div>
    </div>
    <p class="text-xs text-gray-400 dark:text-slate-500">
      النمط: أحرف + أرقام (مثال: ABC 1234 أو أبت 1234)
    </p>
    <p v-if="modelValue && !isValid" class="text-xs text-red-500">
      صيغة اللوحة غير صحيحة
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  modelValue: string
  placeholder?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [v: string] }>()

// Saudi plate: 3 letters (Arabic or Latin) + 4 digits, with optional space separator
const PLATE_REGEX = /^[A-Za-z\u0600-\u06FF]{1,4}[\s\-]?\d{1,4}$/

const isValid = computed(() => !props.modelValue || PLATE_REGEX.test(props.modelValue.trim()))

function normalize(val: string): string {
  // Remove non-alphanumeric except Arabic letters, Latin letters, digits, space, dash
  let v = val.replace(/[^\u0600-\u06FFa-zA-Z0-9\s\-]/g, '')
  // Uppercase Latin letters
  v = v.toUpperCase()
  // Auto-insert space if 3 letters typed and next char is digit
  v = v.replace(/^([A-Z\u0600-\u06FF]{3})(\d)/, '$1 $2')
  return v
}

function onInput(e: Event) {
  const val = normalize((e.target as HTMLInputElement).value)
  emit('update:modelValue', val)
}

function onKeypress(e: KeyboardEvent) {
  // Allow: letters (Arabic + Latin), digits, space, dash, backspace
  const allowed = /[a-zA-Z0-9\u0600-\u06FF\s\-]/
  if (!allowed.test(e.key) && e.key !== 'Backspace') {
    e.preventDefault()
  }
}
</script>
