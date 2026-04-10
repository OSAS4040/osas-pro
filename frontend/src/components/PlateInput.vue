<template>
  <div class="space-y-2" dir="rtl">
    <label v-if="label" class="block text-xs font-medium text-gray-600 dark:text-slate-400">{{ label }}</label>
    <div
      class="rounded-xl border-2 border-gray-300 dark:border-slate-600 bg-gradient-to-b from-white to-gray-50 dark:from-slate-800 dark:to-slate-900 p-3 shadow-inner overflow-hidden"
      :class="!touched || isValid ? '' : 'border-red-400 dark:border-red-500/50'"
    >
      <div class="flex items-stretch min-h-[88px] divide-x divide-gray-200 dark:divide-slate-600">
        <!-- حروف (جهة يمين اللوحة) -->
        <div class="flex-1 flex gap-1 justify-center items-stretch py-1 px-1">
          <div
            v-for="i in 3"
            :key="'L' + i"
            class="flex-1 min-w-0 flex flex-col justify-center rounded-lg border border-gray-200 dark:border-slate-600 bg-white/80 dark:bg-slate-800/80"
          >
            <input
              :ref="(el) => setLetterArRef(i - 1, el)"
              type="text"
              maxlength="1"
              autocomplete="off"
              :placeholder="PLACEHOLDER_LETTER_AR"
              class="w-full text-center text-lg font-bold text-gray-900 dark:text-white bg-transparent border-0 focus:ring-0 py-1 placeholder:text-gray-300/90 dark:placeholder:text-slate-600"
              :value="letterAr[i - 1]"
              @input="onLetterAr(i - 1, $event)"
              @keydown="onLetterArKeydown(i - 1, $event)"
              @paste="onPastePlate"
            />
            <div class="h-px bg-gray-200 dark:bg-slate-600 mx-0.5" />
            <input
              :ref="(el) => setLetterLatRef(i - 1, el)"
              type="text"
              maxlength="1"
              autocomplete="off"
              :placeholder="PLACEHOLDER_LETTER_LAT"
              class="w-full text-center text-sm font-mono font-bold uppercase text-primary-700 dark:text-primary-300 bg-transparent border-0 focus:ring-0 py-1 placeholder:text-primary-400/35 dark:placeholder:text-primary-500/30"
              :value="letterLat[i - 1]"
              @input="onLetterLat(i - 1, $event)"
              @keydown="onLetterLatKeydown(i - 1, $event)"
              @paste="onPastePlate"
            />
          </div>
        </div>
        <!-- أرقام -->
        <div class="flex-1 flex gap-0.5 justify-center items-stretch py-1 px-1 max-w-[52%]">
          <div
            v-for="i in 4"
            :key="'D' + i"
            class="flex-1 min-w-0 flex flex-col justify-center rounded-lg border border-gray-200 dark:border-slate-600 bg-white/80 dark:bg-slate-800/80"
          >
            <input
              :ref="(el) => setDigitArRef(i - 1, el)"
              type="text"
              inputmode="numeric"
              maxlength="1"
              autocomplete="off"
              :placeholder="PLACEHOLDER_DIGIT_AR"
              class="w-full text-center text-lg font-bold text-gray-900 dark:text-white bg-transparent border-0 focus:ring-0 py-1 placeholder:text-gray-300/90 dark:placeholder:text-slate-600"
              :value="digitAr[i - 1]"
              @input="onDigitAr(i - 1, $event)"
              @keydown="onDigitArKeydown(i - 1, $event)"
              @paste="onPastePlate"
            />
            <div class="h-px bg-gray-200 dark:bg-slate-600 mx-0.5" />
            <input
              :ref="(el) => setDigitEnRef(i - 1, el)"
              type="text"
              inputmode="numeric"
              maxlength="1"
              autocomplete="off"
              :placeholder="PLACEHOLDER_DIGIT_EN"
              class="w-full text-center text-sm font-mono font-bold text-gray-800 dark:text-slate-200 bg-transparent border-0 focus:ring-0 py-1 placeholder:text-gray-400/40 dark:placeholder:text-slate-500/35"
              :value="digitEn[i - 1]"
              @input="onDigitEn(i - 1, $event)"
              @keydown="onDigitEnKeydown(i - 1, $event)"
              @paste="onPastePlate"
            />
          </div>
        </div>
      </div>
    </div>
    <p class="text-[11px] text-gray-500 dark:text-slate-500">
      تنسيق مروري: الحروف والأرقام تتزامن بين العربية واللاتينية.
      <span class="font-mono dir-ltr inline-block bg-gray-100 dark:bg-slate-800 px-1.5 rounded mr-1">{{ displayCanonical }}</span>
    </p>
    <p v-if="touched && !isValid && plateFilled" class="text-xs text-red-600">استخدم 3 أحرف لاتينية مع 4 أرقام (مثل الصورة).</p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'

