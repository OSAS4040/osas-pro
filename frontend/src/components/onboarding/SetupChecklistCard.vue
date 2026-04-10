<template>
  <section
    class="rounded-2xl border border-primary-200/80 bg-gradient-to-l from-primary-50/95 via-white to-cyan-50/80 p-4 shadow-sm dark:border-primary-900/40 dark:from-slate-900 dark:via-slate-900 dark:to-primary-950/30 sm:p-5"
    :dir="dir"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">
          {{ t.checklistTitle }}
        </h2>
        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
          {{ t.checklistSubtitle }}
        </p>
      </div>
      <div class="flex shrink-0 items-center gap-3">
        <div class="text-left sm:text-right" :class="dir === 'rtl' ? 'sm:text-left' : 'sm:text-right'">
          <p class="text-2xl font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ progressPercent }}%</p>
          <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
            {{ stepsDone }}/{{ totalSteps }} {{ t.doneLabel }}
          </p>
        </div>
        <div
          class="relative h-14 w-14 rounded-full border-4 border-white bg-white shadow-inner dark:border-slate-800 dark:bg-slate-800"
          aria-hidden="true"
        >
          <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
            <path
              class="text-slate-200 dark:text-slate-600"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
            />
            <path
              class="text-primary-600 dark:text-primary-400"
              :stroke-dasharray="`${progressPercent}, 100`"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
              fill="none"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-width="3"
            />
          </svg>
        </div>
      </div>
    </div>

    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-700">
      <div
        class="h-full rounded-full bg-gradient-to-l from-primary-600 to-cyan-500 transition-all duration-500"
        :style="{ width: `${progressPercent}%` }"
      />
    </div>

    <ul class="mt-4 space-y-2">
      <li v-for="row in rows" :key="row.id">
        <RouterLink
          :to="row.to"
          class="group flex items-center gap-3 rounded-xl border border-transparent px-2 py-2 transition hover:border-primary-200/80 hover:bg-white/80 dark:hover:border-primary-800/50 dark:hover:bg-slate-800/80"
        >
          <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold"
            :class="
              row.done
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400'
                : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'
            "
          >
            <CheckIcon v-if="row.done" class="h-4 w-4" />
            <span v-else class="text-xs">{{ row.index }}</span>
          </span>
          <div class="min-w-0 flex-1">
            <p
              class="text-sm font-medium"
              :class="row.done ? 'text-slate-500 line-through dark:text-slate-500' : 'text-slate-800 dark:text-slate-200'"
            >
              {{ row.title }}
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ row.hint }}</p>
          </div>
          <ChevronLeftIcon
            v-if="dir === 'rtl'"
            class="h-4 w-4 shrink-0 text-slate-300 opacity-0 transition group-hover:opacity-100 dark:text-slate-600"
          />
          <ChevronRightIcon
            v-else
            class="h-4 w-4 shrink-0 text-slate-300 opacity-0 transition group-hover:opacity-100 dark:text-slate-600"
          />
        </RouterLink>
      </li>
    </ul>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 pt-4 dark:border-slate-700/80">
      <p v-if="loading" class="text-xs text-slate-500">{{ t.loading }}</p>
      <p v-else-if="loadError" class="text-xs text-amber-700 dark:text-amber-400">{{ t.loadError }}</p>
      <p v-else class="text-xs text-slate-500 dark:text-slate-400">{{ t.refreshHint }}</p>
      <button
        type="button"
        class="text-xs font-semibold text-slate-600 underline underline-offset-2 hover:text-primary-700 dark:text-slate-400 dark:hover:text-primary-300"
        @click="$emit('dismiss')"
      >
        {{ t.dismiss }}
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'
import { useI18nStore } from '@/stores/i18n'
import type { SetupStatusPayload } from '@/composables/useSetupOnboarding'

const props = defineProps<{
  status: SetupStatusPayload | null
  loading: boolean
  loadError: boolean
  stepsDone: number
  totalSteps: number
  progressPercent: number
}>()

defineEmits<{ dismiss: [] }>()

const i18n = useI18nStore()
const dir = computed(() => i18n.dir)

