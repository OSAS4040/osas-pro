<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold">مراقبة محافظ الاشتراك (شركة)</h1>
    <div class="rounded-xl border bg-white dark:bg-slate-900 overflow-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800">
          <tr>
            <th class="p-2 text-start">الشركة</th>
            <th class="p-2 text-start">الرصيد</th>
            <th class="p-2 text-start">العملة</th>
            <th class="p-2 text-start">الحالة</th>
            <th class="p-2 text-start">محدّث</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in wallets" :key="w.id" class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-2">
              <RouterLink class="font-semibold text-primary-700 hover:underline" :to="platformCompanyPath(Number(w.company_id))">
                {{ w.company?.name || '—' }}
              </RouterLink>
              <span class="block text-xs text-slate-500" dir="ltr">wallet #{{ w.id }} — company #{{ w.company_id }}</span>
            </td>
            <td class="p-2" dir="ltr">{{ w.balance }}</td>
            <td class="p-2">{{ w.currency }}</td>
            <td class="p-2">{{ w.status }}</td>
            <td class="p-2 text-xs" dir="ltr">{{ w.updated_at }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="text-xs text-slate-500">
      المعاملات الأخيرة للمحافظ تُعرض في استجابة الـ API — استخدم حمولة JSON أدناه للتحقق من الحقول الكاملة.
    </p>
    <AdminSubscriptionsFullPayload v-if="raw" :payload="raw" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { subscriptionsApi } from '../api'
import AdminSubscriptionsFullPayload from '../components/AdminSubscriptionsFullPayload.vue'
import { platformCompanyPath } from '../lib/platformLinks'

const wallets = ref<any[]>([])
const raw = ref<unknown>(null)
onMounted(async () => {
  const res = await subscriptionsApi.adminWallets()
  const root = res.data?.data
  raw.value = root ?? null
  const payload = root?.wallets
  wallets.value = Array.isArray(payload) ? payload : (payload?.data ?? [])
})
</script>
