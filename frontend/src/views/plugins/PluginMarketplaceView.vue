<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900" dir="rtl">
    <div class="max-w-screen-2xl mx-auto px-4 py-8 space-y-8">

      <!-- Header -->
      <div class="text-center space-y-3">
        <div class="inline-flex items-center gap-2 bg-purple-500/20 border border-purple-500/30 rounded-full px-4 py-1.5 text-purple-300 text-sm font-medium">
          <SparklesIcon class="w-4 h-4" />
          منصة الإضافات الذكية
        </div>
        <h1 class="text-4xl font-black text-white tracking-tight">
          سوق الإضافات الذكية
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400"> AI Plugins</span>
        </h1>
        <p class="text-slate-400 text-lg max-w-2xl mx-auto">
          وسّع قدرات OSAS بإضافات ذكاء اصطناعي متخصصة — فعّل ما تحتاجه، أوقف ما لا تحتاجه
        </p>
      </div>

      <!-- Stats Bar -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div v-for="s in stats" :key="s.label" class="bg-white/5 border border-white/10 rounded-2xl p-4 text-center backdrop-blur">
          <div class="text-2xl font-black text-white">{{ s.value }}</div>
          <div class="text-xs text-slate-400 mt-1">{{ s.label }}</div>
        </div>
      </div>

      <!-- Search + Filters -->
      <div class="flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-60">
          <MagnifyingGlassIcon class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="search" placeholder="ابحث عن إضافة..." class="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 pr-10 pl-4 text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 text-sm" />
        </div>
        <div class="flex gap-2 flex-wrap">
          <button v-for="cat in categories" :key="cat.key"
            @click="activeCategory = cat.key"
            :class="activeCategory === cat.key ? 'bg-purple-600 text-white border-purple-500' : 'bg-white/5 text-slate-300 border-white/10 hover:border-purple-500/50'"
            class="px-4 py-2 rounded-xl border text-sm font-medium transition-all">
            {{ cat.label }}
          </button>
        </div>
        <div class="flex gap-2">
          <button @click="showInstalled = !showInstalled"
            :class="showInstalled ? 'bg-green-600 text-white' : 'bg-white/5 text-slate-300 border border-white/10'"
            class="px-4 py-2 rounded-xl text-sm font-medium transition-all flex items-center gap-1.5">
            <CheckCircleIcon class="w-4 h-4" />
            المثبتة فقط
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="i in 8" :key="i" class="bg-white/5 border border-white/10 rounded-2xl p-5 animate-pulse">
          <div class="h-12 w-12 bg-white/10 rounded-xl mb-4"></div>
          <div class="h-4 bg-white/10 rounded mb-2 w-3/4"></div>
          <div class="h-3 bg-white/10 rounded mb-1 w-full"></div>
          <div class="h-3 bg-white/10 rounded w-2/3"></div>
        </div>
      </div>

      <!-- Plugin Grid -->
      <div v-else-if="filtered.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <PluginCard
          v-for="p in filtered" :key="p.plugin_key"
          :plugin="p"
          @install="installPlugin"
          @uninstall="uninstallPlugin"
          @details="selectedPlugin = p"
          @configure="openConfig"
        />
      </div>

      <div v-else class="text-center py-16 text-slate-500">
        <CubeTransparentIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
        <p>لا توجد إضافات تطابق البحث</p>
      </div>

    </div>

    <!-- Plugin Detail Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="selectedPlugin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="selectedPlugin = null">
          <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl" dir="rtl">
            <div class="p-6 border-b border-white/10 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div :class="categoryColor(selectedPlugin.category)" class="w-12 h-12 rounded-xl flex items-center justify-center">
                  <component :is="pluginIcon(selectedPlugin.icon)" class="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 class="text-white font-bold text-lg">{{ selectedPlugin.name_ar }}</h3>
                  <span class="text-xs text-slate-400">v{{ selectedPlugin.version }} • {{ selectedPlugin.author }}</span>
                </div>
              </div>
              <button @click="selectedPlugin = null" class="text-slate-400 hover:text-white p-1">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <p class="text-slate-300 text-sm leading-relaxed">{{ selectedPlugin.description_ar }}</p>
              <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-white/5 rounded-xl p-3">
                  <div class="text-slate-400 text-xs mb-1">التقييم</div>
                  <div class="text-yellow-400 font-bold">⭐ {{ selectedPlugin.rating }}</div>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                  <div class="text-slate-400 text-xs mb-1">المثبتون</div>
                  <div class="text-white font-bold">{{ selectedPlugin.install_count }}+</div>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                  <div class="text-slate-400 text-xs mb-1">السعر</div>
                  <div :class="selectedPlugin.price_monthly > 0 ? 'text-pink-400' : 'text-green-400'" class="font-bold">
                    {{ selectedPlugin.price_monthly > 0 ? selectedPlugin.price_monthly + ' ﷼/شهر' : 'مجاني' }}
                  </div>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                  <div class="text-slate-400 text-xs mb-1">الحالة</div>
                  <div :class="selectedPlugin.is_installed ? 'text-green-400' : 'text-slate-400'" class="font-bold">
                    {{ selectedPlugin.is_installed ? '✅ مثبتة' : '⬜ غير مثبتة' }}
                  </div>
                </div>
              </div>
              <div v-if="selectedPlugin.module_scope?.length" class="space-y-1">
                <div class="text-xs text-slate-400">نطاق العمل:</div>
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="s in selectedPlugin.module_scope" :key="s"
                    class="px-2.5 py-0.5 bg-purple-500/20 border border-purple-500/30 rounded-full text-purple-300 text-xs">{{ s }}</span>
                </div>
              </div>
              <div v-if="selectedPlugin.hooks?.length" class="space-y-1">
                <div class="text-xs text-slate-400">أحداث التشغيل:</div>
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="h in selectedPlugin.hooks" :key="h"
                    class="px-2 py-0.5 bg-blue-500/20 border border-blue-500/30 rounded-full text-blue-300 text-xs font-mono">{{ h }}</span>
                </div>
              </div>
            </div>
            <div class="p-4 border-t border-white/10 flex gap-2">
              <button v-if="!selectedPlugin.is_installed"
                @click="installPlugin(selectedPlugin); selectedPlugin = null"
                :disabled="installing === selectedPlugin.plugin_key"
                class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 rounded-xl text-white font-semibold text-sm transition-colors flex items-center justify-center gap-2">
                <ArrowDownTrayIcon class="w-4 h-4" />
                تثبيت الإضافة
              </button>
              <button v-else
                @click="uninstallPlugin(selectedPlugin); selectedPlugin = null"
                class="flex-1 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 rounded-xl text-red-400 font-semibold text-sm transition-colors">
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
        <div v-if="configPlugin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="configPlugin = null">
          <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-md shadow-2xl" dir="rtl">
            <div class="p-5 border-b border-white/10 flex items-center justify-between">
              <h3 class="text-white font-bold">إعدادات: {{ configPlugin.name_ar }}</h3>
              <button @click="configPlugin = null"><XMarkIcon class="w-5 h-5 text-slate-400" /></button>
            </div>
            <div class="p-5 space-y-4">
              <p class="text-slate-400 text-sm">يمكنك تخصيص سلوك هذه الإضافة من هنا.</p>
              <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                <label class="text-xs text-slate-400 block mb-2">تفعيل التنبيهات التلقائية</label>
                <input type="checkbox" class="rounded" v-model="configForm.auto_alerts" />
              </div>
              <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                <label class="text-xs text-slate-400 block mb-2">حد الثقة الأدنى (%)</label>
                <input type="range" min="50" max="95" v-model="configForm.min_confidence" class="w-full accent-purple-500" />
                <div class="text-white text-sm text-center mt-1">{{ configForm.min_confidence }}%</div>
              </div>
            </div>
            <div class="p-4 border-t border-white/10 flex gap-2">
              <button @click="saveConfig" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 rounded-xl text-white font-semibold text-sm">حفظ الإعدادات</button>
              <button @click="configPlugin = null" class="px-4 py-2.5 bg-white/5 hover:bg-white/10 rounded-xl text-slate-300 text-sm">إلغاء</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import {
  SparklesIcon, MagnifyingGlassIcon, CheckCircleIcon, XMarkIcon,
  ArrowDownTrayIcon, CubeTransparentIcon,
  CpuChipIcon, CurrencyDollarIcon, ShieldCheckIcon, ChatBubbleLeftRightIcon,
  CubeIcon, ChartBarIcon, FaceSmileIcon, BoltIcon
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import PluginCard from '@/components/PluginCard.vue'

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
  tenant_config?: Record<string, any>
}

