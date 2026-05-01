<template>
  <section class="space-y-4">
    <header class="rounded-2xl border border-emerald-200/70 bg-emerald-50/80 px-4 py-3 dark:border-emerald-900/50 dark:bg-emerald-950/30">
      <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">الزكاة والضريبة (ZATCA)</h2>
      <p class="mt-1 text-xs text-gray-600 dark:text-slate-300">
        عرض حالة الامتثال الضريبي وفواتير العميل المرتبطة بالهيئة دون الانتقال إلى شاشة مزود الخدمة.
      </p>
    </header>

    <div class="grid gap-3 md:grid-cols-3">
      <article class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <p class="text-xs text-gray-500 dark:text-slate-400">الرقم الضريبي</p>
        <p class="mt-1 text-sm font-bold text-gray-900 dark:text-slate-100">{{ vatNumber }}</p>
      </article>
      <article class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <p class="text-xs text-gray-500 dark:text-slate-400">السجل التجاري</p>
        <p class="mt-1 text-sm font-bold text-gray-900 dark:text-slate-100">{{ crNumber }}</p>
      </article>
      <article class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <p class="text-xs text-gray-500 dark:text-slate-400">حالة الربط</p>
        <p class="mt-1 text-sm font-bold text-emerald-700 dark:text-emerald-300">مفعل</p>
      </article>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
      <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-slate-100">ملخص فواتير ZATCA</h3>
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-emerald-50 px-3 py-2 text-center dark:bg-emerald-900/30">
          <p class="text-[11px] text-emerald-700 dark:text-emerald-300">المرفوعة</p>
          <p class="text-lg font-bold text-emerald-800 dark:text-emerald-200">24</p>
        </div>
        <div class="rounded-xl bg-amber-50 px-3 py-2 text-center dark:bg-amber-900/30">
          <p class="text-[11px] text-amber-700 dark:text-amber-300">بانتظار المعالجة</p>
          <p class="text-lg font-bold text-amber-800 dark:text-amber-200">3</p>
        </div>
        <div class="rounded-xl bg-rose-50 px-3 py-2 text-center dark:bg-rose-900/30">
          <p class="text-[11px] text-rose-700 dark:text-rose-300">بحاجة مراجعة</p>
          <p class="text-lg font-bold text-rose-800 dark:text-rose-200">1</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const vatNumber = '300000000000003'
const crNumber = '1010123456'
</script>
