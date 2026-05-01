<template>
  <section class="space-y-4">
    <header class="rounded-2xl border border-violet-200/70 bg-violet-50/80 px-4 py-3 dark:border-violet-900/50 dark:bg-violet-950/30">
      <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">مفاتيح التكامل</h2>
      <p class="mt-1 text-xs text-gray-600 dark:text-slate-300">
        مفاتيح API الخاصة بالعميل لربط أنظمته بشكل آمن. هذه الواجهة مخصصة للعميل ولا تعتمد صلاحيات مزود الخدمة.
      </p>
    </header>

    <div class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
      <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-100">المفاتيح المتاحة</h3>
        <span class="rounded-lg bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-200">
          الإجمالي: {{ keys.length }}
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-right text-xs text-gray-500 dark:bg-slate-900/50 dark:text-slate-400">
            <tr>
              <th class="px-3 py-2 font-medium">اسم المفتاح</th>
              <th class="px-3 py-2 font-medium">آخر استخدام</th>
              <th class="px-3 py-2 font-medium">الحد بالدقيقة</th>
              <th class="px-3 py-2 font-medium">الحالة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr v-for="item in keys" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-slate-900/30">
              <td class="px-3 py-2 font-semibold text-gray-800 dark:text-slate-100">{{ item.name }}</td>
              <td class="px-3 py-2 text-gray-500 dark:text-slate-400">{{ item.lastUsed }}</td>
              <td class="px-3 py-2 text-gray-600 dark:text-slate-300">{{ item.rateLimit }} طلب/دقيقة</td>
              <td class="px-3 py-2">
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="item.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300'">
                  {{ item.active ? 'نشط' : 'موقوف' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100">
      للحفاظ على الأمان، إدارة إنشاء/إلغاء المفاتيح تتم وفق سياسة حساب العميل أو عبر مدير النظام.
    </div>
  </section>
</template>

<script setup lang="ts">
type CustomerApiKeyRow = {
  id: number
  name: string
  lastUsed: string
  rateLimit: number
  active: boolean
}

const keys: CustomerApiKeyRow[] = [
  { id: 1, name: 'ERP-Connector', lastUsed: 'اليوم 10:15 ص', rateLimit: 120, active: true },
  { id: 2, name: 'Mobile-Integration', lastUsed: 'أمس 08:42 م', rateLimit: 60, active: true },
]
</script>