/** عرض خفيف لنوع الحقل فقط — لا يُحفَظ كنموذج */
const PLACEHOLDER_LETTER_AR = 'ح'
const PLACEHOLDER_LETTER_LAT = 'A'
const PLACEHOLDER_DIGIT_AR = '١'
const PLACEHOLDER_DIGIT_EN = '1'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
  }>(),
  { label: '' },
)
const emit = defineEmits<{ 'update:modelValue': [v: string] }>()

const AR_TO_LATIN: Record<string, string> = {
  أ: 'A', ا: 'A', ب: 'B', ت: 'T', ث: 'X', ج: 'G', ح: 'J', خ: 'K', د: 'D', ذ: 'D', ر: 'R', ز: 'Z',
  س: 'S', ش: 'S', ص: 'X', ض: 'D', ط: 'T', ظ: 'Z', ع: 'E', غ: 'G', ف: 'F', ق: 'Q', ك: 'K', ل: 'L',
  م: 'M', ن: 'N', ه: 'H', و: 'W', ى: 'D', ي: 'V',
}

const LATIN_TO_AR: Record<string, string> = {
  A: 'أ', B: 'ب', T: 'ط', X: 'ث', D: 'د', G: 'ج', J: 'ح', K: 'ك', R: 'ر', Z: 'ز', S: 'س',
  E: 'ع', F: 'ف', Q: 'ق', L: 'ل', M: 'م', N: 'ن', H: 'ه', W: 'و', V: 'ي',
}

const EN_DIG = '0123456789'
const AR_DIG = '٠١٢٣٤٥٦٧٨٩'

function toArDigit(c: string): string {
  const i = EN_DIG.indexOf(c)
  return i >= 0 ? AR_DIG[i]! : ''
}

function toEnDigit(c: string): string {
  const i = AR_DIG.indexOf(c)
  return i >= 0 ? EN_DIG[i]! : ''
}

const letterAr = ref<string[]>(['', '', ''])
const letterLat = ref<string[]>(['', '', ''])
const digitAr = ref<string[]>(['', '', '', ''])
const digitEn = ref<string[]>(['', '', '', ''])
const touched = ref(false)

const letterArRefs = ref<(HTMLInputElement | null)[]>([])
const letterLatRefs = ref<(HTMLInputElement | null)[]>([])
const digitArRefs = ref<(HTMLInputElement | null)[]>([])
const digitEnRefs = ref<(HTMLInputElement | null)[]>([])

function setLetterArRef(i: number, el: unknown) {
  letterArRefs.value[i] = (el as HTMLInputElement) ?? null
}
function setLetterLatRef(i: number, el: unknown) {
  letterLatRefs.value[i] = (el as HTMLInputElement) ?? null
}
function setDigitArRef(i: number, el: unknown) {
  digitArRefs.value[i] = (el as HTMLInputElement) ?? null
}
function setDigitEnRef(i: number, el: unknown) {
  digitEnRefs.value[i] = (el as HTMLInputElement) ?? null
}

