<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-primary-900/40 to-slate-900" dir="rtl">
    <div class="max-w-screen-2xl mx-auto px-4 py-8 space-y-8">
      <!-- Header -->
      <div class="text-center space-y-3">
        <div class="inline-flex items-center gap-2 rounded-full border border-primary-500/30 bg-primary-500/20 px-4 py-1.5 text-sm font-medium text-primary-200">
          <SparklesIcon class="w-4 h-4" />
          منصة الإضافات الذكية
        </div>
        <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
          سوق الإضافات الذكية
          <span class="bg-gradient-to-r from-primary-300 to-cyan-300 bg-clip-text text-transparent"> AI Plugins</span>
        </h1>
        <p class="text-slate-400 text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
          نقترح ترتيب الإضافات حسب أهميتها لورش وشركات التشغيل السعودية — قوى، التأمينات، التعاقد، التنبيهات، وذكاء تشغيلي.
          للاشتراكات المدفوعة يُضاف <strong class="text-primary-300">٢٠٪</strong> رسوم منصة على مبلغ أداء/اشتراك المزود، ويُعرض الإجمالي بوضوح.
        </p>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div
          v-for="s in stats"
          :key="s.label"
          class="bg-white/5 border border-white/10 rounded-2xl p-4 text-center backdrop-blur"
        >
          <div class="text-2xl font-black text-white tabular-nums">{{ s.value }}</div>
          <div class="text-[11px] text-slate-400 mt-1 leading-snug">{{ s.label }}</div>
        </div>
      </div>

      <!-- Pricing notice -->
      <div
        class="flex flex-wrap items-start gap-3 rounded-2xl border border-primary-500/25 bg-primary-500/10 px-4 py-3 text-sm text-primary-100/95"
      >
        <LightBulbIcon class="w-5 h-5 shrink-0 text-amber-300 mt-0.5" />
        <div class="min-w-0">
          <p class="font-semibold text-white">سياسة التسعير الشفافة</p>
          <p class="mt-1 text-xs leading-relaxed text-primary-200/85">
            «سعر المزود» هو ما يطلبه شريك التشغيل مقابل الاشتراك أو الأداء.
            تضيف المنصة <strong>{{ Math.round(PLATFORM_FEE_RATE * 100) }}٪</strong> لصالحها فيما عدا الإضافات المجانية — لا تُفرض رسوم على
            <span class="whitespace-nowrap">٠ ر.س</span>.
          </p>
        </div>
      </div>

      <!-- Search + Filters -->
      <div class="space-y-3">
        <div class="flex flex-wrap gap-3 items-center">
          <div class="relative flex-1 min-w-[220px]">
            <MagnifyingGlassIcon class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
            <input
              v-model="search"
              placeholder="ابحث بالاسم أو الوصف أو الوسم..."
              class="w-full rounded-xl border border-white/10 bg-white/5 py-2.5 pl-4 pr-10 text-sm text-white placeholder-slate-500 focus:border-primary-500 focus:outline-none"
            />
          </div>
          <select
            v-model="filterTag"
            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-slate-200 focus:border-primary-500 focus:outline-none"
          >
            <option value="">كل الوسوم</option>
            <option value="recommended">موصى بها فقط</option>
            <option value="hr">موارد بشرية</option>
            <option value="qiwa">قوى</option>
            <option value="gosi">تأمينات</option>
            <option value="alerts">تنبيهات</option>
          </select>
          <button
            type="button"
            :class="showInstalled ? 'bg-green-600 text-white' : 'bg-white/5 text-slate-300 border border-white/10'"
            class="px-4 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center gap-1.5"
            @click="showInstalled = !showInstalled"
          >
            <CheckCircleIcon class="w-4 h-4" />
            المثبتة فقط
          </button>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="cat in categories"
            :key="cat.key"
            type="button"
            :class="
              activeCategory === cat.key
                ? 'border-primary-500 bg-primary-600 text-white'
                : 'border-white/10 bg-white/5 text-slate-300 hover:border-primary-500/50'
            "
            class="px-4 py-2 rounded-xl border text-sm font-medium transition-all"
            @click="activeCategory = cat.key"
          >
            {{ cat.label }}
          </button>
        </div>
      </div>

      <!-- Recommended strip -->
      <section v-if="!loading && recommendedPreview.length" class="space-y-2">
        <h2 class="text-sm font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-5 rounded-full bg-amber-400" />
          يُنصح بالبدء بهذه الإضافات
        </h2>
        <p class="text-xs text-slate-500">مرتبة حسب اولوية المنصة لعملائك في السعودية — يمكنك تغيير الفلترة أعلاه.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <PluginCard
            v-for="p in recommendedPreview"
            :key="'rec-' + p.plugin_key"
            :plugin="p"
            @install="installPlugin"
            @uninstall="uninstallPlugin"
            @details="onOpenPluginDetails"
            @configure="openConfig"
          />
        </div>
      </section>

      <!-- Loading -->
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="i in 8" :key="i" class="bg-white/5 border border-white/10 rounded-2xl p-5 animate-pulse">
          <div class="h-12 w-12 bg-white/10 rounded-xl mb-4" />
          <div class="h-4 bg-white/10 rounded mb-2 w-3/4" />
          <div class="h-3 bg-white/10 rounded mb-1 w-full" />
          <div class="h-3 bg-white/10 rounded w-2/3" />
        </div>
      </div>

      <!-- All plugins -->
      <section v-else-if="displayedPlugins.length" class="space-y-3">
        <h2 v-if="recommendedPreview.length" class="text-sm font-bold text-slate-400">باقي الإضافات</h2>
        <h2 v-else class="text-sm font-bold text-slate-400">جميع الإضافات المتاحة</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          <PluginCard
            v-for="p in displayedPlugins"
            :key="p.plugin_key"
            :plugin="p"
            @install="installPlugin"
            @uninstall="uninstallPlugin"
            @details="onOpenPluginDetails"
            @configure="openConfig"
          />
        </div>
      </section>

      <div v-else-if="!loading" class="text-center py-16 text-slate-500">
        <CubeTransparentIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
        <p>لا توجد إضافات تطابق البحث</p>
        <p class="text-xs mt-2 text-slate-600 max-w-md mx-auto">
          إذا ظهرت القائمة فارغة بالكامل، شغّل على الخادم:
          <code class="text-slate-400 bg-white/5 px-1 rounded">php artisan migrate</code>
          ثم
          <code class="text-slate-400 bg-white/5 px-1 rounded">php artisan db:seed --class=PluginsCatalogSeeder</code>
        </p>
      </div>

      <footer class="text-center text-[11px] text-slate-600 pt-6 border-t border-white/5">
        الإضافات للقراءة والتفعيل داخل اشتراككم — التنفيذ الفعلي لبعض التكاملات يعتمد على عقود الأطراف الخارجية.
      </footer>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="selectedPlugin"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
          @click.self="selectedPlugin = null"
        >
          <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto" dir="rtl">
            <div class="p-6 border-b border-white/10 flex items-center justify-between sticky top-0 bg-slate-900 z-10">
              <div class="flex items-center gap-3 min-w-0">
                <div :class="categoryColor(selectedPlugin.category)" class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0">
                  <component :is="pluginIcon(selectedPlugin.icon)" class="w-6 h-6 text-white" />
                </div>
                <div class="min-w-0">
                  <h3 class="text-white font-bold text-lg leading-snug">{{ selectedPlugin.name_ar }}</h3>
                  <span class="text-xs text-slate-400">v{{ selectedPlugin.version }} · {{ selectedPlugin.author }}</span>
                </div>
              </div>
              <button type="button" class="text-slate-400 hover:text-white p-1 shrink-0" @click="selectedPlugin = null">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <p class="text-slate-300 text-sm leading-relaxed whitespace-pre-line">{{ selectedPlugin.description_ar }}</p>

              <div v-if="detailPricing.isFree" class="rounded-xl bg-green-500/10 border border-green-500/25 p-4 text-center">
                <p class="text-green-400 font-bold">إضافة مجانية</p>
                <p class="text-xs text-slate-400 mt-1">لا رسوم مزود ولا رسوم منصة.</p>
              </div>
              <div v-else class="rounded-xl bg-white/5 border border-white/10 p-4 space-y-2 text-sm">
                <p class="text-xs font-semibold text-slate-400">السعر الشهري (تقديري)</p>
                <div class="flex justify-between text-slate-300">
                  <span>أداء / اشتراك المزود</span>
                  <span class="font-mono tabular-nums">{{ formatSar(detailPricing.supplierMonthly) }}</span>
                </div>
                <div class="flex justify-between text-amber-200/90">
                  <span>رسوم المنصة ({{ Math.round(PLATFORM_FEE_RATE * 100) }}٪)</span>
                  <span class="font-mono tabular-nums">+ {{ formatSar(detailPricing.platformFee) }}</span>
                </div>
                <div class="flex justify-between text-white font-bold pt-2 border-t border-white/10">
                  <span>الإجمالي شهرياً</span>
                  <span class="tabular-nums">{{ formatSar(detailPricing.totalMonthly) }}</span>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-white/5 rounded-xl p-3">
                  <div class="text-slate-400 text-xs mb-1">التقييم</div>
                  <div class="text-yellow-400 font-bold">⭐ {{ Number(selectedPlugin.rating).toFixed(1) }}</div>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                  <div class="text-slate-400 text-xs mb-1">المثبتون (تقريبي)</div>
                  <div class="text-white font-bold">{{ selectedPlugin.install_count }}+</div>
                </div>
                <div class="bg-white/5 rounded-xl p-3 col-span-2">
                  <div class="text-slate-400 text-xs mb-1">الحالة عندكم</div>
                  <div :class="selectedPlugin.is_installed ? 'text-green-400' : 'text-slate-400'" class="font-bold">
                    {{ selectedPlugin.is_installed ? '✓ مثبتة' : 'غير مثبتة' }}
                  </div>
                </div>
              </div>
              <div v-if="selectedPlugin.module_scope?.length" class="space-y-1">
                <div class="text-xs text-slate-400">نطاق الوحدات:</div>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="s in selectedPlugin.module_scope"
                    :key="s"
                    class="rounded-full border border-primary-500/30 bg-primary-500/20 px-2.5 py-0.5 text-xs text-primary-300"
                  >{{ s }}</span>
                </div>
              </div>
              <div v-if="selectedPlugin.hooks?.length" class="space-y-1">
                <div class="text-xs text-slate-400">نقاط الربط:</div>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="h in selectedPlugin.hooks"
                    :key="h"
                    class="px-2 py-0.5 bg-blue-500/20 border border-blue-500/30 rounded-full text-blue-300 text-xs font-mono"
                  >{{ h }}</span>
                </div>
              </div>
            </div>
            <div class="p-4 border-t border-white/10 flex gap-2 sticky bottom-0 bg-slate-900">
              <button
                v-if="!selectedPlugin.is_installed"
                type="button"
                :disabled="installing === selectedPlugin.plugin_key"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 disabled:opacity-50"
                @click="installPlugin(selectedPlugin); selectedPlugin = null"
              >
                <ArrowDownTrayIcon class="w-4 h-4" />
                تثبيت
              </button>
              <button
                v-else
                type="button"
                class="flex-1 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 rounded-xl text-red-400 font-semibold text-sm"
                @click="uninstallPlugin(selectedPlugin); selectedPlugin = null"
              >
                إلغاء التثبيت
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Config Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="configPlugin"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
          @click.self="configPlugin = null"
        >
          <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-md shadow-2xl" dir="rtl">
            <div class="p-5 border-b border-white/10 flex items-center justify-between">
              <h3 class="text-white font-bold">إعدادات: {{ configPlugin.name_ar }}</h3>
              <button type="button" @click="configPlugin = null"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
            </div>
            <div class="p-5 space-y-4">
              <p class="text-slate-400 text-sm">تخصيص سلوك الإضافة للشركة الحالية.</p>
              <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                <label class="text-xs text-slate-400 block mb-2">تفعيل التنبيهات التلقائية</label>
                <input v-model="configForm.auto_alerts" type="checkbox" class="rounded" />
              </div>
              <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                <label class="text-xs text-slate-400 block mb-2">حد الثقة الأدنى (%)</label>
                <input v-model.number="configForm.min_confidence" type="range" min="50" max="95" class="w-full accent-primary-500" />
                <div class="text-white text-sm text-center mt-1">{{ configForm.min_confidence }}%</div>
              </div>
            </div>
            <div class="p-4 border-t border-white/10 flex gap-2">
              <button type="button" class="flex-1 rounded-xl bg-primary-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700" @click="saveConfig">
                حفظ
              </button>
              <button type="button" class="px-4 py-2.5 bg-white/5 rounded-xl text-slate-300 text-sm" @click="configPlugin = null">إلغاء</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'

