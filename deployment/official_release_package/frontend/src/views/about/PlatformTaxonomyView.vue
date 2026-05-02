<template>
  <div class="app-shell-page max-w-3xl space-y-8" dir="rtl">
    <div>
      <h1 class="page-title-xl">مسرد الكيانات والبوابات</h1>
      <p class="page-subtitle text-sm mt-1 leading-relaxed">
        توضيح ثابت لتقليل الخلط بين <strong class="font-semibold text-gray-900 dark:text-slate-100">منصة أسس برو</strong>،
        <strong class="font-semibold text-gray-900 dark:text-slate-100">الشركة المشتركة (المستأجر)</strong>،
        و<strong class="font-semibold text-gray-900 dark:text-slate-100">العميل النهائي</strong>.
        لا يغيّر هذا المستند صلاحيات الخادم — الهدف وضوح المنتج والدعم.
      </p>
    </div>

    <section class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 space-y-4">
      <h2 class="text-sm font-bold text-gray-900 dark:text-slate-100">ثلاثة مستويات مستقلة</h2>
      <dl class="space-y-4 text-sm">
        <div class="border-b border-gray-100 dark:border-slate-700 pb-3">
          <dt class="font-semibold text-primary-700 dark:text-primary-300 mb-1">منصة أسس برو (Platform)</dt>
          <dd class="text-gray-600 dark:text-slate-300 leading-relaxed">
            المشغّل التجاري للخدمة السحابية: كتالوج الباقات العالمي، سياسات تتعلق بعدة مستأجرين،
            ولوحات تشغيل محدودة لمشغّلي المنصة (حسب الإعداد في الخادم).
            الوصول لا يُستنتج من «مالك شركة» فقط — يُضبط عبر البيئة (مثل بريد مسموح في الخادم).
          </dd>
        </div>
        <div class="border-b border-gray-100 dark:border-slate-700 pb-3">
          <dt class="font-semibold text-primary-700 dark:text-primary-300 mb-1">الشركة المشتركة (Tenant / Provider)</dt>
          <dd class="text-gray-600 dark:text-slate-300 leading-relaxed">
            مركز الخدمة أو منفذ البيع (المنشأة) الذي لديه اشتراك على النظام: فروع، مستخدمون، فواتير، محفظة تشغيل، وإعدادات.
            يُقصد بـ «العملاء» في القائمة الجانبية (قائمة العملاء) <em>عملاء هذه الشركة</em>، وليس مشتركي المنصة.
          </dd>
        </div>
        <div>
          <dt class="font-semibold text-primary-700 dark:text-primary-300 mb-1">العميل النهائي (End customer)</dt>
          <dd class="text-gray-600 dark:text-slate-300 leading-relaxed">
            شخص أو جهة تتعامل مع مركز الخدمة أو المنفذ (صيانة، حجز، فواتير): حساب بوابة العملاء أو تجربة محدودة عبر الروابط.
            محفظته وفواتيره تخص علاقته مع <em>الشركة المشتركة</em>، وليست اشتراكاً منفصلاً عن أسس برو ما لم يُبنَ ذلك لاحقاً في المنتج.
          </dd>
        </div>
      </dl>
    </section>

    <section class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 space-y-3">
      <h2 class="text-sm font-bold text-gray-900 dark:text-slate-100">البوابات المعتمدة</h2>
      <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
        النموذج الرسمي للمنتج: <strong class="text-gray-700 dark:text-slate-300">ثلاث بوابات فقط</strong> — دون اعتماد مسارات أخرى في الوصف التسويقي أو التعاقدي.
      </p>
      <ul class="text-sm text-gray-600 dark:text-slate-300 space-y-2 list-disc pr-5">
        <li>
          <strong class="text-gray-800 dark:text-slate-200">إدارة المنصّة:</strong>
          تشغيل المنصّة والاشتراكات والسياسات عبر المستأجرين ومزوّدي الخدمة المرتبطين بالمنصّة — الدخول من
          <code class="text-xs bg-gray-100 dark:bg-slate-900 px-1 rounded" dir="ltr">/platform/login</code>
          ؛ الصلاحية على الخادم.
        </li>
        <li>
          <strong class="text-gray-800 dark:text-slate-200">بوابة مزوّد الخدمة (مقدّم الخدمة):</strong>
          التشغيل اليومي لمركز الخدمة أو الورشة — ما كان يُعرَض تاريخياً تحت «فريق العمل / Staff» والدخول من
          <code class="text-xs bg-gray-100 dark:bg-slate-900 px-1 rounded" dir="ltr">/login</code>.
        </li>
        <li>
          <strong class="text-gray-800 dark:text-slate-200">بوابة العميل (طالب الخدمة):</strong>
          طلب الخدمة والمتابعة من جهة العميل النهائي — مسارات
          <code class="text-xs bg-gray-100 dark:bg-slate-900 px-1 rounded" dir="ltr">/customer/*</code>
          ؛ تفعيل الواجهة يخضع لإعداد البناء عند الحاجة.
        </li>
      </ul>
    </section>

    <p class="text-xs text-gray-500 dark:text-slate-400">
      <RouterLink to="/about/deployment" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">
        بيانات النشر ومقارنة الخادم
      </RouterLink>
    </p>
  </div>
</template>

<script setup lang="ts">
// صفحة مسرد ثابتة — لا تستدعي API
</script>