function focusLetterLat(i: number) {
  nextTick(() => letterLatRefs.value[i]?.focus())
}
function focusLetterAr(i: number) {
  nextTick(() => letterArRefs.value[i]?.focus())
}
function focusDigitEn(i: number) {
  nextTick(() => digitEnRefs.value[i]?.focus())
}
function focusDigitAr(i: number) {
  nextTick(() => digitArRefs.value[i]?.focus())
}

/** الانتقال بعد حرف صالح — يبقى على نفس الصف (عربي/لاتيني) */
function advanceAfterLetter(idx: number, row: 'ar' | 'lat') {
  if (idx < 2) {
    if (row === 'ar') focusLetterAr(idx + 1)
    else focusLetterLat(idx + 1)
  } else {
    if (row === 'ar') focusDigitAr(0)
    else focusDigitEn(0)
  }
}

function advanceAfterDigit(idx: number, row: 'ar' | 'lat') {
  if (idx >= 3) return
  if (row === 'ar') focusDigitAr(idx + 1)
  else focusDigitEn(idx + 1)
}

const displayCanonical = computed(() => {
  const L = letterLat.value.join('').toUpperCase()
  const n = digitEn.value.join('')
  if (L.length === 3 && n.length === 4) return `${L} ${n}`
  return [L, n].filter(Boolean).join(' ') || '—'
})

const plateFilled = computed(() => letterLat.value.some(Boolean) || digitEn.value.some(Boolean))

const isValid = computed(() => {
  const L = letterLat.value.join('')
  const n = digitEn.value.join('')
  return /^[A-Z]{3}$/.test(L) && /^\d{4}$/.test(n)
})

function emitPlate() {
  const L = letterLat.value.map((c) => (c || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 1)).join('')
  const n = digitEn.value.map((c) => (c || '').replace(/\D/g, '').slice(0, 1)).join('')
  if (L.length === 3 && n.length === 4) emit('update:modelValue', `${L} ${n}`)
  else emit('update:modelValue', `${L} ${n}`.trim())
}

function parseExternal(raw: string) {
  if (!raw?.trim()) {
    letterAr.value = ['', '', '']
    letterLat.value = ['', '', '']
    digitAr.value = ['', '', '', '']
    digitEn.value = ['', '', '', '']
    return
  }
  const s = raw.trim().toUpperCase()
  const m = s.match(/^([A-Z]{3})[\s-]?(\d{4})$/)
  if (m) {
    const l = m[1]!
    const d = m[2]!
    for (let i = 0; i < 3; i++) {
      const ch = l[i]!
      letterLat.value[i] = ch
      letterAr.value[i] = LATIN_TO_AR[ch] ?? ''
    }
    for (let i = 0; i < 4; i++) {
      digitEn.value[i] = d[i]!
      digitAr.value[i] = toArDigit(d[i]!)
    }
    return
  }
}

watch(
  () => props.modelValue,
  (v) => parseExternal(v ?? ''),
  { immediate: true },
)

function onLetterAr(idx: number, e: Event) {
  touched.value = true
  const el = e.target as HTMLInputElement
  let raw = (el.value || '').trim()
  if (raw.length > 1) raw = raw.slice(-1)
  const ch = raw.slice(-1)
  letterAr.value[idx] = ch
  if (ch && AR_TO_LATIN[ch]) letterLat.value[idx] = AR_TO_LATIN[ch]!
  else if (!ch) letterLat.value[idx] = ''
  emitPlate()
  if (ch && letterLat.value[idx]) advanceAfterLetter(idx, 'ar')
}

function onLetterLat(idx: number, e: Event) {
  touched.value = true
  const el = e.target as HTMLInputElement
  const raw = (el.value || '').toUpperCase().replace(/[^A-Z]/g, '')
  const ch = raw.slice(-1)
  letterLat.value[idx] = ch
  if (ch && LATIN_TO_AR[ch]) letterAr.value[idx] = LATIN_TO_AR[ch]!
  else if (!ch) letterAr.value[idx] = ''
  emitPlate()
  if (ch) advanceAfterLetter(idx, 'lat')
}