defineOptions({ name: 'PluginMarketplaceView' })
import {
  SparklesIcon,
  MagnifyingGlassIcon,
  CheckCircleIcon,
  XMarkIcon,
  ArrowDownTrayIcon,
  CubeTransparentIcon,
  CpuChipIcon,
  CurrencyDollarIcon,
  ShieldCheckIcon,
  ChatBubbleLeftRightIcon,
  CubeIcon,
  ChartBarIcon,
  FaceSmileIcon,
  BoltIcon,
  LightBulbIcon,
  BuildingOffice2Icon,
  DocumentTextIcon,
  BellAlertIcon,
  BanknotesIcon,
  DocumentCheckIcon,
  ShieldExclamationIcon,
  Squares2X2Icon,
  DevicePhoneMobileIcon,
  MapIcon,
  PaintBrushIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useRoute } from 'vue-router'
import PluginCard from '@/components/PluginCard.vue'
import { monthlyPricingWithPlatform, formatSar, PLATFORM_FEE_RATE } from '@/utils/pluginPricing'

interface Plugin {
  plugin_key: string
  name: string
  name_ar: string
  description_ar: string
  version: string
  author: string
  category: string
  icon: string
  module_scope: string[]
  hooks: string[]
  supported_plans: string[]
  is_active: boolean
  is_installed: boolean
  is_premium: boolean
  price_monthly: number
  install_count: number
  rating: number
  recommended_rank?: number
  tags?: string[]
  tenant_config?: Record<string, any>
}

