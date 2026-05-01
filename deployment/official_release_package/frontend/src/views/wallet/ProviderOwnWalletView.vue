<template>
  <div class="app-shell-page" dir="rtl">
    <NavigationSourceHint />
    <div class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl flex items-center gap-2">
          <CreditCardIcon class="w-7 h-7 text-primary-600" />
          محفظتي
        </h1>
        <p class="page-subtitle">
          رصيد المنشأة المرتبط بالمنصّة — عرض فقط. شحن الرصيد ومحافظ العملاء من نطاق العميل أو إدارة المنصّة.
        </p>
        <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-1 max-w-2xl">
          بصفتك شريك تنفيذ، أنت معفي من اختيار الباقات والدفع الذاتي عبر بوابة الاشتراك؛ إدارة الباقة تتم وفق اتفاقك مع المنصّة.
        </p>
      </div>
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 p-8 animate-pulse">
      <div class="h-8 bg-gray-200 dark:bg-slate-600 rounded w-1/3 mb-4" />
      <div class="h-12 bg-gray-100 dark:bg-slate-700 rounded w-1/2" />
    </div>

    <div v-else-if="errorMsg" class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/90 dark:bg-amber-950/30 p-6 text-sm text-amber-950 dark:text-amber-100">
      {{ errorMsg }}
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2 max-w-3xl">
      <article class="rounded-2xl border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 shadow-sm">
        <p class="text-xs font-medium text-gray-500 dark:text-slate-400">الرصيد الحالي</p>
        <p class="text-2xl font-bold tabular-nums mt-2 text-gray-900 dark:text-white">{{ fmt(balance) }}</p>
        <p v-if="currency" class="text-xs text-gray-400 mt-1">{{ currency }}</p>
      </article>
      <article class="rounded-2xl border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 shadow-sm">
        <p class="text-xs font-medium text-gray-500 dark:text-slate-400">حالة المحفظة</p>
        <p class="text-lg font-semibold mt-2 text-gray-900 dark:text-white">{{ statusLabel }}</p>
        <p v-if="planHint" class="text-xs text-gray-500 dark:text-slate-400 mt-2">{{ planHint }}</p>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import { subscriptionsApi } from '@/modules/subscriptions/api'

const loading = ref(true)
const errorMsg = ref('')
const balance = ref(0)
const currency = ref('SAR')
const walletStatus = ref('')
const planHint = ref('')

const statusLabel = computed(() => {
  const s = String(walletStatus.value || '').toLowerCase()
  if (s === 'active') return 'نشطة'
  if (s === 'frozen') return 'مجمّدة'
  if (s === 'closed') return 'مغلقة'
  return walletStatus.value || '—'
})

function fmt(n: number) {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: currency.value || 'SAR' }).format(n || 0)
}

onMounted(async () => {
  loading.value = true
  errorMsg.value = ''
  try {
    const [walletRes, currentRes] = await Promise.allSettled([
      subscriptionsApi.getWallet(),
      subscriptionsApi.getCurrent(),
    ])
    if (walletRes.status === 'fulfilled') {
      const w = walletRes.value.data?.data?.wallet
      balance.value = Number(w?.balance ?? 0)
      currency.value = String(w?.currency ?? 'SAR')
      walletStatus.value = String(w?.status ?? '')
    } else {
      errorMsg.value =
        'تعذر تحميل رصيد المحفظة. إن لم يكن لديك صلاحية عرض الاشتراك، ستظهر البيانات عند تفعيلها من المنصّة.'
    }
    if (currentRes.status === 'fulfilled') {
      const plan = currentRes.value.data?.data?.plan
      const name = plan?.name_ar || plan?.name
      if (name) {
        planHint.value = `الباقة المعروضة في النظام: ${name} (الترقية والدفع عبر المنصّة لشريك التنفيذ).`
      }
    }
  } catch {
    errorMsg.value = 'حدث خطأ أثناء التحميل.'
  } finally {
    loading.value = false
  }
})
</script>
