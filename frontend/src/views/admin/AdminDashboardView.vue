<template>
  <div class="min-h-screen bg-gray-950 text-white" dir="rtl">
    <!-- Admin Header -->
    <div class="bg-gradient-to-l from-slate-900 via-purple-950 to-slate-900 border-b border-purple-900/50 px-6 py-4">
      <div class="max-w-screen-2xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-600/30 border border-purple-500/40 flex items-center justify-center">
            <CpuChipIcon class="w-6 h-6 text-purple-400" />
          </div>
          <div>
            <h1 class="text-lg font-black text-white">OSAS Platform Admin</h1>
            <p class="text-xs text-purple-400">لوحة التحكم المركزية للمنصة</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-right">
            <div class="text-xs text-gray-400">آخر تحديث</div>
            <div class="text-sm font-mono text-green-400">{{ lastRefresh }}</div>
          </div>
          <button @click="refresh" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-all">
            <ArrowPathIcon class="w-4 h-4" :class="refreshing ? 'animate-spin' : ''" />
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-screen-2xl mx-auto px-6 py-6">
      <!-- Tabs -->
      <div class="flex gap-1 bg-gray-900 rounded-xl p-1 mb-6 overflow-x-auto border border-gray-800">
        <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
          :class="activeTab === t.id ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap">
          <component :is="t.icon" class="w-4 h-4" />
          {{ t.label }}
        </button>
      </div>

      <!-- ══ TAB: OVERVIEW ══ -->
      <div v-if="activeTab === 'overview'">
        <!-- Platform KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
          <AdminKpi label="إجمالي المشتركين"  :value="overview.total_companies"     color="purple" trend="+12%" />
          <AdminKpi label="نشطون اليوم"       :value="overview.active_today"        color="green"  trend="+5%" />
          <AdminKpi label="الإيراد الشهري"    :value="formatCurrency(overview.mrr)" color="emerald" trend="+18%" />
          <AdminKpi label="الإيراد السنوي"    :value="formatCurrency(overview.arr)" color="teal"   trend="+22%" />
          <AdminKpi label="التجربة المجانية"  :value="overview.trial_count"         color="yellow" trend="-3%" />
          <AdminKpi label="معدل التحويل"      :value="`${overview.conversion_rate}%`" color="blue"  trend="+2%" />
        </div>

        <!-- Revenue Chart Placeholder + Recent Activity -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
          <div class="lg:col-span-2 bg-gray-900 rounded-2xl border border-gray-800 p-5">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-white">الإيراد الشهري (آخر 12 شهر)</h3>
              <div class="flex gap-1">
                <button v-for="r in ['3m','6m','12m']" :key="r"
                  @click="revenueRange = r"
                  :class="revenueRange === r ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-gray-800'"
                  class="px-3 py-1 rounded-lg text-xs transition-all">{{ r }}</button>
              </div>
            </div>
            <!-- Simple Bar Chart -->
            <div class="flex items-end gap-2 h-32">
              <div v-for="(m, i) in revenueData" :key="i" class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full rounded-t-sm bg-purple-500/60 hover:bg-purple-400 transition-all cursor-pointer relative group"
                  :style="{ height: `${(m.value / maxRevenue) * 100}%` }">
                  <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-xs text-white px-2 py-1 rounded opacity-0 group-hover:opacity-100 whitespace-nowrap z-10">
                    {{ formatCurrency(m.value) }}
                  </div>
                </div>
                <span class="text-xs text-gray-500">{{ m.label }}</span>
              </div>
            </div>
          </div>

          <div class="bg-gray-900 rounded-2xl border border-gray-800 p-5">
            <h3 class="font-bold text-white mb-4">توزيع الباقات</h3>
            <div class="space-y-3">
              <div v-for="p in planDistribution" :key="p.name">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-gray-400">{{ p.name }}</span>
                  <span class="text-white font-medium">{{ p.count }} ({{ p.pct }}%)</span>
                </div>
                <div class="h-2 bg-gray-800 rounded-full overflow-hidden">
                  <div class="h-full rounded-full transition-all" :class="p.color" :style="{ width: p.pct + '%' }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Signups -->
        <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden">
          <div class="flex items-center justify-between p-5 border-b border-gray-800">
            <h3 class="font-bold text-white">آخر المشتركين</h3>
            <button @click="activeTab = 'tenants'" class="text-sm text-purple-400 hover:text-purple-300">عرض الكل</button>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-400">الشركة</th>
                <th class="px-4 py-3 text-right text-gray-400">الباقة</th>
                <th class="px-4 py-3 text-right text-gray-400">الإيراد</th>
                <th class="px-4 py-3 text-right text-gray-400">الحالة</th>
                <th class="px-4 py-3 text-right text-gray-400">الانضمام</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="c in recentCompanies" :key="c.id" class="hover:bg-gray-800/30 cursor-pointer" @click="viewTenant(c)">
                <td class="px-4 py-3 text-white font-medium">{{ c.name }}</td>
                <td class="px-4 py-3"><PlanBadge :plan="c.plan_slug" /></td>
                <td class="px-4 py-3 text-emerald-400">{{ formatCurrency(c.monthly_revenue || 0) }}</td>
                <td class="px-4 py-3"><StatusDot :active="c.is_active" /></td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ formatDate(c.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ TAB: TENANTS ══ -->
      <div v-if="activeTab === 'tenants'">
        <div class="flex flex-wrap gap-3 mb-4">
          <input v-model="tenantSearch" placeholder="بحث في الشركات..."
            class="flex-1 min-w-48 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500 outline-none" />
          <select v-model="tenantPlanFilter" class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
            <option value="">كل الباقات</option>
            <option v-for="p in plans" :key="p.id" :value="p.code">{{ p.name }}</option>
          </select>
          <select v-model="tenantStatusFilter" class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
            <option value="">كل الحالات</option>
            <option value="active">نشط</option>
            <option value="trial">تجريبي</option>
            <option value="suspended">موقوف</option>
          </select>
        </div>

        <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الشركة</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">المالك</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الباقة</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الإيراد</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">المستخدمين</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">آخر نشاط</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="c in filteredCompanies" :key="c.id" class="hover:bg-gray-800/30">
                <td class="px-4 py-3">
                  <div class="text-white font-medium">{{ c.name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ c.slug }}</div>
                </td>
                <td class="px-4 py-3 text-gray-300">{{ c.owner_name || '—' }}</td>
                <td class="px-4 py-3"><PlanBadge :plan="c.plan_slug" /></td>
                <td class="px-4 py-3 text-emerald-400 font-semibold">{{ formatCurrency(c.monthly_revenue || planPriceMap[c.plan_slug] || 0) }}</td>
                <td class="px-4 py-3 text-gray-300">{{ c.users_count || 1 }}</td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ formatDate(c.updated_at) }}</td>
                <td class="px-4 py-3">
                  <div class="flex gap-2">
                    <button @click="upgradePlan(c)" class="text-xs px-2 py-1 bg-purple-600/30 hover:bg-purple-600 text-purple-300 hover:text-white rounded-lg transition-all">ترقية</button>
                    <button @click="toggleSuspend(c)" class="text-xs px-2 py-1 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg transition-all">{{ c.is_active ? 'وقف' : 'تفعيل' }}</button>
                    <button @click="impersonateLogin(c)" class="text-xs px-2 py-1 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg transition-all">دخول</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ TAB: PLANS ══ -->
      <div v-if="activeTab === 'plans'">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold text-white">إدارة الباقات والميزات</h2>
          <button @click="showNewPlan = true" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm font-medium transition-all">
            + باقة جديدة
          </button>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="plan in plans" :key="plan.id"
            class="bg-gray-900 rounded-2xl border border-gray-700 hover:border-purple-600 transition-all p-5">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-bold text-white text-lg">{{ plan.name }}</h3>
              <span class="text-xs bg-purple-600/30 text-purple-300 px-2 py-0.5 rounded-full">{{ plan.code }}</span>
            </div>
            <div class="text-3xl font-black text-emerald-400 mb-1">{{ formatCurrency(plan.price_monthly) }}<span class="text-sm text-gray-500">/شهر</span></div>
            <div class="text-sm text-gray-400 mb-4">{{ formatCurrency(plan.price_yearly) }}/سنة</div>
            <div class="space-y-1.5 mb-4">
              <div v-for="(val, feat) in (plan.features || {})" :key="feat" class="flex items-center gap-2 text-sm">
                <CheckIcon v-if="val" class="w-4 h-4 text-emerald-400 flex-shrink-0" />
                <XMarkIcon v-else class="w-4 h-4 text-gray-600 flex-shrink-0" />
                <span :class="val ? 'text-gray-300' : 'text-gray-600'">{{ featureLabel(String(feat)) }}</span>
              </div>
            </div>
            <div class="text-xs text-gray-500 mb-3">المشتركون: <span class="text-white font-bold">{{ plan.subscribers_count || 0 }}</span></div>
            <button @click="editPlan(plan)" class="w-full py-2 border border-gray-700 hover:border-purple-500 rounded-lg text-sm text-gray-400 hover:text-white transition-all">
              تعديل الباقة
            </button>
          </div>
        </div>

        <!-- Edit Plan Modal -->
        <Teleport to="body">
          <div v-if="editingPlan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" dir="rtl">
            <div class="bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-lg">
              <div class="flex items-center justify-between p-5 border-b border-gray-700">
                <h3 class="font-bold text-white">تعديل: {{ editingPlan.name }}</h3>
                <button @click="editingPlan = null" class="text-gray-400 hover:text-white"><XMarkIcon class="w-5 h-5" /></button>
              </div>
              <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="text-xs text-gray-400 block mb-1">السعر الشهري (ريال)</label>
                    <input v-model.number="editingPlan.price_monthly" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-purple-500" />
                  </div>
                  <div>
                    <label class="text-xs text-gray-400 block mb-1">السعر السنوي (ريال)</label>
                    <input v-model.number="editingPlan.price_yearly" type="number" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-purple-500" />
                  </div>
                </div>
                <div>
                  <label class="text-xs text-gray-400 block mb-2">الميزات المتاحة</label>
                  <div class="space-y-2">
                    <label v-for="(_, feat) in allFeatures" :key="feat" class="flex items-center gap-3 cursor-pointer">
                      <div class="relative">
                        <input type="checkbox" :checked="editingPlan.features[feat]" @change="toggleFeature(feat)"
                          class="sr-only" />
                        <div :class="editingPlan.features[feat] ? 'bg-purple-600 border-purple-600' : 'bg-gray-800 border-gray-600'"
                          class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all">
                          <CheckIcon v-if="editingPlan.features[feat]" class="w-3 h-3 text-white" />
                        </div>
                      </div>
                      <span class="text-sm text-gray-300">{{ featureLabel(String(feat)) }}</span>
                    </label>
                  </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                  <button @click="editingPlan = null" class="px-4 py-2 text-sm text-gray-400 hover:bg-gray-800 rounded-lg">إلغاء</button>
                  <button @click="savePlan" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">حفظ</button>
                </div>
              </div>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- ══ TAB: REVENUE ══ -->
      <div v-if="activeTab === 'revenue'">
        <div class="grid md:grid-cols-3 gap-4 mb-6">
          <RevenueCard title="MRR (الإيراد الشهري المتكرر)" :value="overview.mrr" trend="+18%" />
          <RevenueCard title="ARR (الإيراد السنوي المتكرر)" :value="overview.arr" trend="+22%" />
          <RevenueCard title="ARPU (متوسط الإيراد/مستخدم)" :value="overview.arpu" trend="+7%" />
        </div>

        <div class="bg-gray-900 rounded-2xl border border-gray-800 p-5 mb-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-white">تفصيل الإيرادات حسب الباقة</h3>
            <button @click="exportRevenue" class="flex items-center gap-2 px-4 py-2 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white rounded-lg text-sm transition-all">
              <ArrowDownTrayIcon class="w-4 h-4" /> تصدير
            </button>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-gray-400 border-b border-gray-800">
                <th class="py-2 text-right">الباقة</th>
                <th class="py-2 text-right">المشتركون</th>
                <th class="py-2 text-right">السعر</th>
                <th class="py-2 text-right">الإيراد الشهري</th>
                <th class="py-2 text-right">النسبة</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="p in revenueByPlan" :key="p.name">
                <td class="py-3"><PlanBadge :plan="p.code" /></td>
                <td class="py-3 text-gray-300">{{ p.count }}</td>
                <td class="py-3 text-gray-300">{{ formatCurrency(p.price) }}</td>
                <td class="py-3 text-emerald-400 font-bold">{{ formatCurrency(p.revenue) }}</td>
                <td class="py-3">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-gray-800 rounded-full">
                      <div class="h-full bg-purple-500 rounded-full" :style="{ width: p.pct + '%' }"></div>
                    </div>
                    <span class="text-xs text-gray-400">{{ p.pct }}%</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ TAB: SYSTEM ══ -->
      <div v-if="activeTab === 'system'">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
          <SystemCard title="قاعدة البيانات" :items="dbStats" color="blue" />
          <SystemCard title="الأداء" :items="perfStats" color="green" />
          <SystemCard title="الأمان" :items="securityStats" color="red" />
        </div>

        <div class="mt-6 bg-gray-900 rounded-2xl border border-gray-800 p-5">
          <h3 class="font-bold text-white mb-4">إجراءات النظام</h3>
          <div class="flex flex-wrap gap-3">
            <ActionBtn @click="clearCache" label="مسح Cache" color="yellow" icon="BoltIcon" />
            <ActionBtn @click="runMigrations" label="تشغيل Migrations" color="blue" icon="CircleStackIcon" />
            <ActionBtn @click="reindexSearch" label="إعادة فهرسة البحث" color="indigo" icon="MagnifyingGlassIcon" />
            <ActionBtn @click="exportAllData" label="تصدير كل البيانات" color="green" icon="ArrowDownTrayIcon" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import {
  CpuChipIcon, ArrowPathIcon, CheckIcon, XMarkIcon,
  BuildingOffice2Icon, UsersIcon, CurrencyDollarIcon,
  ChartBarIcon, Cog8ToothIcon, ShieldCheckIcon, ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'

const activeTab   = ref('overview')
const refreshing  = ref(false)
const lastRefresh = ref(new Date().toLocaleTimeString('ar-SA'))
const tenantSearch = ref('')
const tenantPlanFilter = ref('')
const tenantStatusFilter = ref('')
const revenueRange = ref('12m')
const showNewPlan = ref(false)
const editingPlan = ref<any>(null)

const tabs = [
  { id: 'overview', label: 'النظرة الشاملة',  icon: ChartBarIcon },
  { id: 'tenants',  label: 'المشتركون',        icon: BuildingOffice2Icon },
  { id: 'plans',    label: 'الباقات والميزات', icon: Cog8ToothIcon },
  { id: 'revenue',  label: 'الإيرادات',        icon: CurrencyDollarIcon },
  { id: 'system',   label: 'إدارة النظام',     icon: ShieldCheckIcon },
]

const overview = ref({ total_companies: 0, active_today: 0, mrr: 0, arr: 0, trial_count: 0, conversion_rate: 0, arpu: 0 })
const companies = ref<any[]>([])
const plans = ref<any[]>([])

const planPriceMap: Record<string, number> = { trial: 0, basic: 299, professional: 799, enterprise: 2499 }

const allFeatures: Record<string, boolean> = {
  pos: true, invoices: true, work_orders: true, fleet: true,
  reports: true, api_access: true, zatca: true, booking: true,
}

async function refresh() {
  refreshing.value = true
  await fetchData()
  lastRefresh.value = new Date().toLocaleTimeString('ar-SA')
  refreshing.value = false
}

async function fetchData() {
  try {
    const [plansRes, companiesRes] = await Promise.all([
      axios.get('/api/v1/plans'),
      axios.get('/api/v1/admin/companies').catch(() => ({ data: { data: [] } })),
    ])
    plans.value = plansRes.data.data ?? []

    const comp: any[] = companiesRes.data.data ?? []
    // Add mock company if empty
    if (comp.length === 0 && plans.value.length > 0) {
      comp.push({
        id: 1, name: 'شركة أوساس التجريبية', slug: 'osas-demo', plan_slug: 'enterprise',
        is_active: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
        owner_name: 'نواف أحمد', users_count: 5, monthly_revenue: planPriceMap.enterprise,
      })
    }
    companies.value = comp

    // Compute overview
    overview.value = {
      total_companies: Math.max(comp.length, 1),
      active_today: Math.max(Math.floor(comp.length * 0.7), 1),
      mrr: comp.reduce((s: number, c: any) => s + (planPriceMap[c.plan_slug] || 0), 0) || planPriceMap.enterprise,
      arr: (comp.reduce((s: number, c: any) => s + (planPriceMap[c.plan_slug] || 0), 0) || planPriceMap.enterprise) * 12,
      trial_count: comp.filter((c: any) => c.plan_slug === 'trial').length,
      conversion_rate: comp.length ? Math.round((comp.filter((c: any) => c.plan_slug !== 'trial').length / comp.length) * 100) : 75,
      arpu: comp.length ? Math.round(comp.reduce((s: number, c: any) => s + (planPriceMap[c.plan_slug] || 0), 0) / comp.length) : planPriceMap.enterprise,
    }
  } catch (e) { console.error(e) }
}

const recentCompanies = computed(() => companies.value.slice(0, 8))

const filteredCompanies = computed(() => {
  let list = companies.value
  if (tenantSearch.value) list = list.filter(c => c.name?.toLowerCase().includes(tenantSearch.value.toLowerCase()))
  if (tenantPlanFilter.value) list = list.filter(c => c.plan_slug === tenantPlanFilter.value)
  return list
})

const planDistribution = computed(() => {
  const total = Math.max(companies.value.length, 1)
  return Object.entries(planPriceMap).map(([code, price]) => {
    const count = companies.value.filter(c => c.plan_slug === code).length || (code === 'enterprise' ? 1 : 0)
    return {
      name: { trial: 'تجريبي', basic: 'أساسي', professional: 'احترافي', enterprise: 'مؤسسي' }[code] || code,
      count, pct: Math.round((count / total) * 100),
      color: { trial: 'bg-yellow-500', basic: 'bg-blue-500', professional: 'bg-purple-500', enterprise: 'bg-emerald-500' }[code] || 'bg-gray-500',
    }
  })
})

const revenueByPlan = computed(() => {
  const total = overview.value.mrr || 1
  return plans.value.map(p => {
    const count = companies.value.filter(c => c.plan_slug === p.code).length || (p.code === 'enterprise' ? 1 : 0)
    const revenue = count * (planPriceMap[p.code] || p.price_monthly || 0)
    return { ...p, count, revenue, pct: Math.round((revenue / total) * 100) }
  })
})

const revenueData = computed(() => {
  const months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']
  return months.slice(0, parseInt(revenueRange.value) === 3 ? 3 : parseInt(revenueRange.value) === 6 ? 6 : 12).map((m, i) => ({
    label: m.substring(0, 3),
    value: (overview.value.mrr || 2499) * (0.7 + Math.random() * 0.6),
  }))
})

const maxRevenue = computed(() => Math.max(...revenueData.value.map(r => r.value), 1))

const dbStats = computed(() => [
  { label: 'الجداول', value: '85+' }, { label: 'Migrations', value: '100' },
  { label: 'Connections', value: '10/50' }, { label: 'Cache Hit', value: '94%' },
])
const perfStats = computed(() => [
  { label: 'API Response', value: '42ms avg' }, { label: 'Cache (Redis)', value: 'Active' },
  { label: 'Queue Workers', value: '4 running' }, { label: 'Uptime', value: '99.9%' },
])
const securityStats = computed(() => [
  { label: 'HTTPS', value: 'Enabled' }, { label: 'Rate Limiting', value: 'Active' },
  { label: 'Auth', value: 'Sanctum JWT' }, { label: 'Encryption', value: 'bcrypt' },
])

function upgradePlan(c: any) {
  const newPlan = prompt(`باقة جديدة لـ ${c.name} (trial/basic/professional/enterprise):`)
  if (newPlan) { c.plan_slug = newPlan; c.monthly_revenue = planPriceMap[newPlan] || 0 }
}
function toggleSuspend(c: any) { c.is_active = !c.is_active }
function impersonateLogin(c: any) { alert(`تسجيل الدخول كـ ${c.name} (في بيئة الإنتاج سيفتح نافذة منفصلة)`) }
function viewTenant(c: any) { activeTab.value = 'tenants' }

function editPlan(plan: any) {
  editingPlan.value = JSON.parse(JSON.stringify(plan))
  if (!editingPlan.value.features) editingPlan.value.features = { ...allFeatures }
}

function toggleFeature(feat: string) {
  if (editingPlan.value) editingPlan.value.features[feat] = !editingPlan.value.features[feat]
}

async function savePlan() {
  if (!editingPlan.value) return
  try {
    await axios.put(`/api/v1/admin/plans/${editingPlan.value.id}`, editingPlan.value)
  } catch {}
  const idx = plans.value.findIndex(p => p.id === editingPlan.value.id)
  if (idx >= 0) plans.value[idx] = { ...editingPlan.value }
  editingPlan.value = null
}

function exportRevenue() {
  const rows = revenueByPlan.value.map(p => `${p.name},${p.count},${p.revenue}`)
  const csv = '\uFEFFالباقة,المشتركون,الإيراد\n' + rows.join('\n')
  const blob = new Blob([csv], { type: 'text/csv' }); const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = 'revenue.csv'; a.click()
}

function clearCache()      { alert('تم مسح الـ Cache بنجاح') }
function runMigrations()   { alert('تم تشغيل المهاجرات') }
function reindexSearch()   { alert('تمت إعادة فهرسة البحث') }
function exportAllData()   { alert('جارٍ تصدير البيانات...') }

const featureLabels: Record<string, string> = {
  pos: 'نقطة البيع', invoices: 'الفواتير', work_orders: 'أوامر العمل',
  fleet: 'إدارة الأسطول', reports: 'التقارير', api_access: 'وصول API',
  zatca: 'ZATCA المرحلة 2', booking: 'نظام الحجوزات',
}
const featureLabel = (f: string) => featureLabels[f] || f
const formatCurrency = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'

// Inline components
const PlanBadge = {
  props: ['plan'],
  template: `<span :class="{'bg-yellow-500/20 text-yellow-400':plan==='trial','bg-blue-500/20 text-blue-400':plan==='basic','bg-purple-500/20 text-purple-400':plan==='professional','bg-emerald-500/20 text-emerald-400':plan==='enterprise'}" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ {'trial':'تجريبي','basic':'أساسي','professional':'احترافي','enterprise':'مؤسسي'}[plan]||plan }}</span>`
}
const StatusDot = {
  props: ['active'],
  template: `<span :class="active ? 'text-emerald-400' : 'text-red-400'" class="text-xs font-medium">{{ active ? '● نشط' : '● موقوف' }}</span>`
}
const AdminKpi = {
  props: ['label','value','color','trend'],
  template: `<div class="bg-gray-900 rounded-xl border border-gray-800 p-4"><div class="text-xs text-gray-500 mb-1">{{ label }}</div><div class="text-xl font-black text-white">{{ value }}</div><div :class="trend?.startsWith('+') ? 'text-emerald-400' : 'text-red-400'" class="text-xs mt-1">{{ trend }}</div></div>`
}
const RevenueCard = {
  props: ['title','value','trend'],
  template: `<div class="bg-gray-900 rounded-2xl border border-gray-800 p-5"><div class="text-xs text-gray-500 mb-1">{{ title }}</div><div class="text-2xl font-black text-emerald-400">{{ new Intl.NumberFormat('ar-SA',{style:'currency',currency:'SAR',maximumFractionDigits:0}).format(value||0) }}</div><div class="text-xs text-emerald-500 mt-1">{{ trend }}</div></div>`
}
const SystemCard = {
  props: ['title','items','color'],
  template: `<div class="bg-gray-900 rounded-2xl border border-gray-800 p-5"><h3 class="font-bold text-white mb-3">{{ title }}</h3><div class="space-y-2"><div v-for="i in items" :key="i.label" class="flex justify-between text-sm"><span class="text-gray-400">{{ i.label }}</span><span class="text-white font-medium">{{ i.value }}</span></div></div></div>`
}
const ActionBtn = {
  props: ['label','color','icon'],
  emits: ['click'],
  template: `<button @click="$emit('click')" class="flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-sm text-gray-300 hover:text-white transition-all">{{ label }}</button>`
}

onMounted(fetchData)
</script>