const route = useRoute()
const plugins = ref<Plugin[]>([])
const loading = ref(true)
const search = ref('')
const activeCategory = ref('all')
const filterTag = ref('')
const showInstalled = ref(false)
const selectedPlugin = ref<Plugin | null>(null)
const configPlugin = ref<Plugin | null>(null)
const installing = ref<string | null>(null)
const configForm = reactive({ auto_alerts: true, min_confidence: 75 })

const categories = [
  { key: 'all', label: 'الكل' },
  { key: 'ai', label: '🧠 ذكاء اصطناعي' },
  { key: 'integration', label: '🔌 تكاملات' },
  { key: 'analytics', label: '📊 تحليلات' },
  { key: 'ui', label: '🎨 واجهة' },
]

const stats = computed(() => [
  { label: 'إجمالي الإضافات', value: plugins.value.length },
  { label: 'مثبتة', value: plugins.value.filter((p) => p.is_installed).length },
  { label: 'مجانية', value: plugins.value.filter((p) => !p.is_premium || Number(p.price_monthly) === 0).length },
  { label: 'المدفوعة', value: plugins.value.filter((p) => p.is_premium && Number(p.price_monthly) > 0).length },
])

function matchFilters(p: Plugin): boolean {
  const q = search.value.trim().toLowerCase()
  const tags = (p.tags ?? []).map((t) => t.toLowerCase())
  const matchSearch =
    !q ||
    p.name_ar?.toLowerCase().includes(q) ||
    p.description_ar?.toLowerCase().includes(q) ||
    p.name?.toLowerCase().includes(q) ||
    tags.some((t) => t.includes(q))
  const matchCategory = activeCategory.value === 'all' || p.category === activeCategory.value
  const matchInstall = !showInstalled.value || p.is_installed
  let matchTag = true
  if (filterTag.value === 'recommended') matchTag = tags.includes('recommended')
  else if (filterTag.value) matchTag = tags.includes(filterTag.value)
  return matchSearch && matchCategory && matchInstall && matchTag
}

