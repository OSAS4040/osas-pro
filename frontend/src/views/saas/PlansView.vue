<template>
  <div class="space-y-8" dir="rtl">
    <div class="text-center">
      <h2 class="text-3xl font-bold text-gray-900">باقات الاشتراك</h2>
      <p class="text-gray-500 mt-2 text-sm">اختر الباقة المناسبة لعملك</p>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600"></div>
    </div>

    <!-- Current Subscription Banner -->
    <div v-if="currentSub" class="bg-primary-50 border border-primary-200 rounded-xl p-4 flex items-center gap-4">
      <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center">
        <CheckBadgeIcon class="w-6 h-6 text-white" />
      </div>
      <div class="flex-1">
        <p class="font-semibold text-primary-900">اشتراكك الحالي: {{ currentSub.plan }}</p>
        <p class="text-xs text-primary-700">
          ينتهي: {{ formatDate(currentSub.ends_at) }} ·
          الفروع: {{ usage?.usage?.branches }}/{{ usage?.limits?.max_branches }} ·
          المستخدمون: {{ usage?.usage?.users }}/{{ usage?.limits?.max_users }}
        </p>
      </div>
      <button @click="$router.push('/subscription')"
        class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
        إدارة الاشتراك
      </button>
    </div>

    <!-- Plans Grid -->
    <div v-if="!loading" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div v-for="plan in plans" :key="plan.id"
        class="bg-white rounded-2xl border-2 p-6 flex flex-col transition-all hover:shadow-lg"
        :class="plan.slug === currentSub?.plan ? 'border-primary-500 shadow-md' : 'border-gray-200'">
        <!-- Badge -->
        <div v-if="plan.slug === currentSub?.plan" class="mb-3">
          <span class="bg-primary-600 text-white text-xs px-3 py-1 rounded-full font-medium">باقتك الحالية</span>
        </div>
        <div v-else-if="plan.slug === 'professional'" class="mb-3">
          <span class="bg-orange-500 text-white text-xs px-3 py-1 rounded-full font-medium">الأكثر شيوعاً</span>
        </div>
        <div v-else class="mb-3 h-6"></div>

        <h3 class="text-xl font-bold text-gray-900">{{ plan.name }}</h3>
        <div class="mt-2 mb-4">
          <span class="text-3xl font-bold text-primary-700">{{ formatNum(plan.price) }}</span>
          <span class="text-gray-500 text-sm"> ر.س/شهر</span>
        </div>
        <p class="text-gray-500 text-sm mb-4">{{ plan.description }}</p>

        <!-- Limits -->
        <div class="space-y-2 mb-6 flex-1">
          <div v-for="(val, key) in planLimits(plan)" :key="key"
            class="flex items-center gap-2 text-sm">
            <CheckIcon class="w-4 h-4 text-green-500 flex-shrink-0" />
            <span class="text-gray-700">{{ val }}</span>
          </div>
        </div>

        <!-- Features -->
        <div v-if="plan.features?.length" class="space-y-1.5 mb-6">
          <div v-for="f in plan.features" :key="f"
            class="flex items-center gap-2 text-sm">
            <SparklesIcon class="w-4 h-4 text-primary-500 flex-shrink-0" />
            <span class="text-gray-600">{{ f }}</span>
          </div>
        </div>

        <button
          :disabled="plan.slug === currentSub?.plan || changingPlan === plan.slug"
          @click="changePlan(plan.slug)"
          class="w-full py-2.5 rounded-xl font-semibold text-sm transition-colors"
          :class="plan.slug === currentSub?.plan
            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
            : 'bg-primary-600 text-white hover:bg-primary-700'">
          {{ plan.slug === currentSub?.plan ? 'باقتك الحالية' : (changingPlan === plan.slug ? 'جاري التغيير...' : 'الترقية لهذه الباقة') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { CheckBadgeIcon, CheckIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'


const plans = ref<any[]>([])
const currentSub = ref<any>(null)
const usage = ref<any>(null)
const loading = ref(true)
const changingPlan = ref('')

function formatNum(n: number) { return Number(n || 0).toLocaleString('ar-SA') }
function formatDate(d: string) { return new Date(d).toLocaleDateString('ar-SA') }

function planLimits(plan: any) {
  const limits = plan.limits ?? {}
  const result: Record<string, string> = {}
  if (limits.max_branches) result.branches = `${limits.max_branches} فرع`
  if (limits.max_users) result.users = `${limits.max_users} مستخدم`
  if (limits.max_vehicles) result.vehicles = `${limits.max_vehicles} مركبة`
  if (limits.max_products) result.products = `${limits.max_products} منتج`
  if (limits.max_monthly_invoices) result.invoices = `${limits.max_monthly_invoices} فاتورة/شهر`
  return result
}

async function changePlan(slug: string) {
  if (!confirm(`هل تريد تغيير باقتك إلى "${slug}"؟`)) return
  changingPlan.value = slug
  try {
    await apiClient.post('/subscription/change', { plan_slug: slug })
    const r = await apiClient.get('/subscription')
    currentSub.value = r.data?.data?.subscription
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'حدث خطأ')
  } finally { changingPlan.value = '' }
}

onMounted(async () => {
  loading.value = true
  try {
    const [p, s, u] = await Promise.all([
      apiClient.get('/plans'),
      apiClient.get('/subscription'),
      apiClient.get('/subscription/usage'),
    ])
    plans.value = p.data?.data ?? []
    currentSub.value = s.data?.data?.subscription
    usage.value = u.data
  } finally { loading.value = false }
})
</script>