const plugins    = ref<Plugin[]>([])
const loading    = ref(true)
const search     = ref('')
const activeCategory = ref('all')
const showInstalled  = ref(false)
const selectedPlugin = ref<Plugin | null>(null)
const configPlugin   = ref<Plugin | null>(null)
const installing = ref<string | null>(null)
const configForm = reactive({ auto_alerts: true, min_confidence: 75 })

const categories = [
  { key: 'all',         label: 'الكل' },
  { key: 'ai',          label: '🧠 ذكاء اصطناعي' },
  { key: 'integration', label: '🔌 تكاملات' },
  { key: 'analytics',   label: '📊 تحليلات' },
  { key: 'ui',          label: '🎨 واجهة' },
]

const stats = computed(() => [
  { label: 'إجمالي الإضافات', value: plugins.value.length },
  { label: 'مثبتة', value: plugins.value.filter(p => p.is_installed).length },
  { label: 'مجانية', value: plugins.value.filter(p => !p.is_premium).length },
  { label: 'متميزة', value: plugins.value.filter(p => p.is_premium).length },
])

const filtered = computed(() => plugins.value.filter(p => {
  const matchSearch   = !search.value || p.name_ar.includes(search.value) || p.description_ar?.includes(search.value)
  const matchCategory = activeCategory.value === 'all' || p.category === activeCategory.value
  const matchInstall  = !showInstalled.value || p.is_installed
  return matchSearch && matchCategory && matchInstall
}))