const baseFiltered = computed(() => plugins.value.filter(matchFilters))

const sortedList = computed(() => {
  return [...baseFiltered.value].sort((a, b) => {
    const ra = Number(a.recommended_rank ?? 100)
    const rb = Number(b.recommended_rank ?? 100)
    if (ra !== rb) return ra - rb
    return (a.name_ar || '').localeCompare(b.name_ar || '', 'ar')
  })
})

/** أول صف «موصى» — يُستثنى من الشبكة السفلى لتفادي التكرار */
const recommendedPreview = computed(() => sortedList.value.filter((p) => Number(p.recommended_rank ?? 100) <= 6))

const recommendedKeys = computed(() => new Set(recommendedPreview.value.map((p) => p.plugin_key)))

const displayedPlugins = computed(() => sortedList.value.filter((p) => !recommendedKeys.value.has(p.plugin_key)))

const detailPricing = computed(() => monthlyPricingWithPlatform(selectedPlugin.value?.price_monthly ?? 0))

function pluginIcon(icon: string) {
  const map: Record<string, any> = {
    'cpu-chip': CpuChipIcon,
    'currency-dollar': CurrencyDollarIcon,
    'shield-check': ShieldCheckIcon,
    'chat-bubble-left-right': ChatBubbleLeftRightIcon,
    cube: CubeIcon,
    'chart-bar': ChartBarIcon,
    'face-smile': FaceSmileIcon,
    'chat-bubble-oval-left': ChatBubbleLeftRightIcon,
    'building-office-2': BuildingOffice2Icon,
    'document-text': DocumentTextIcon,
    'bell-alert': BellAlertIcon,
    banknotes: BanknotesIcon,
    'document-check': DocumentCheckIcon,
    'shield-exclamation': ShieldExclamationIcon,
    'squares-2x2': Squares2X2Icon,
    'device-phone-mobile': DevicePhoneMobileIcon,
    map: MapIcon,
    'paint-brush': PaintBrushIcon,
  }
  return map[icon] ?? BoltIcon
}

