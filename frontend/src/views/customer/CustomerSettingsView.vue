<template>
  <div class="space-y-4 py-2" dir="rtl">
    <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 px-4 py-3 dark:border-emerald-800/60 dark:bg-emerald-950/30">
      <p class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">النموذج المالي: {{ financialModelLabel }}</p>
      <p class="mt-1 text-xs leading-relaxed text-emerald-900/85 dark:text-emerald-200/90">
        {{ financialModelHint }}
      </p>
    </div>
    <div class="rounded-2xl border border-violet-200/90 bg-violet-50/90 px-4 py-3 dark:border-violet-800/60 dark:bg-violet-950/30">
      <p class="text-sm font-semibold text-violet-950 dark:text-violet-100">إعدادات بوابة العميل</p>
      <p class="mt-1 text-xs leading-relaxed text-violet-900/85 dark:text-violet-200/90">
        هذه الصفحة مخصصة لإعدادات العميل فقط، وتُبقي التنقل داخل بوابة العميل.
      </p>
    </div>

    <div v-if="!isCreditCustomer" class="grid gap-3 sm:grid-cols-2">
      <RouterLink to="/customer/wallet/top-up-requests" class="rounded-xl border border-emerald-200 bg-white p-4 transition-colors hover:bg-emerald-50/60 dark:border-emerald-700 dark:bg-slate-800/50 dark:hover:bg-emerald-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">طلبات الشحن المسبق</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">إدارة طلبات شحن محفظة الشركة ومتابعة الاعتماد.</p>
      </RouterLink>
      <RouterLink to="/customer/wallet" class="rounded-xl border border-emerald-200 bg-white p-4 transition-colors hover:bg-emerald-50/60 dark:border-emerald-700 dark:bg-slate-800/50 dark:hover:bg-emerald-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">تحويلات المحافظ</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">التحويل من محفظة الشركة إلى محافظ المركبات حسب السياسة المالية.</p>
      </RouterLink>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        to="/customer/notifications"
        class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20"
      >
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">الإشعارات</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">إدارة تنبيهاتك ومتابعة الرسائل.</p>
      </RouterLink>

      <RouterLink
        to="/customer/dashboard"
        class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20"
      >
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">العودة للوحة العميل</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">الرجوع إلى مركز التشغيل الرئيسي للعميل.</p>
      </RouterLink>

      <RouterLink to="/customer/wallet" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">المحفظة والمدفوعات</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">إدارة الرصيد وطلبات الشحن الخاصة بالعميل.</p>
      </RouterLink>
      <RouterLink to="/customer/invoices" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">فواتيري</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">مراجعة الفواتير وحالة السداد.</p>
      </RouterLink>
      <RouterLink to="/customer/vehicles" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">مركباتي</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">إدارة مركبات العميل وسجلها.</p>
      </RouterLink>
      <RouterLink to="/customer/profile" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">الملف الشخصي</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">تحديث بيانات حسابك الشخصي وكلمة المرور.</p>
      </RouterLink>
      <RouterLink to="/customer/team-users" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">حسابات فريق العمل</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">إدارة مستخدمي فريق العميل وصلاحياتهم.</p>
      </RouterLink>
      <RouterLink to="/customer/org-units" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">هيكل القطاعات</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">تنظيم قطاعات منشأة العميل.</p>
      </RouterLink>
      <RouterLink to="/customer/activity" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">سجل العمليات</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">مراجعة نشاط حساب العميل وأحداثه.</p>
      </RouterLink>
      <RouterLink to="/customer/zatca" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">ZATCA</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">إعدادات ضريبية خاصة ببيانات العميل.</p>
      </RouterLink>
      <RouterLink to="/customer/api-keys" class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-violet-700 dark:hover:bg-violet-900/20">
        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">API Keys</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">مفاتيح تكامل العميل وربط أنظمته.</p>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const customerProfile = ref<any | null>(null)
const isCreditCustomer = computed(() => {
  const profile = String(customerProfile.value?.customer_pricing_profile ?? '').toLowerCase()
  if (profile === 'credit') return true
  const limit = Number(customerProfile.value?.credit_limit ?? 0)
  return Number.isFinite(limit) && limit > 0
})
const financialModelLabel = computed(() => (isCreditCustomer.value ? 'ائتماني' : 'مسبق الدفع'))
const financialModelHint = computed(() =>
  isCreditCustomer.value
    ? 'التركيز هنا على إدارة حد الائتمان والفواتير المستحقة والالتزام بدورات السداد.'
    : 'التركيز هنا على طلبات الشحن المسبق لمحفظة الشركة والتحويلات الداخلية لمحافظ المركبات.',
)

async function loadCustomerProfile() {
  try {
    const cid = auth.user?.customer_id
    if (!cid) {
      customerProfile.value = null
      return
    }
    const { data } = await apiClient.get(`/customers/${cid}`)
    customerProfile.value = data?.data ?? data ?? null
  } catch {
    customerProfile.value = null
  }
}

onMounted(loadCustomerProfile)
</script>
