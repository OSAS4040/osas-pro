<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100">الأصول الثابتة</h1>
      <button class="px-3 py-2 rounded-lg text-xs bg-indigo-600 text-white opacity-80 cursor-not-allowed" disabled>
        إضافة أصل
      </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-900/60 text-gray-500 dark:text-slate-400 text-xs">
          <tr>
            <th class="px-3 py-2 text-right">الحالة</th>
            <th class="px-3 py-2 text-right">رقم الأصل</th>
            <th class="px-3 py-2 text-right">الاسم</th>
            <th class="px-3 py-2 text-right">تاريخ الشراء</th>
            <th class="px-3 py-2 text-right">القيمة الدفترية</th>
            <th class="px-3 py-2 text-right">المجمع</th>
            <th class="px-3 py-2 text-right">معدل الإهلاك</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="7" class="px-3 py-10 text-center text-gray-400 dark:text-slate-500">
              لا توجد سجلات بعد.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="rounded-xl border border-indigo-200 bg-indigo-50/80 dark:border-indigo-800 dark:bg-indigo-950/30 p-4 text-center">
      <p class="text-sm text-indigo-800 dark:text-indigo-200 font-semibold">هذه الميزة متاحة في الباقة الأعلى</p>
      <p class="text-xs text-indigo-700 dark:text-indigo-300 mt-1">يمكن تفعيلها لاحقًا مع سجل الإهلاك والترحيل الآلي للقيود.</p>
      <RouterLink to="/plans" class="inline-block mt-3 px-3 py-1.5 rounded-lg text-xs bg-indigo-600 text-white hover:bg-indigo-700">
        ترقية الباقة
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>
