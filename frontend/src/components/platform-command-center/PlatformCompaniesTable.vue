<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">الشركات</h3>
      <div class="flex gap-2">
        <button type="button" class="rounded-lg bg-violet-600 px-2.5 py-1.5 text-xs font-bold text-white" @click="$emit('add-company')">إضافة شركة</button>
        <button type="button" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs dark:border-slate-700" @click="$emit('export')">تصدير البيانات</button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-xs">
        <thead class="bg-slate-50 dark:bg-slate-800/70">
          <tr>
            <th class="px-3 py-2 text-right">اسم الشركة</th>
            <th class="px-3 py-2 text-right">الباقة</th>
            <th class="px-3 py-2 text-right">حالة الاشتراك</th>
            <th class="px-3 py-2 text-right">التجديد</th>
            <th class="px-3 py-2 text-right">المستخدمون</th>
            <th class="px-3 py-2 text-right">الإيراد</th>
            <th class="px-3 py-2 text-right">الصحة</th>
            <th class="px-3 py-2 text-right">الإجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <tr v-for="row in rows" :key="row.id">
            <td class="px-3 py-2 font-semibold">{{ row.name }}</td>
            <td class="px-3 py-2">{{ row.plan }}</td>
            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5" :class="row.subscriptionClass">{{ row.subscription }}</span></td>
            <td class="px-3 py-2">{{ row.renewal }}</td>
            <td class="px-3 py-2">{{ row.users }}</td>
            <td class="px-3 py-2">{{ row.revenue }}</td>
            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5" :class="row.healthClass">{{ row.health }}</span></td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-1">
                <button type="button" class="rounded-md border border-slate-300 px-2 py-1 dark:border-slate-700" @click="$emit('open', row.id)">فتح</button>
                <button type="button" class="rounded-md border border-slate-300 px-2 py-1 dark:border-slate-700" @click="$emit('manage', row.id)">إدارة</button>
              </div>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="8" class="px-3 py-8 text-center text-slate-500">لا توجد بيانات مطابقة.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{
  rows: Array<{
    id: number
    name: string
    plan: string
    subscription: string
    subscriptionClass: string
    renewal: string
    users: string
    revenue: string
    health: string
    healthClass: string
  }>
}>()
defineEmits<{ open: [id: number]; manage: [id: number]; 'add-company': []; export: [] }>()
</script>
