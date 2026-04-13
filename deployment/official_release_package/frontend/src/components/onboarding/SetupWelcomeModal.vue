<template>
  <Teleport to="body">
    <Transition name="overlay-fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/55 p-4 backdrop-blur-[2px]"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @click.self="onLater"
      >
        <div
          class="relative max-w-md w-full overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900 via-primary-950 to-slate-900 text-white shadow-2xl"
          :dir="dir"
        >
          <div class="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-primary-500/25 blur-3xl" />
          <div class="absolute -bottom-12 -left-12 h-32 w-32 rounded-full bg-cyan-500/20 blur-2xl" />
          <div class="relative p-6 sm:p-8">
            <div class="flex items-start gap-3">
              <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15"
                aria-hidden="true"
              >
                <SparklesIcon class="h-7 w-7 text-amber-300" />
              </div>
              <div class="min-w-0 flex-1">
                <h2 :id="titleId" class="text-lg font-bold leading-snug tracking-tight">
                  {{ t.welcomeTitle }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-200/90">
                  {{ t.welcomeBody }}
                </p>
              </div>
            </div>
            <ul class="mt-5 space-y-2.5 text-xs text-slate-300/95">
              <li v-for="(line, i) in t.welcomeBullets" :key="i" class="flex gap-2">
                <span class="mt-0.5 text-primary-300" aria-hidden="true">✓</span>
                <span>{{ line }}</span>
              </li>
            </ul>
            <div class="mt-7 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="rounded-xl border border-white/15 px-4 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-white/10"
                @click="onLater"
              >
                {{ t.later }}
              </button>
              <button
                type="button"
                class="rounded-xl bg-gradient-to-l from-primary-500 to-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-900/40 transition hover:brightness-110"
                @click="onStart"
              >
                {{ t.start }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { SparklesIcon } from '@heroicons/vue/24/solid'
import { useI18nStore } from '@/stores/i18n'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ start: []; later: [] }>()

const i18n = useI18nStore()
const dir = computed(() => i18n.dir)
const titleId = 'setup-welcome-title'

const pack = computed(() => {
  const m = i18n.messages as Record<string, unknown>
  return (m.onboarding ?? {}) as Record<string, unknown>
})

const t = computed(() => {
  const o = pack.value
  const ar = i18n.currentLang === 'ar'
  const bullets = o.welcomeBullets
  const welcomeBullets = Array.isArray(bullets)
    ? (bullets as string[])
    : [
        ar ? 'قائمة إعداد واضحة مع روابط مباشرة لكل خطوة' : 'A clear checklist with direct links for each step',
        ar ? 'تقدّم مرئي — يمكنك المتابعة لاحقاً من الإعدادات' : 'Visible progress — continue later from Settings',
        ar ? 'مصممة لتناسب مدير المنشأة دون إعاقة يوم العمل' : 'Built for managers without blocking daily work',
      ]
  return {
    welcomeTitle: (o.welcomeTitle as string) || (ar ? 'مرحباً بك في أسس برو' : 'Welcome to Osas Pro'),
    welcomeBody:
      (o.welcomeBody as string) ||
      (ar
        ? 'نحن سعداء بانضمامك. خذ دقائق لضبط الأساسيات — نرشدك خطوة بخطوة لتجهيز ملف منشأتك والاستفادة القصوى من النظام.'
        : 'We are glad you are here. Take a few minutes to set up the basics — we will guide you step by step.'),
    welcomeBullets,
    start: (o.startSetup as string) || (ar ? 'ابدأ ضبط المنشأة' : 'Start setup'),
    later: (o.welcomeLater as string) || (ar ? 'سأكمل لاحقاً' : 'I will do this later'),
  }
})

function onStart() {
  emit('start')
}

function onLater() {
  emit('later')
}
</script>

<style scoped>
.overlay-fade-enter-active,
.overlay-fade-leave-active {
  transition: opacity 0.2s ease;
}
.overlay-fade-enter-from,
.overlay-fade-leave-to {
  opacity: 0;
}
</style>
