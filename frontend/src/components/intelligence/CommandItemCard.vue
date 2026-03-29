<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { CommandZoneItem, EntityReference } from '@/composables/useIntelligenceCommandCenter'
import SeverityBadge from './SeverityBadge.vue'
import SuggestedActionBlock from './SuggestedActionBlock.vue'
import {
  ChevronDownIcon,
  ChevronUpIcon,
  ArrowTopRightOnSquareIcon,
  QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps<{
  item: CommandZoneItem
}>()

const expanded = ref(false)
const whyOpen = ref(false)

const refsList = computed(() => props.item.related_entity_references ?? [])

const whyDetails = computed(() => props.item.why_details ?? [])
const signalsUsed = computed(() => props.item.signals_used ?? [])
const thresholds = computed(() => props.item.thresholds ?? {})
const confidence = computed(() =>
  props.item.confidence === null || props.item.confidence === undefined
    ? null
    : Math.round(Number(props.item.confidence) * 100),
)

function refToLocation(ref: EntityReference) {
  return {
    path: ref.href,
    query: { source: 'command-center' },
  }
}

const metaJson = computed(() => {
  const m = props.item.meta
  if (m === undefined || Object.keys(m).length === 0) {
    return ''
  }
  try {
    return JSON.stringify(m, null, 2)
  } catch {
    return ''
  }
})

const thresholdEntries = computed(() =>
  Object.entries(thresholds.value).filter(([k]) => k !== 'rule_id'),
)

const ruleId = computed(() => {
  const t = thresholds.value
  return typeof t.rule_id === 'string' ? t.rule_id : null
})
</script>

<template>
  <article
    class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/80 shadow-sm hover:shadow-md transition-shadow"
  >
    <div class="p-4">
      <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
        <h4 class="text-sm font-semibold text-gray-900 dark:text-slate-100 leading-snug flex-1 min-w-0">
          {{ item.title }}
        </h4>
        <SeverityBadge :severity="item.severity" />
      </div>

      <div class="space-y-2 text-sm">
        <div v-if="item.why_now">
          <span class="text-xs font-medium text-gray-500 dark:text-slate-400">لماذا الآن</span>
          <p class="text-gray-700 dark:text-slate-300 mt-0.5 leading-relaxed">{{ item.why_now }}</p>
        </div>

        <SuggestedActionBlock :text="item.suggested_action" />

        <div v-if="item.impact_if_ignored">
          <span class="text-xs font-medium text-gray-500 dark:text-slate-400">إن تجاهلت</span>
          <p class="text-gray-600 dark:text-slate-400 mt-0.5 text-sm leading-relaxed">{{ item.impact_if_ignored }}</p>
        </div>

        <!-- Phase 6 — لماذا؟ (explainability) -->
        <div class="pt-2 border-t border-gray-100 dark:border-slate-700">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 text-sm font-semibold text-primary-700 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200"
            @click="whyOpen = !whyOpen"
          >
            <span class="inline-flex items-center gap-1.5">
              <QuestionMarkCircleIcon class="w-5 h-5 opacity-80" aria-hidden="true" />
              لماذا؟
            </span>
            <ChevronUpIcon v-if="whyOpen" class="w-4 h-4" />
            <ChevronDownIcon v-else class="w-4 h-4" />
          </button>

          <div
            v-show="whyOpen"
            class="mt-3 space-y-3 rounded-lg bg-slate-50/90 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-600 p-3 text-xs text-gray-700 dark:text-slate-300"
          >
            <div v-if="whyDetails.length">
              <p class="text-[11px] font-semibold text-gray-500 dark:text-slate-400 mb-1.5">شرح مبسّط</p>
              <ul class="list-disc list-inside space-y-1 leading-relaxed">
                <li v-for="(line, i) in whyDetails" :key="i">{{ line }}</li>
              </ul>
            </div>

            <div v-if="signalsUsed.length">
              <p class="text-[11px] font-semibold text-gray-500 dark:text-slate-400 mb-1">المؤشرات المستخدمة</p>
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="(sig, j) in signalsUsed"
                  :key="j"
                  class="font-mono text-[10px] px-2 py-0.5 rounded bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400"
                >
                  {{ sig }}
                </span>
              </div>
            </div>

            <div v-if="thresholdEntries.length">
              <p class="text-[11px] font-semibold text-gray-500 dark:text-slate-400 mb-1">العتبات / القواعد</p>
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1">
                <template v-for="([k, v], idx) in thresholdEntries" :key="idx">
                  <dt class="text-gray-500 dark:text-slate-500 truncate" :title="k">{{ k }}</dt>
                  <dd class="font-mono text-[11px] text-gray-800 dark:text-slate-200 break-all">
                    {{ typeof v === 'object' ? JSON.stringify(v) : v }}
                  </dd>
                </template>
              </dl>
            </div>

            <p v-if="ruleId" class="text-[10px] text-gray-400 dark:text-slate-500 font-mono">
              rule: {{ ruleId }}
            </p>

            <div v-if="confidence !== null" class="pt-1">
              <div class="flex items-center justify-between text-[11px] mb-1">
                <span class="text-gray-500 dark:text-slate-400">درجة الثقة التقديرية</span>
                <span class="font-semibold text-primary-700 dark:text-primary-300">{{ confidence }}٪</span>
              </div>
              <div class="h-1.5 rounded-full bg-gray-200 dark:bg-slate-700 overflow-hidden">
                <div
                  class="h-full rounded-full bg-primary-500 dark:bg-primary-600 transition-all"
                  :style="{ width: `${confidence}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <div v-if="refsList.length" class="pt-2 border-t border-gray-100 dark:border-slate-700">
          <span class="text-xs font-medium text-gray-500 dark:text-slate-400">سياق تشغيلي</span>
          <div class="flex flex-wrap gap-2 mt-2">
            <RouterLink
              v-for="(r, idx) in refsList"
              :key="`${r.href}-${r.type}-${idx}`"
              :to="refToLocation(r)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary-50 dark:bg-primary-900/25 text-primary-800 dark:text-primary-200 border border-primary-200/80 dark:border-primary-800/50 hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors"
            >
              {{ r.label }}
              <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5 opacity-70" aria-hidden="true" />
            </RouterLink>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-2 text-xs text-gray-400 dark:text-slate-500">
          <span class="font-mono">{{ item.id }}</span>
          <span>·</span>
          <span>{{ item.source }}</span>
          <button
            type="button"
            class="ms-auto inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 hover:underline font-medium"
            @click="expanded = !expanded"
          >
            {{ expanded ? 'طيّ التفاصيل' : 'تفاصيل إضافية' }}
            <ChevronUpIcon v-if="expanded" class="w-4 h-4" />
            <ChevronDownIcon v-else class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <div
      v-show="expanded"
      class="border-t border-gray-100 dark:border-slate-700 px-4 py-3 bg-gray-50/80 dark:bg-slate-900/40 text-xs text-gray-600 dark:text-slate-400 space-y-2"
    >
      <p class="font-semibold text-gray-700 dark:text-slate-300">قراءة فقط — لا إجراءات من هذه اللوحة</p>
      <pre
        v-if="metaJson"
        class="mt-2 p-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 overflow-x-auto font-mono text-[11px] leading-relaxed"
      >{{ metaJson }}</pre>
      <p v-else class="text-gray-500 dark:text-slate-500">لا بيانات وصفية إضافية.</p>
    </div>
  </article>
</template>