function categoryColor(cat: string) {
  const colors: Record<string, string> = {
    ai: 'bg-gradient-to-br from-primary-600 to-primary-800',
    integration: 'bg-gradient-to-br from-blue-600 to-blue-800',
    analytics: 'bg-gradient-to-br from-green-600 to-green-800',
    ui: 'bg-gradient-to-br from-pink-600 to-pink-800',
  }
  return colors[cat] ?? 'bg-gradient-to-br from-slate-600 to-slate-800'
}

async function loadPlugins() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/plugins')
    const list = (data?.data ?? []) as Plugin[]
    plugins.value = list.map((p: any) => ({
      ...p,
      is_installed: Boolean(p.is_installed),
      recommended_rank: p.recommended_rank ?? 100,
      tags: Array.isArray(p.tags) ? p.tags : [],
    }))
  } catch {
    plugins.value = []
  } finally {
    loading.value = false
  }
}

async function installPlugin(p: Plugin) {
  installing.value = p.plugin_key
  try {
    await apiClient.post(`/plugins/${p.plugin_key}/install`)
    p.is_installed = true
    p.install_count = (p.install_count ?? 0) + 1
  } catch {
    /* optional toast */
  } finally {
    installing.value = null
  }
}

/** فتح التفاصيل — وإذا كانت الإضافة مجانية تُفعَّل فوراً دون خطوة إضافية */
async function onOpenPluginDetails(p: Plugin) {
  selectedPlugin.value = p
  const breakdown = monthlyPricingWithPlatform(p.price_monthly)
  if (breakdown.isFree && !p.is_installed) {
    await installPlugin(p)
  }
}

async function uninstallPlugin(p: Plugin) {
  try {
    await apiClient.delete(`/plugins/${p.plugin_key}/uninstall`)
    p.is_installed = false
  } catch {
    /* */
  }
}

function openConfig(p: Plugin) {
  configPlugin.value = p
  Object.assign(configForm, p.tenant_config ?? { auto_alerts: true, min_confidence: 75 })
}

async function saveConfig() {
  if (!configPlugin.value) return
  try {
    await apiClient.put(`/plugins/${configPlugin.value.plugin_key}/configure`, { config: { ...configForm } })
    configPlugin.value = null
  } catch {
    /* */
  }
}

onMounted(() => {
  const t = route.query.tag
  if (typeof t === 'string' && t.trim()) filterTag.value = t.trim()
  void loadPlugins()
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
