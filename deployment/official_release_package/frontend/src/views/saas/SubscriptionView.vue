<template>
  <div class="app-shell-page space-y-8" :dir="langInfo.dir">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">{{ t('subscription.pageTitle') }}</h2>
        <p class="page-subtitle">{{ t('subscription.pageSubtitle') }}</p>
      </div>
    </div>

    <div v-if="loading" class="flex flex-col items-center justify-center py-20 gap-3 rounded-2xl border border-gray-200/80 dark:border-slate-700 bg-white/60 dark:bg-slate-900/40">
      <div class="h-10 w-10 rounded-full border-2 border-primary-500 border-t-transparent animate-spin" />
      <p class="text-sm text-gray-500 dark:text-slate-400">{{ t('common.loading') }}</p>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Current plan -->
        <div
          class="relative overflow-hidden rounded-2xl border border-gray-200/90 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.04]"
        >
          <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-primary-500 via-primary-500 to-primary-400 opacity-90" />
          <div class="p-6 sm:p-7 space-y-5">
            <div class="flex items-start gap-4">
              <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-100 dark:from-primary-900/50 dark:to-primary-900/40 text-primary-700 dark:text-primary-300"
              >
                <CreditCardIcon class="h-6 w-6" />
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-base font-bold text-gray-900 dark:text-slate-100">{{ t('subscription.currentPlan') }}</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">{{ t('subscription.planDetailsHint') }}</p>
              </div>
            </div>

            <div
              class="rounded-2xl border border-primary-100/80 dark:border-primary-900/40 bg-gradient-to-br from-primary-50/90 to-white dark:from-primary-950/30 dark:to-slate-900/80 px-5 py-4 space-y-4"
            >
              <p class="text-2xl sm:text-3xl font-bold text-primary-700 dark:text-primary-300 tracking-tight">
                {{ planDisplayName }}
              </p>
              <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                  <span class="text-gray-500 dark:text-slate-400 block text-xs font-medium uppercase tracking-wide">{{ t('subscription.startDate') }}</span>
                  <p class="font-semibold text-gray-900 dark:text-slate-100 mt-0.5 tabular-nums">{{ formatDate(sub?.starts_at) }}</p>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-slate-400 block text-xs font-medium uppercase tracking-wide">{{ t('subscription.endDate') }}</span>
                  <p class="font-semibold text-gray-900 dark:text-slate-100 mt-0.5 tabular-nums">{{ formatDate(sub?.ends_at) }}</p>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-slate-400 block text-xs font-medium uppercase tracking-wide">{{ t('subscription.status') }}</span>
                  <span
                    class="inline-flex mt-1 items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                    :class="statusBadgeClass"
                  >
                    {{ statusLabel(sub?.status) }}
                  </span>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-slate-400 block text-xs font-medium uppercase tracking-wide">{{ t('subscription.billingCycle') }}</span>
                  <p class="font-semibold text-gray-900 dark:text-slate-100 mt-0.5">
                    {{ sub?.billing_cycle === 'annual' ? t('subscription.billingAnnual') : t('subscription.billingMonthly') }}
                  </p>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="w-full py-3 rounded-xl font-semibold text-sm border-2 border-primary-600 text-primary-700 dark:text-primary-300 dark:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors"
              @click="$router.push('/plans')"
            >
              {{ t('subscription.changePlan') }}
            </button>
          </div>
        </div>

        <!-- Usage -->
        <div
          class="rounded-2xl border border-gray-200/90 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.04] p-6 sm:p-7 space-y-5"
        >
          <div class="flex items-start gap-4">
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300"
            >
              <ChartBarIcon class="h-6 w-6" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-gray-900 dark:text-slate-100">{{ t('subscription.usageLimits') }}</h3>
              <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">{{ t('subscription.usageHint') }}</p>
            </div>
          </div>

          <div class="space-y-4">
            <div v-for="item in usageItems" :key="item.key">
              <div class="flex justify-between gap-3 text-sm mb-1.5">
                <span class="text-gray-600 dark:text-slate-300 font-medium">{{ item.label }}</span>
                <span
                  class="font-semibold tabular-nums shrink-0"
                  :class="item.warn ? 'text-amber-700 dark:text-amber-300' : 'text-gray-800 dark:text-slate-200'"
                >
                  {{ item.used }}/{{ item.maxDisplay }}
                </span>
              </div>
              <div class="h-2.5 bg-gray-100 dark:bg-slate-700/80 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="item.barClass"
                  :style="{ width: item.barPct + '%' }"
                />
              </div>
            </div>
            <p v-if="!usageItems.length" class="text-sm text-gray-500 dark:text-slate-400 py-4 text-center">
              {{ t('common.no_data') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Add-ons (catalog + purchase — POST /subscription/addons) -->
      <div
        id="subscription-addons"
        class="rounded-2xl border border-primary-200/90 dark:border-primary-900/40 bg-gradient-to-bl from-primary-50/80 via-white to-slate-50/90 dark:from-primary-950/25 dark:via-slate-900 dark:to-slate-950 shadow-sm ring-1 ring-primary-100/50 dark:ring-primary-900/30 overflow-hidden"
      >
        <div class="px-6 sm:px-8 py-6 border-b border-primary-100/80 dark:border-primary-900/40 bg-white/60 dark:bg-slate-900/40">
          <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">{{ t('subscription.addonsTitle') }}</h3>
          <p class="text-sm text-gray-600 dark:text-slate-400 mt-1 max-w-3xl leading-relaxed">{{ t('subscription.addonsSubtitle') }}</p>
        </div>
        <div class="p-6 sm:p-8">
          <p v-if="!eligibleCatalogAddons.length" class="text-sm text-gray-500 dark:text-slate-400 text-center py-6">
            {{ t('subscription.addonsEmpty') }}
          </p>
          <ul v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <li
              v-for="addon in eligibleCatalogAddons"
              :key="addon.slug"
              class="flex flex-col rounded-xl border border-gray-200/90 dark:border-slate-700 bg-white dark:bg-slate-800/90 p-5 shadow-sm"
            >
              <div class="flex items-start justify-between gap-2">
                <h4 class="text-sm font-bold text-gray-900 dark:text-slate-100 leading-snug">
                  {{ addonLabel(addon) }}
                </h4>
                <span
                  v-if="isAddonActive(addon.slug)"
                  class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200"
                >
                  {{ t('subscription.addonsActive') }}
                </span>
                <span
                  v-else
                  class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                >
                  {{ t('subscription.addonsEligible') }}
                </span>
              </div>
              <p v-if="addon.description_ar" class="mt-2 text-xs text-gray-600 dark:text-slate-400 leading-relaxed">
                {{ addon.description_ar }}
              </p>
              <div class="mt-3 text-sm font-semibold text-primary-700 dark:text-primary-300 tabular-nums">
                {{ formatMoney(addon.price_monthly) }}
                <span class="text-xs font-normal text-gray-500 dark:text-slate-400"> {{ t('subscription.addonsMonthly') }}</span>
              </div>
              <p class="text-xs text-gray-500 dark:text-slate-500 tabular-nums">{{ formatMoney(addon.price_yearly) }} {{ t('subscription.addonsYearly') }}</p>
              <div class="mt-4 flex flex-wrap gap-2">
                <button
                  v-if="!isAddonActive(addon.slug)"
                  type="button"
                  class="inline-flex flex-1 min-w-[8rem] items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                  :disabled="addonBusySlug === addon.slug"
                  @click="openPurchaseModal(addon)"
                >
                  {{ addonBusySlug === addon.slug ? t('subscription.addonsProcessing') : t('subscription.addonsBuy') }}
                </button>
                <button
                  v-else
                  type="button"
                  class="inline-flex flex-1 min-w-[8rem] items-center justify-center rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-50 dark:border-rose-900/50 dark:bg-slate-900 dark:text-rose-200 dark:hover:bg-rose-950/30 disabled:opacity-50"
                  :disabled="addonBusySlug === addon.slug"
                  @click="confirmRemoveAddon(addon)"
                >
                  {{ addonBusySlug === addon.slug ? t('subscription.addonsProcessing') : t('subscription.addonsRemove') }}
                </button>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Plan features (from API plan.features — not raw limits keys) -->
      <div
        class="rounded-2xl border border-gray-200/90 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.04] overflow-hidden"
      >
        <div class="px-6 sm:px-8 py-6 border-b border-gray-100 dark:border-slate-700/80 bg-gray-50/50 dark:bg-slate-900/50">
          <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">{{ t('subscription.planFeatures') }}</h3>
          <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">{{ t('subscription.planFeaturesHint') }}</p>
        </div>
        <div class="p-6 sm:p-8">
          <ul
            v-if="enabledFeatureKeys.length"
            class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3"
          >
            <li
              v-for="key in enabledFeatureKeys"
              :key="key"
              class="flex items-center gap-3 rounded-xl border border-emerald-100/90 dark:border-emerald-900/40 bg-emerald-50/40 dark:bg-emerald-950/20 px-4 py-3.5"
            >
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                <CheckCircleIcon class="h-5 w-5" />
              </span>
              <span class="text-sm font-medium text-gray-800 dark:text-slate-200 leading-snug">{{ featureTitle(key) }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-500 dark:text-slate-400 text-center py-8">
            {{ t('subscription.noFeatures') }}
          </p>
        </div>
      </div>
    </template>

    <Teleport to="body">
      <div
        v-if="purchaseModalAddon"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4"
        role="dialog"
        aria-modal="true"
        :dir="langInfo.dir"
        @click.self="purchaseModalAddon = null"
      >
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
          <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">{{ t('subscription.addonsConfirmTitle') }}</h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">{{ t('subscription.addonsConfirmIntro') }}</p>
          <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-slate-100">{{ addonLabel(purchaseModalAddon) }}</p>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
            {{ t('subscription.addonsConfirmPrice') }}:
            <span class="font-mono tabular-nums">{{ formatMoney(purchaseModalAddon.price_monthly) }}</span>
          </p>
          <p v-if="checkoutGatewayConfigured" class="mt-3 rounded-lg border border-amber-200/80 bg-amber-50/90 p-3 text-[11px] leading-relaxed text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-100">
            {{ t('subscription.addonsGatewayHint') }}
          </p>
          <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end sm:gap-3">
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
              @click="purchaseModalAddon = null"
            >
              {{ t('subscription.addonsCancel') }}
            </button>
            <button
              v-if="checkoutGatewayConfigured"
              type="button"
              class="rounded-lg border border-primary-300 bg-white px-4 py-2 text-sm font-semibold text-primary-800 hover:bg-primary-50 dark:border-primary-700 dark:bg-slate-900 dark:text-primary-200 dark:hover:bg-primary-950/40"
              @click="onPayAtGateway"
            >
              {{ t('subscription.addonsPayAtGateway') }}
            </button>
            <button
              type="button"
              class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700 disabled:opacity-50"
              :disabled="!!addonBusySlug"
              @click="onConfirmPurchase"
            >
              {{ checkoutGatewayConfigured ? t('subscription.addonsActivateAfterPay') : t('subscription.addonsActivateDirect') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { CreditCardIcon, ChartBarIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'
import { useToast } from '@/composables/useToast'
import { useSubscriptionStore } from '@/stores/subscription'
import { getAddonExternalCheckoutUrl, isAddonCheckoutGatewayConfigured } from '@/composables/useSubscriptionAddonCheckout'

const { t, lang, langInfo } = useLocale()
const toast = useToast()
const subscriptionStore = useSubscriptionStore()
const route = useRoute()

const sub = ref<any>(null)
const plan = ref<any>(null)
const usage = ref<any>(null)
const limits = ref<Record<string, unknown>>({})
const loading = ref(true)
const planCatalogAddons = ref<any[]>([])
const activeAddons = ref<any[]>([])
const purchaseModalAddon = ref<any | null>(null)
const addonBusySlug = ref<string | null>(null)

const checkoutGatewayConfigured = computed(() => isAddonCheckoutGatewayConfigured())

const currentPlanSlug = computed(() => String(plan.value?.slug ?? sub.value?.plan ?? '').trim())

function isAddonEligibleForPlan(addon: Record<string, unknown>): boolean {
  const planSlug = currentPlanSlug.value
  if (!planSlug) return false
  const slugs = addon.eligible_plan_slugs
  if (!Array.isArray(slugs) || slugs.length === 0) return true
  return slugs.some((s) => String(s) === planSlug)
}

const eligibleCatalogAddons = computed(() =>
  planCatalogAddons.value.filter((a) => a && isAddonEligibleForPlan(a as Record<string, unknown>)),
)

function isAddonActive(slug: string): boolean {
  return activeAddons.value.some((a) => a && String(a.slug) === slug)
}

function addonLabel(addon: Record<string, unknown>): string {
  if (lang.value === 'en' && addon.name) return String(addon.name)
  return String(addon.name_ar ?? addon.name ?? addon.slug ?? '')
}

function formatMoney(n: unknown): string {
  const v = Number(n) || 0
  const loc = lang.value === 'en' ? 'en-SA' : 'ar-SA'
  return new Intl.NumberFormat(loc, { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v)
}

function openPurchaseModal(addon: Record<string, unknown>): void {
  purchaseModalAddon.value = addon
}

function onPayAtGateway(): void {
  const a = purchaseModalAddon.value
  if (!a?.slug) return
  const url = getAddonExternalCheckoutUrl(String(a.slug))
  if (url) {
    window.open(url, '_blank', 'noopener,noreferrer')
  }
}

async function onConfirmPurchase(): Promise<void> {
  const a = purchaseModalAddon.value
  if (!a?.slug) return
  addonBusySlug.value = String(a.slug)
  try {
    await apiClient.post('/subscription/addons', { addon_slug: a.slug })
    toast.success(t('subscription.addonsSuccess'))
    purchaseModalAddon.value = null
    await reloadSubscriptionPage()
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? t('subscription.addonsError')
    toast.error(t('subscription.addonsError'), String(msg))
  } finally {
    addonBusySlug.value = null
  }
}

async function confirmRemoveAddon(addon: Record<string, unknown>): Promise<void> {
  if (!addon.slug) return
  if (!window.confirm(t('subscription.addonsRemoveConfirm'))) return
  addonBusySlug.value = String(addon.slug)
  try {
    await apiClient.delete(`/subscription/addons/${encodeURIComponent(String(addon.slug))}`)
    toast.success(t('subscription.addonsSuccess'))
    await reloadSubscriptionPage()
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? t('subscription.addonsError')
    toast.error(t('subscription.addonsError'), String(msg))
  } finally {
    addonBusySlug.value = null
  }
}

async function reloadSubscriptionPage(): Promise<void> {
  const [s, u, p] = await Promise.all([
    apiClient.get('/subscription'),
    apiClient.get('/subscription/usage'),
    apiClient.get('/plans').catch(() => ({ data: {} })),
  ])
  const payload = s.data?.data ?? s.data
  sub.value = payload?.subscription ?? null
  plan.value = payload?.plan ?? null
  activeAddons.value = Array.isArray(payload?.active_addons) ? payload.active_addons : []
  usage.value = u.data?.usage ?? null
  limits.value = (u.data?.limits ?? {}) as Record<string, unknown>
  const body = p.data as Record<string, unknown> | undefined
  const raw = body?.plan_addons
  planCatalogAddons.value = Array.isArray(raw) ? raw : []
  await subscriptionStore.loadSubscription(true)
}

const planDisplayName = computed(() => {
  const p = plan.value
  if (!p && !sub.value?.plan) return '—'
  if (lang.value === 'en') {
    return p?.name || String(sub.value?.plan ?? '—')
  }
  return p?.name_ar || p?.name || String(sub.value?.plan ?? '—')
})

function formatDate(d: string | undefined | null) {
  if (!d) return '—'
  const loc = lang.value === 'en' ? 'en-GB' : 'ar-SA'
  try {
    return new Date(d).toLocaleDateString(loc, { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return '—'
  }
}

function statusLabel(status: string | undefined) {
  if (!status) return '—'
  const s = String(status).toLowerCase()
  if (s === 'active') return t('subscription.statusActive')
  if (s === 'trialing') return t('subscription.statusTrialing')
  if (s === 'past_due') return t('subscription.statusPastDue')
  if (s === 'canceled' || s === 'cancelled') return t('subscription.statusCanceled')
  return t('subscription.statusOther').replace('{status}', status)
}

const statusBadgeClass = computed(() => {
  const s = String(sub.value?.status ?? '').toLowerCase()
  if (s === 'active' || s === 'trialing') {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200'
  }
  if (s === 'past_due') {
    return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200'
  }
  if (s === 'canceled' || s === 'cancelled') {
    return 'bg-gray-200 text-gray-700 dark:bg-slate-600 dark:text-slate-200'
  }
  return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
})

type UsageDef = { key: string; maxKey: string; usedKey: string; labelKey: string }

const USAGE_DEFS: UsageDef[] = [
  { key: 'branches', maxKey: 'max_branches', usedKey: 'branches', labelKey: 'subscription.usage.branches' },
  { key: 'users', maxKey: 'max_users', usedKey: 'users', labelKey: 'subscription.usage.users' },
  { key: 'vehicles', maxKey: 'max_vehicles', usedKey: 'vehicles', labelKey: 'subscription.usage.vehicles' },
  { key: 'products', maxKey: 'max_products', usedKey: 'products', labelKey: 'subscription.usage.products' },
  { key: 'monthly_invoices', maxKey: 'max_monthly_invoices', usedKey: 'monthly_invoices', labelKey: 'subscription.usage.monthlyInvoices' },
]

function isUnlimitedMax(max: number): boolean {
  return max < 0 || max > 500_000
}

const usageItems = computed(() => {
  if (!usage.value || !limits.value || typeof limits.value !== 'object') return []
  const u = usage.value as Record<string, number>
  const l = limits.value as Record<string, unknown>

  return USAGE_DEFS.filter((d) => l[d.maxKey] !== undefined && l[d.maxKey] !== null)
    .map((d) => {
      const rawMax = Number(l[d.maxKey])
      const used = Number(u[d.usedKey] ?? 0)
      const unlimited = isUnlimitedMax(rawMax)
      const maxDisplay = unlimited ? t('subscription.unlimited') : String(rawMax)
      const pct = !unlimited && rawMax > 0 ? Math.round((used / rawMax) * 100) : 0
      const barPct = unlimited ? 100 : Math.min(Math.max(pct, used > 0 ? 6 : 0), 100)
      const warn = !unlimited && pct > 80
      let barClass = 'bg-gradient-to-l from-emerald-500 to-teal-400'
      if (!unlimited && pct > 80) barClass = 'bg-gradient-to-l from-red-500 to-rose-400'
      else if (!unlimited && pct > 50) barClass = 'bg-gradient-to-l from-amber-500 to-yellow-400'
      if (unlimited) barClass = 'bg-gradient-to-l from-sky-400/80 to-primary-400/70'

      return {
        key: d.key,
        label: t(d.labelKey),
        used,
        maxDisplay,
        barPct,
        barClass,
        warn,
      }
    })
})

const enabledFeatureKeys = computed(() => {
  const raw = plan.value?.features
  if (!raw || typeof raw !== 'object') return [] as string[]
  return Object.entries(raw as Record<string, unknown>)
    .filter(([, v]) => v === true)
    .map(([k]) => k)
    .sort((a, b) => featureTitle(a).localeCompare(featureTitle(b), lang.value === 'en' ? 'en' : 'ar'))
})

function featureTitle(key: string): string {
  const path = `subscription.features.${key}`
  const tr = t(path)
  if (tr !== path) return tr
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function scrollToAddonsIfHash(): void {
  if (route.hash !== '#subscription-addons') return
  requestAnimationFrame(() => {
    document.getElementById('subscription-addons')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}

watch(() => route.hash, () => {
  if (!loading.value) scrollToAddonsIfHash()
})

onMounted(async () => {
  loading.value = true
  try {
    const [s, u, p] = await Promise.all([
      apiClient.get('/subscription'),
      apiClient.get('/subscription/usage'),
      apiClient.get('/plans').catch(() => ({ data: {} })),
    ])
    const payload = s.data?.data ?? s.data
    sub.value = payload?.subscription ?? null
    plan.value = payload?.plan ?? null
    activeAddons.value = Array.isArray(payload?.active_addons) ? payload.active_addons : []
    usage.value = u.data?.usage ?? null
    limits.value = (u.data?.limits ?? {}) as Record<string, unknown>
    const body = p.data as Record<string, unknown> | undefined
    const raw = body?.plan_addons
    planCatalogAddons.value = Array.isArray(raw) ? raw : []
    await subscriptionStore.loadSubscription(true)
  } finally {
    loading.value = false
    scrollToAddonsIfHash()
  }
})
</script>