function pluginIcon(icon: string) {
  const map: Record<string, any> = {
    'cpu-chip': CpuChipIcon,
    'currency-dollar': CurrencyDollarIcon,
    'shield-check': ShieldCheckIcon,
    'chat-bubble-left-right': ChatBubbleLeftRightIcon,
    'cube': CubeIcon,
    'chart-bar': ChartBarIcon,
    'face-smile': FaceSmileIcon,
    'chat-bubble-oval-left': ChatBubbleLeftRightIcon,
  }
  return map[icon] ?? BoltIcon
}

function categoryColor(cat: string) {
  const colors: Record<string, string> = {
    ai:          'bg-gradient-to-br from-purple-600 to-purple-800',
    integration: 'bg-gradient-to-br from-blue-600 to-blue-800',
    analytics:   'bg-gradient-to-br from-green-600 to-green-800',
    ui:          'bg-gradient-to-br from-pink-600 to-pink-800',
  }
  return colors[cat] ?? 'bg-gradient-to-br from-slate-600 to-slate-800'
}

async function loadPlugins() {
  try {
    const { data } = await apiClient.get('/plugins')
    plugins.value = data.data ?? []
  } catch { plugins.value = [] }
  finally { loading.value = false }
}

async function installPlugin(p: Plugin) {
  installing.value = p.plugin_key
  try {
    await apiClient.post(`/plugins/${p.plugin_key}/install`)
    p.is_installed = true
    p.install_count++
  } catch { /* ignore */ }
  finally { installing.value = null }
}

async function uninstallPlugin(p: Plugin) {
  try {
    await apiClient.delete(`/plugins/${p.plugin_key}/uninstall`)
    p.is_installed = false
  } catch { /* ignore */ }
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
  } catch { /* ignore */ }
}

onMounted(loadPlugins)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