function onLetterArKeydown(idx: number, e: KeyboardEvent) {
  if (e.key !== 'Backspace') return
  const t = e.target as HTMLInputElement
  if (t.value) return
  e.preventDefault()
  if (idx > 0) focusLetterAr(idx - 1)
}

function onLetterLatKeydown(idx: number, e: KeyboardEvent) {
  if (e.key !== 'Backspace') return
  const t = e.target as HTMLInputElement
  if (t.value) return
  e.preventDefault()
  if (idx > 0) focusLetterLat(idx - 1)
}

function onDigitAr(idx: number, e: Event) {
  touched.value = true
  const el = e.target as HTMLInputElement
  let raw = (el.value || '').trim()
  if (raw.length > 1) raw = raw.slice(-1)
  const en = toEnDigit(raw) || (/^\d$/.test(raw) ? raw : '')
  if (/^\d$/.test(en)) {
    digitEn.value[idx] = en
    digitAr.value[idx] = toArDigit(en)
    emitPlate()
    advanceAfterDigit(idx, 'ar')
  } else if (!raw) {
    digitEn.value[idx] = ''
    digitAr.value[idx] = ''
    emitPlate()
  } else {
    digitAr.value[idx] = ''
    digitEn.value[idx] = ''
    emitPlate()
  }
}

function onDigitEn(idx: number, e: Event) {
  touched.value = true
  const el = e.target as HTMLInputElement
  let raw = (el.value || '').replace(/\D/g, '')
  if (raw.length > 1) raw = raw.slice(-1)
  const ch = raw.slice(-1)
  if (/^\d$/.test(ch)) {
    digitEn.value[idx] = ch
    digitAr.value[idx] = toArDigit(ch)
    emitPlate()
    advanceAfterDigit(idx, 'lat')
  } else if (!ch) {
    digitEn.value[idx] = ''
    digitAr.value[idx] = ''
    emitPlate()
  } else {
    digitEn.value[idx] = ''
    digitAr.value[idx] = ''
    emitPlate()
  }
}

function onDigitArKeydown(idx: number, e: KeyboardEvent) {
  if (e.key !== 'Backspace') return
  const t = e.target as HTMLInputElement
  if (t.value) return
  e.preventDefault()
  if (idx > 0) focusDigitAr(idx - 1)
  else focusLetterAr(2)
}

function onDigitEnKeydown(idx: number, e: KeyboardEvent) {
  if (e.key !== 'Backspace') return
  const t = e.target as HTMLInputElement
  if (t.value) return
  e.preventDefault()
  if (idx > 0) focusDigitEn(idx - 1)
  else focusLetterLat(2)
}

function focusFirstEmptyAfterPaste() {
  nextTick(() => {
    for (let i = 0; i < 3; i++) {
      if (!letterLat.value[i]) {
        focusLetterLat(i)
        return
      }
    }
    for (let i = 0; i < 4; i++) {
      if (!digitEn.value[i]) {
        focusDigitEn(i)
        return
      }
    }
    digitEnRefs.value[3]?.focus()
  })
}

function westernizeDigits(s: string): string {
  let out = ''
  for (const c of s) {
    const i = AR_DIG.indexOf(c)
    out += i >= 0 ? EN_DIG[i]! : c
  }
  return out
}

function onPastePlate(e: ClipboardEvent) {
  e.preventDefault()
  const text = (e.clipboardData?.getData('text/plain') ?? '').trim()
  if (!text) return
  touched.value = true
  const compact = westernizeDigits(text.toUpperCase().replace(/[\s\-_/،,]/g, ''))
  const m = compact.match(/^([A-Z]{3})(\d{4})$/)
  if (m) {
    parseExternal(`${m[1]} ${m[2]}`)
    emitPlate()
    focusFirstEmptyAfterPaste()
    return
  }
  parseExternal(text)
  emitPlate()
  if (letterLat.value.every(Boolean) && digitEn.value.every(Boolean)) {
    digitEnRefs.value[3]?.focus()
    return
  }
  focusFirstEmptyAfterPaste()
}
</script>