const pack = computed(() => {
  const m = i18n.messages as Record<string, unknown>
  return (m.onboarding ?? {}) as Record<string, string>
})

const t = computed(() => {
  const o = pack.value
  const ar = i18n.currentLang === 'ar'
  return {
    checklistTitle: o.checklistTitle || (ar ? 'قائمة إعداد المنشأة' : 'Workspace setup checklist'),
    checklistSubtitle:
      o.checklistSubtitle ||
      (ar
        ? 'أكمل الخطوات بالترتيب المناسب لنشاطك — اضغط أي بند للانتقال مباشرة.'
        : 'Complete the steps in an order that fits your business — tap any item to go there.'),
    doneLabel: o.doneLabel || (ar ? 'مكتمل' : 'done'),
    loading: o.loading || (ar ? 'جارٍ التحقق من التقدم…' : 'Checking progress…'),
    loadError: o.loadError || (ar ? 'تعذّر تحميل حالة الإعداد. حدّث الصفحة لاحقاً.' : 'Could not load setup status. Refresh later.'),
    refreshHint: o.refreshHint || (ar ? 'عد للوحة التحكم بعد الحفظ لتحديث النسبة.' : 'Return to the dashboard after saving to refresh progress.'),
    dismiss: o.dismissChecklist || (ar ? 'إخفاء حتى أفتحها من الإعدادات' : 'Hide until I open it from Settings'),
    s1: o.stepCompany || (ar ? 'ملف المنشأة (البيانات الرسمية)' : 'Company legal profile'),
    s1h: o.stepCompanyHint || (ar ? 'الاسم، التواصل، الرقم الضريبي أو السجل' : 'Name, contact, VAT or CR'),
    s2: o.stepBranch || (ar ? 'فرع تشغيلي' : 'Operational branch'),
    s2h: o.stepBranchHint || (ar ? 'أضف فرعاً أو راجع الفروع الحالية' : 'Add or review branches'),
    s3: o.stepTeam || (ar ? 'فريق العمل (مستخدم إضافي)' : 'Team (add another user)'),
    s3h: o.stepTeamHint || (ar ? 'وجود مستخدمين اثنين على الأقل في المنشأة' : 'At least two users in the company'),
    s4: o.stepPermissions || (ar ? 'سياسات وصلاحيات' : 'Policies & permissions'),
    s4h: o.stepPermissionsHint || (ar ? 'عرّف قاعدة حوكمة واحدة على الأقل' : 'Define at least one governance rule'),
    s5: o.stepProduct || (ar ? 'منتج أو صنف' : 'Product or SKU'),
    s5h: o.stepProductHint || (ar ? 'أضف أول منتج في الكتالوج' : 'Add your first catalog item'),
    s6: o.stepPrice || (ar ? 'تسعير (خدمة أو منتج)' : 'Pricing (service or product)'),
    s6h: o.stepPriceHint || (ar ? 'حدد سعر بيع لخدمة أو منتج' : 'Set a sale price on a service or product'),
  }
})

const rows = computed(() => {
  const s = props.status
  const o = t.value
  return [
    {
      id: 'company',
      index: 1,
      title: o.s1,
      hint: o.s1h,
      to: { name: 'settings', query: { tab: 'info' } },
      done: !!s?.company_profile_ok,
    },
    {
      id: 'branch',
      index: 2,
      title: o.s2,
      hint: o.s2h,
      to: { name: 'branches' },
      done: !!s?.branch_ok,
    },
    {
      id: 'team',
      index: 3,
      title: o.s3,
      hint: o.s3h,
      to: { name: 'profile' },
      done: !!s?.team_ok,
    },
    {
      id: 'perm',
      index: 4,
      title: o.s4,
      hint: o.s4h,
      to: { name: 'governance' },
      done: !!s?.permissions_ok,
    },
    {
      id: 'product',
      index: 5,
      title: o.s5,
      hint: o.s5h,
      to: { name: 'products' },
      done: !!s?.product_ok,
    },
    {
      id: 'price',
      index: 6,
      title: o.s6,
      hint: o.s6h,
      to: { name: 'services' },
      done: !!s?.has_priced_catalog,
    },
  ]
})
</script>
