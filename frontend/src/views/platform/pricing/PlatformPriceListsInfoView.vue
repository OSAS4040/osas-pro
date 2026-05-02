<template>
  <div class="mx-auto max-w-3xl space-y-4 pb-12" dir="rtl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">قوائم الأسعار والنسخ</h1>
    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
      لا يوجد مسار API منفصل لـ «كتالوج قوائم أسعار» عام؛ النظام يعتمد على
      <strong>نسخ أسعار العملاء</strong> المرتبطة بالشركة والعميل، حيث كل اعتماد لطلب تسعير ينشئ نسخة جديدة دون تعديل النسخة المرجعية السابقة مباشرة.
    </p>
    <RouterLink
      :to="{ name: 'platform-pricing-customer-prices' }"
      class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
    >
      الانتقال إلى أسعار العملاء (نسخ الإصدارات)
    </RouterLink>
    <RouterLink
      :to="{ name: 'platform-pricing-requests' }"
      class="mr-3 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600"
    >
      طلبات التسعير
    </RouterLink>
  </div>
</template>
