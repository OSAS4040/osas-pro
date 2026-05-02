<template>
  <div class="space-y-6">
    <header class="rounded-xl border bg-white dark:bg-slate-900 p-5">
      <h1 class="text-xl font-bold">الاشتراك</h1>
      <p class="text-sm text-slate-500">متابعة الحالة، المحفظة، والاستخدام.</p>
      <aside
        class="mt-4 rounded-lg border border-sky-200/80 bg-sky-50/90 px-4 py-3 text-xs leading-relaxed text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/25 dark:text-sky-100/95"
        role="note"
      >
        <p class="font-semibold text-sky-900 dark:text-sky-100">سياسة الأدوار</p>
        <p class="mt-1 text-sky-900/90 dark:text-sky-100/85">
          إدارة شركتك تتابع الباقة والدفع من هذه البوابة. <strong class="font-semibold">تحديد الكتالوج الرسمي للباقات، سياسات الأسعار العامة، أو اعتماد اشتراك يتطلب تدخلاً خاصاً من المنصّة</strong> يتم من طرف مشغّلي المنصّة وليس من لوحة عملاء آخرين على النظام.
        </p>
      </aside>
    </header>

    <section class="grid md:grid-cols-3 gap-4">
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">الباقة الحالية</p>
        <p class="text-lg font-semibold mt-1">{{ planName }}</p>
        <p class="text-xs mt-2">تنتهي: {{ formatDate(subscription?.ends_at) }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">حالة الاشتراك</p>
        <p class="text-lg font-semibold mt-1">{{ uiStatus }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-white dark:bg-slate-900">
        <p class="text-xs text-slate-500">رصيد المحفظة</p>
        <p class="text-lg font-semibold mt-1">{{ money(walletBalance) }}</p>
      </article>
    </section>

    <section class="grid md:grid-cols-3 gap-4">
      <article class="rounded-xl border p-4 bg-amber-50 dark:bg-amber-950/20">
        <p class="text-sm font-semibold">تنبيه</p>
        <p class="text-xs mt-1">{{ expiryAlert }}</p>
      </article>
      <article class="rounded-xl border p-4 bg-emerald-50 dark:bg-emerald-950/20">
        <p class="text-sm font-semibold">الرصيد</p>
        <p class="text-xs mt-1">رصيدك يكفي تقريبًا {{ walletDaysCover }} يوم.</p>
      </article>
      <article class="rounded-xl border p-4 bg-sky-50 dark:bg-sky-950/20">
        <p class="text-sm font-semibold">توصية</p>
        <p class="text-xs mt-1">الترقية قد تمنحك سعة أكبر وتقليل تكلفة التوسّع.</p>
      </article>
    </section>

    <section class="rounded-xl border p-4 bg-white dark:bg-slate-900">
      <h2 class="font-semibold mb-3">الاستخدام</h2>
      <div class="grid md:grid-cols-3 gap-3 text-sm">
        <div>الفروع: {{ subscription?.max_branches ?? '—' }}</div>
        <div>المستخدمون: {{ subscription?.max_users ?? '—' }}</div>
        <div>العملة: {{ subscription?.currency ?? 'SAR' }}</div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { subscriptionsApi } from '../api'

const subscription = ref<any>(null)
const plan = ref<any>(null)
const walletBalance = ref(0)

const planName = computed(() => plan.value?.name_ar || plan.value?.name || subscription.value?.plan || '—')
const uiStatus = computed(() => {
  const raw = String(subscription.value?.status || '').toLowerCase()
  if (raw === 'pending_transfer') return 'بانتظار التحويل'
  if (raw === 'awaiting_review') return 'جاري المراجعة'
  if (raw === 'approved' || raw === 'active') return 'تم التفعيل'
  if (raw === 'rejected') return 'مرفوض'
  if (raw === 'past_due') return 'متأخر سداد'
  if (raw === 'suspended') return 'موقوف'
  if (raw === 'expired') return 'منتهي'
  return raw || '—'
})
const expiryAlert = computed(() => {
  if (!subscription.value?.ends_at) return 'لا توجد بيانات انتهاء.'
  const days = Math.ceil((new Date(subscription.value.ends_at).getTime() - Date.now()) / (24 * 3600 * 1000))
  if (days <= 2) return `سينتهي اشتراكك خلال ${Math.max(0, days)} يوم.`
  return `الاشتراك مستقر، متبقٍ ${days} يوم.`
})
const walletDaysCover = computed(() => {
  const monthly = Number(plan.value?.price_monthly || 0)
  if (!monthly) return 0
  return Math.floor(Number(walletBalance.value) / (monthly / 30))
})

function formatDate(v?: string) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('ar-SA')
}
function money(v: number) {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(v || 0)
}

onMounted(async () => {
  const [current, wallet] = await Promise.all([subscriptionsApi.getCurrent(), subscriptionsApi.getWallet()])
  subscription.value = current.data?.data?.subscription ?? null
  plan.value = current.data?.data?.plan ?? null
  walletBalance.value = Number(wallet.data?.data?.wallet?.balance || 0)
})
</script>

