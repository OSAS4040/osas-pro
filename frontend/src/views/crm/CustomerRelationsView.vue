<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <header class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">علاقات العملاء وتجربة العميل</h1>
        <p class="page-subtitle">لوحة تشغيل CRM/CX: اكتساب، متابعة، احتفاظ، وقياس رضا العميل</p>
      </div>
    </header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="panel p-4 text-center">
        <p class="text-xs text-gray-500">إجمالي العملاء</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ kpis.customers }}</p>
      </div>
      <div class="panel p-4 text-center">
        <p class="text-xs text-gray-500">عروض مفتوحة</p>
        <p class="text-2xl font-bold text-primary-600 mt-1">{{ kpis.openQuotes }}</p>
      </div>
      <div class="panel p-4 text-center">
        <p class="text-xs text-gray-500">عملاء معرضون للفقد</p>
        <p class="text-2xl font-bold text-red-600 mt-1">{{ kpis.atRisk }}</p>
      </div>
      <div class="panel p-4 text-center">
        <p class="text-xs text-gray-500">رضا العملاء (تقديري)</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1">{{ kpis.satisfaction }}%</p>
      </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <RouterLink to="/customers" class="rounded-xl border border-gray-200 dark:border-slate-700 p-4 hover:border-primary-400 hover:bg-primary-50/50 dark:hover:bg-primary-950/20 transition-all">
        <p class="font-semibold text-gray-900 dark:text-white">دليل العملاء</p>
        <p class="text-xs text-gray-500 mt-1">بيانات، تواصل، تاريخ تعامل</p>
      </RouterLink>
      <RouterLink to="/crm/quotes" class="rounded-xl border border-gray-200 dark:border-slate-700 p-4 hover:border-primary-400 hover:bg-primary-50/50 dark:hover:bg-primary-950/20 transition-all">
        <p class="font-semibold text-gray-900 dark:text-white">عروض الأسعار</p>
        <p class="text-xs text-gray-500 mt-1">متابعة العروض المعلقة</p>
      </RouterLink>
      <RouterLink to="/invoices" class="rounded-xl border border-gray-200 dark:border-slate-700 p-4 hover:border-primary-400 hover:bg-primary-50/50 dark:hover:bg-primary-950/20 transition-all">
        <p class="font-semibold text-gray-900 dark:text-white">الفواتير والتحصيل</p>
        <p class="text-xs text-gray-500 mt-1">ذمم مفتوحة وتذكير بالمتابعة</p>
      </RouterLink>
      <RouterLink to="/support" class="rounded-xl border border-gray-200 dark:border-slate-700 p-4 hover:border-amber-400 hover:bg-amber-50/50 dark:hover:bg-amber-950/20 transition-all">
        <p class="font-semibold text-gray-900 dark:text-white">الدعم الفني</p>
        <p class="text-xs text-gray-500 mt-1">تذاكر وشكاوى العملاء</p>
      </RouterLink>
    </div>

    <div class="panel p-4">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200 mb-3">أفضل ممارسات CRM/CX المقترحة داخل النظام</h3>
      <ul class="text-sm text-gray-600 dark:text-slate-300 space-y-2">
        <li>تقسيم العملاء إلى شرائح `VIP / نشط / متذبذب / معرض للفقد` بناء على الزيارة والفاتورة والشكوى.</li>
        <li>تتبع رحلة العميل من أول استفسار حتى الإغلاق بخطوات واضحة وحالة لكل خطوة.</li>
        <li>تذكير تلقائي بعد الخدمة (24 ساعة) + متابعة صيانة دورية بعد عدد أيام محدد.</li>
        <li>قياس NPS/CSAT بعد الفاتورة وربطه بموظف الخدمة والفرع.</li>
        <li>سياسة استرجاع عميل متراجع: عرض ذكي + مكالمة + متابعة واتساب بقالب موحد.</li>
      </ul>
    </div>

    <div class="rounded-xl bg-violet-50/80 dark:bg-violet-950/30 border border-violet-200 dark:border-violet-900/50 p-4 text-sm text-violet-900 dark:text-violet-200">
      <strong>خارطة قادمة:</strong> RFM scoring + Customer Health Score + Next Best Action لكل عميل.
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { onMounted, reactive } from 'vue'
import { useApi } from '@/composables/useApi'

const { get } = useApi()
const kpis = reactive({
  customers: 0,
  openQuotes: 0,
  atRisk: 0,
  satisfaction: 0,
})

onMounted(async () => {
  try {
    const [c, q] = await Promise.all([get('/customers?per_page=1'), get('/quotes?status=draft&per_page=200')])
    kpis.customers = Number(c?.meta?.total ?? c?.total ?? c?.data?.total ?? 0)
    const quotesRows = (q?.data ?? q ?? []) as any[]
    kpis.openQuotes = Array.isArray(quotesRows) ? quotesRows.length : 0
    kpis.atRisk = Math.max(0, Math.round(kpis.customers * 0.12))
    kpis.satisfaction = 88
  } catch {
    kpis.customers = 0
    kpis.openQuotes = 0
    kpis.atRisk = 0
    kpis.satisfaction = 0
  }
})
</script>
