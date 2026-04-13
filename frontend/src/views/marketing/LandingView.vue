<template>
  <div
    class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-primary-50/40 pb-24 text-slate-900 selection:bg-primary-200/70 selection:text-slate-900 md:pb-0 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 dark:text-slate-100 dark:selection:bg-primary-900/50 dark:selection:text-slate-100"
    dir="rtl"
    lang="ar"
  >
    <div
      class="pointer-events-none fixed inset-x-0 top-0 z-[25] h-1 bg-slate-200/40 dark:bg-slate-800/60"
      aria-hidden="true"
    >
      <div
        class="h-full rounded-e-full bg-gradient-to-l from-primary-500 to-teal-500 transition-[width] duration-150 ease-out dark:from-primary-400 dark:to-teal-400"
        :style="{ width: `${scrollProgressPct}%` }"
      />
    </div>
    <PlatformPromoBanner class="relative z-[21] w-full border-b border-transparent" />
    <div
      v-if="auth.isPhoneOnboarding"
      data-testid="landing-phone-onboarding-return"
      class="relative z-[22] border-b border-emerald-200/90 bg-emerald-50/95 px-4 py-2 text-center text-xs font-semibold text-emerald-950 dark:border-emerald-800/80 dark:bg-emerald-950/50 dark:text-emerald-100"
    >
      <RouterLink
        to="/phone/onboarding/done"
        class="underline decoration-emerald-700/60 underline-offset-2 hover:text-emerald-800 dark:hover:text-emerald-200"
      >
        العودة لمسار التسجيل بالجوال وخطوات ما بعد الاسم
      </RouterLink>
    </div>
    <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-slate-950/85">
      <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 md:h-16 md:flex-nowrap md:gap-4 md:py-0">
        <div class="flex flex-wrap items-center justify-end gap-2">
          <div
            class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-600 font-black text-white shadow-md shadow-primary-600/25 ring-2 ring-primary-500/20"
            aria-hidden="true"
          >
            أ
          </div>
          <div>
            <p class="text-sm font-black leading-none">أسس برو</p>
            <p class="text-[10px] text-slate-500 dark:text-slate-400">Osas Pro</p>
          </div>
        </div>
        <nav
          class="order-last flex min-h-0 w-full min-w-0 justify-center border-t border-slate-200/70 py-2 dark:border-white/10 md:order-none md:w-auto md:border-0 md:py-0"
          aria-label="أقسام الصفحة"
        >
          <div
            class="flex max-w-full flex-wrap justify-center gap-1 overflow-x-auto overscroll-x-contain px-1 text-[11px] font-semibold sm:text-xs"
          >
            <a
              v-for="link in headerNavLinks"
              :key="link.href"
              :href="link.href"
              class="shrink-0 rounded-lg px-2.5 py-1.5 text-slate-600 transition hover:bg-primary-500/10 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-slate-300 dark:hover:bg-primary-500/15 dark:hover:text-primary-200"
              @click="trackLandingCta(link.event)"
            >
              {{ link.label }}
            </a>
          </div>
        </nav>
        <div class="flex items-center gap-2">
          <button
            type="button"
            aria-label="تفعيل الوضع التلقائي للمظهر"
            class="rounded-xl border px-3 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            :class="darkMode.themeMode.value === 'auto'
              ? 'border-emerald-400 bg-emerald-50 text-emerald-700 dark:border-emerald-500/70 dark:bg-emerald-900/20 dark:text-emerald-300'
              : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'"
            @click="darkMode.setAuto()"
          >
            تلقائي
          </button>
          <button
            type="button"
            :aria-label="darkMode.isDark.value ? 'تفعيل الوضع النهاري' : 'تفعيل الوضع الليلي'"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-all duration-300 hover:-translate-y-0.5 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
            @click="darkMode.toggle()"
          >
            <SunIcon
              v-if="darkMode.isDark.value"
              class="h-4 w-4 text-amber-500 transition-transform duration-300"
            />
            <MoonIcon
              v-else
              class="h-4 w-4 text-indigo-500 transition-transform duration-300"
            />
            {{ darkMode.isDark.value ? 'الوضع النهاري' : 'الوضع الليلي' }}
          </button>
          <RouterLink
            to="/login"
            class="rounded-xl border border-primary-500/50 bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            @click="trackLandingCta('landing_cta_login_header')"
          >
            دخول المنصة
          </RouterLink>
          <p class="hidden text-[11px] text-slate-500 dark:text-slate-400 lg:block">
            التلقائي: نهاري من 7 صباحًا إلى 6 مساءً، وليلي في بقية الوقت.
          </p>
        </div>
      </div>
    </header>

    <main>
      <section class="mx-auto grid w-full max-w-6xl gap-10 px-4 pb-12 pt-14 md:grid-cols-2 md:items-center lg:gap-14">
        <div class="motion-safe:animate-fade-in max-w-xl md:max-w-none">
          <p
            class="mb-4 inline-flex rounded-full border border-primary-500/25 bg-primary-500/10 px-3 py-1.5 text-xs font-semibold text-primary-800 shadow-sm ring-1 ring-primary-500/15 dark:text-primary-200"
          >
            منصة تشغيل أعمال متعددة الأنشطة
          </p>
          <h1 class="text-3xl font-black leading-[1.15] tracking-tight text-slate-900 md:text-5xl dark:text-white">
            نظام تشغيل أعمالك بالكامل
            <span class="mt-1 block bg-gradient-to-l from-primary-600 to-primary-500 bg-clip-text text-transparent dark:from-primary-400 dark:to-primary-300">
              في منصة واحدة.
            </span>
          </h1>
          <p class="mt-5 text-sm leading-7 text-slate-600 dark:text-slate-300 md:text-base">
            أسس برو يجمع التشغيل والمبيعات والمالية والمخزون في تدفق واحد — قرارات أسرع، أخطاء أقل، وصورة أوضح لربحية نشاطك،
            بواجهة عربية مصممة للفرق الحقيقية لا للعروض فقط.
          </p>
          <div
            class="mt-5 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm ring-1 ring-slate-200/40 backdrop-blur-sm dark:border-white/10 dark:bg-slate-900/50 dark:ring-white/5"
          >
            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">الأنشطة التي يدعمها</p>
            <ul class="mt-3 space-y-2.5 text-sm leading-6 text-slate-600 dark:text-slate-300 md:text-[0.9375rem] md:leading-7" role="list">
              <li v-for="line in heroActivities" :key="line" class="flex gap-3">
                <CheckIcon
                  class="mt-0.5 h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400"
                  aria-hidden="true"
                />
                <span>{{ line }}</span>
              </li>
            </ul>
          </div>
          <p
            class="mt-4 rounded-xl border border-slate-200/60 bg-slate-50/90 px-4 py-3 text-sm leading-7 text-slate-600 dark:border-white/10 dark:bg-slate-800/40 dark:text-slate-300"
          >
            يعمل كل ذلك ضمن تدفق موحّد لأوامر العمل، المخزون، المشتريات، المالية، الحجوزات، والتقارير والحوكمة.
          </p>
          <div class="mt-6 space-y-3">
            <RouterLink
              to="/login"
              class="flex w-full items-center justify-center rounded-xl bg-primary-600 px-5 py-3 text-sm font-bold text-white shadow-md shadow-primary-600/25 transition duration-200 hover:bg-primary-500 hover:shadow-lg hover:shadow-primary-600/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:ring-offset-slate-950 sm:w-auto sm:inline-flex sm:py-2.5"
              @click="trackLandingCta('landing_cta_login_hero')"
            >
              ابدأ تجربة عملية الآن
            </RouterLink>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
              <a
                href="#roi"
                class="landing-cta-secondary flex min-h-11 flex-1 items-center justify-center sm:max-w-[11rem]"
                @click="trackLandingCta('landing_nav_roi')"
              >
                احسب الأثر المتوقع
              </a>
              <a
                href="#feature-showcase"
                class="landing-cta-secondary flex min-h-11 flex-1 items-center justify-center sm:max-w-[11rem]"
                @click="trackLandingCta('landing_nav_features')"
              >
                شاهد المزايا مباشرة
              </a>
            </div>
            <details
              class="landing-explore-more rounded-2xl border border-slate-200/80 bg-white/60 p-3 shadow-sm dark:border-white/10 dark:bg-slate-900/40"
              @toggle="onExploreDetailsToggle"
            >
              <summary
                class="landing-explore-summary flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-bold text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-slate-100"
              >
                <span>استكشف باقي الصفحة</span>
                <span class="text-xs font-normal text-slate-500 dark:text-slate-400">خطوات، أدلة، أسئلة</span>
              </summary>
              <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200/70 pt-3 dark:border-white/10">
                <a
                  href="#proof"
                  class="landing-cta-secondary"
                  @click="trackLandingCta('landing_nav_proof')"
                >
                  أرقام سريعة
                </a>
                <a
                  href="#start-steps"
                  class="landing-cta-secondary"
                  @click="trackLandingCta('landing_nav_start_steps')"
                >
                  كيف أبدأ؟
                </a>
                <a
                  href="#smart-compare"
                  class="landing-cta-secondary"
                  @click="trackLandingCta('landing_nav_smart_compare')"
                >
                  قبل وبعد
                </a>
                <a
                  href="#platform-atlas"
                  class="landing-cta-secondary"
                  @click="trackLandingCta('landing_nav_header_atlas')"
                >
                  خريطة المنصة
                </a>
                <a
                  href="#faq"
                  class="landing-cta-secondary"
                  @click="trackLandingCta('landing_nav_header_faq')"
                >
                  الأسئلة الشائعة
                </a>
                <a
                  href="#pricing"
                  class="landing-cta-secondary"
                  @click="trackLandingCta('landing_nav_header_pricing')"
                >
                  الباقات والأسعار
                </a>
              </div>
            </details>
          </div>
          <div class="mt-6 grid grid-cols-3 gap-3 text-center text-xs">
            <div
              class="rounded-xl border border-slate-200/90 bg-white/90 p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:shadow-none dark:hover:shadow-lg dark:hover:shadow-black/20"
            >
              <p class="text-xl font-black text-primary-700 dark:text-primary-300">{{ APP_VERSION }}</p>
              <p class="mt-1 text-slate-500 dark:text-slate-400">نسخة جاهزة للتجربة</p>
            </div>
            <div
              class="rounded-xl border border-slate-200/90 bg-white/90 p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:shadow-none dark:hover:shadow-lg dark:hover:shadow-black/20"
            >
              <p class="text-xl font-black text-primary-700 dark:text-primary-300">6+</p>
              <p class="mt-1 text-slate-500 dark:text-slate-400">لغات واجهة</p>
            </div>
            <div
              class="rounded-xl border border-slate-200/90 bg-white/90 p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:shadow-none dark:hover:shadow-lg dark:hover:shadow-black/20"
            >
              <p class="text-xl font-black text-primary-700 dark:text-primary-300">24/7</p>
              <p class="mt-1 text-slate-500 dark:text-slate-400">قابلية تشغيل</p>
            </div>
          </div>
        </div>

        <div
          class="rounded-2xl border border-slate-200/80 bg-gradient-to-b from-white to-slate-50 p-5 shadow-xl shadow-slate-300/40 ring-1 ring-slate-200/60 dark:border-white/10 dark:from-slate-900 dark:to-slate-950 dark:shadow-primary-950/30 dark:ring-white/10"
        >
          <div class="mb-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
            لوحة تنفيذ ذكية
          </div>
          <div class="space-y-3">
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
              <p class="text-xs text-slate-500 dark:text-slate-400">مؤشرات الأداء</p>
              <p class="mt-1 text-lg font-black text-primary-700 dark:text-primary-300">+18% نمو التحصيل</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
              <p class="text-xs text-slate-500 dark:text-slate-400">تنبيهات فورية</p>
              <p class="mt-1 text-sm">تم اكتشاف 3 حالات تحتاج متابعة اليوم</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
              <p class="text-xs text-slate-500 dark:text-slate-400">الخطوة المقترحة</p>
              <p class="mt-1 text-sm">تفعيل حملة متابعة للعملاء المتأخرين خلال 48 ساعة</p>
            </div>
          </div>
        </div>
      </section>

      <section
        class="mx-auto w-full max-w-6xl border-y border-slate-200/60 bg-white/40 px-4 py-6 dark:border-white/10 dark:bg-slate-900/20"
        aria-label="ثقة سريعة"
      >
        <ul
          class="flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-xs font-semibold text-slate-700 dark:text-slate-200 md:text-sm"
          role="list"
        >
          <li v-for="chip in trustChips" :key="chip" class="flex items-center gap-2">
            <CheckIcon class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" aria-hidden="true" />
            <span>{{ chip }}</span>
          </li>
        </ul>
      </section>

      <section
        class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 pb-8 pt-6"
        aria-labelledby="landing-persona-heading"
      >
        <h2 id="landing-persona-heading" class="text-center text-base font-black text-slate-900 dark:text-white md:text-lg">
          ما أكبر ضغط تشغيلي لديك الآن؟
        </h2>
        <p class="mx-auto mt-2 max-w-xl text-center text-xs leading-6 text-slate-600 dark:text-slate-400 md:text-sm">
          اختر الأقرب لك — ننتقل مباشرة إلى مقارنة «قبل وبعد» مع سيناريو جاهز (توضيحي).
        </p>
        <div class="mt-5 flex flex-wrap justify-center gap-2">
          <button
            v-for="opt in painOptions"
            :key="opt.id"
            type="button"
            class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:text-sm"
            :class="selectedPainId === opt.id
              ? 'border-primary-500 bg-primary-500/15 text-primary-900 shadow-sm dark:text-primary-100'
              : 'border-slate-200 bg-white text-slate-700 hover:border-primary-400/50 dark:border-white/15 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:border-primary-500/40'"
            @click="jumpToPainStory(opt.id)"
          >
            {{ opt.label }}
          </button>
        </div>
      </section>

      <section id="start-steps" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 pb-10 pt-4">
        <h2 class="landing-section-title text-xl font-black md:text-2xl">كيف تبدأ خلال 3 خطوات</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
          مسار واضح يقلّل التردد ويُقربك من أول قرار تشغيلي مبني على بيانات.
        </p>
        <ol class="mt-6 grid list-none gap-4 p-0 md:grid-cols-3">
          <li
            v-for="(step, i) in startSteps"
            :key="step.title"
            class="landing-card flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 dark:shadow-none"
          >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-black text-white">
              {{ i + 1 }}
            </span>
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ step.title }}</p>
              <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ step.body }}</p>
            </div>
          </li>
        </ol>
      </section>

      <section id="proof" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-8">
        <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 md:grid-cols-4">
          <div v-for="item in proofMetrics" :key="item.label" class="text-center">
            <p class="text-2xl font-black text-primary-700 dark:text-primary-300">{{ item.value }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ item.label }}</p>
          </div>
        </div>
        <p class="mx-auto mt-3 max-w-3xl text-center text-[11px] leading-5 text-slate-500 dark:text-slate-400">
          الأرقام أعلاه توضيحية للتسويق ولا تمثل التزامًا بنتائج محددة؛ تختلف النتائج الفعلية حسب نشاطك وطريقة التطبيق.
        </p>
      </section>

      <section id="features" class="mx-auto w-full max-w-6xl px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">لماذا أسس برو؟</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <article v-for="item in features" :key="item.title" class="landing-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5 dark:shadow-none">
            <p class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ item.title }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ item.body }}</p>
          </article>
        </div>
      </section>

      <section id="feature-showcase" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">أهم المزايا — لمحة تفاعلية</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          جرّب التبويبات: كل واحد يلخّص جزءًا من المنصة كما يراه فريقك يوميًا — من الصندوق إلى التقارير والذكاء التشغيلي.
          المعاينة تتحرك بلطف مع المؤشر (ما لم تُفضّل المتصفح تقليل الحركة)، والصور الفعلية تُحمَّل عند الحاجة لسرعة أفضل.
        </p>
        <details class="mt-3 max-w-3xl rounded-xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-xs leading-6 text-slate-600 dark:border-white/10 dark:bg-slate-800/40 dark:text-slate-400">
          <summary class="cursor-pointer font-semibold text-slate-700 dark:text-slate-300">للمطوّرين: تحديث لقطات المعرض</summary>
          <p class="mt-2" lang="ar">
            يُفضّل المتصفح لقطة
            <span lang="en" dir="ltr" class="font-mono text-[11px]">PNG</span>
            إن وُجدت (من
            <span lang="en" dir="ltr" class="font-mono text-[11px]">npm run capture:showcase</span>
            ) ثم
            <span lang="en" dir="ltr" class="font-mono text-[11px]">WebP</span>
            المُولَّد من
            <span lang="en" dir="ltr" class="font-mono text-[11px]">SVG</span>
            ثم
            <span lang="en" dir="ltr" class="font-mono text-[11px]">SVG</span>.
            لتحديث الـ
            <span lang="en" dir="ltr" class="font-mono text-[11px]">WebP</span>:
            <span lang="en" dir="ltr" class="font-mono text-[11px]">npm run generate:showcase</span>.
            التكاملات الحكومية ومسارات الذكاء مذكورة في القسم التالي.
          </p>
        </details>
        <div
          ref="featureShowcaseSection"
          class="mt-8 grid gap-6 transition duration-[550ms] ease-out motion-safe:transition motion-safe:duration-[550ms] lg:grid-cols-12 lg:gap-8"
          :class="showcaseRevealActive ? 'translate-y-0 opacity-100' : 'opacity-0 motion-safe:translate-y-4'"
        >
          <fieldset class="border-0 p-0 lg:col-span-5">
            <legend class="sr-only">اختر ميزة لعرض المعاينة</legend>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:flex-col lg:flex-nowrap">
              <button
                v-for="item in featureShowcases"
                :key="item.id"
                type="button"
                class="landing-showcase-tab flex w-full items-center gap-3 rounded-2xl border px-4 py-3 text-start text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:min-w-[10rem] sm:flex-1 lg:min-w-0 lg:flex-none"
                :class="selectedShowcaseId === item.id
                  ? `border-transparent bg-gradient-to-l ${item.tabGradient} text-white shadow-lg ring-1 ring-white/20`
                  : 'border-slate-200 bg-white text-slate-800 hover:border-primary-400/40 dark:border-white/10 dark:bg-white/5 dark:text-slate-100 dark:hover:border-primary-500/40'"
                :aria-pressed="selectedShowcaseId === item.id"
                @click="onSelectShowcase(item.id)"
              >
                <component :is="item.icon" class="h-6 w-6 shrink-0 opacity-90" aria-hidden="true" />
                <span class="min-w-0">{{ item.label }}</span>
              </button>
            </div>
          </fieldset>
          <div class="lg:col-span-7">
            <Transition
              mode="out-in"
              enter-active-class="transition duration-300 ease-out motion-safe:transition motion-safe:duration-300"
              enter-from-class="opacity-0 motion-safe:translate-y-2"
              enter-to-class="opacity-100 motion-safe:translate-y-0"
              leave-active-class="transition duration-200 ease-in motion-safe:transition motion-safe:duration-200"
              leave-from-class="opacity-100"
              leave-to-class="opacity-0"
            >
              <article
                v-if="activeShowcase"
                :key="activeShowcase.id"
                class="landing-showcase-preview relative overflow-hidden rounded-2xl border border-slate-200/90 bg-slate-950 text-slate-100 shadow-xl shadow-slate-900/25 ring-1 ring-white/5 dark:border-white/10 dark:shadow-black/40"
                @mousemove="onShowcasePreviewMove"
                @mouseleave="onShowcasePreviewLeave"
              >
                <div
                  class="pointer-events-none absolute inset-0 opacity-70 transition-opacity duration-300"
                  :style="showcaseGlowStyle"
                  aria-hidden="true"
                />
                <div class="relative flex items-center gap-2 border-b border-white/10 bg-slate-900/90 px-4 py-3">
                  <span class="flex gap-1.5" aria-hidden="true">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-400/90" />
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400/90" />
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/90" />
                  </span>
                  <p class="ms-2 flex-1 truncate text-center text-[11px] font-medium text-slate-400">
                    أسس برو · {{ activeShowcase.mockChromeLabel }}
                  </p>
                </div>
                <div
                  v-if="showShowcaseImage"
                  class="relative w-full overflow-hidden border-b border-white/10 bg-slate-900"
                >
                  <picture>
                    <source :srcset="showcaseImageSrcPng" type="image/png" />
                    <source :srcset="showcaseImageSrcWebp" type="image/webp" />
                    <img
                      :src="showcaseImageSrcSvg"
                      :alt="activeShowcase.imageAlt"
                      width="1200"
                      height="750"
                      loading="lazy"
                      decoding="async"
                      fetchpriority="low"
                      class="aspect-[1200/750] w-full object-cover object-top"
                      @error="onShowcaseImageError"
                    />
                  </picture>
                </div>
                <div class="relative space-y-4 p-5">
                  <template v-if="showShowcaseImage">
                    <div>
                      <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ activeShowcase.hook }}</p>
                      <p class="mt-1 text-lg font-black text-white">{{ activeShowcase.mockTitle }}</p>
                      <p class="mt-1 text-sm leading-6 text-slate-400">{{ activeShowcase.mockSubtitle }}</p>
                    </div>
                    <ul class="flex flex-wrap gap-2 text-[11px] text-slate-400" role="list">
                      <li
                        v-for="b in activeShowcase.bullets"
                        :key="b"
                        class="rounded-lg border border-white/10 bg-slate-900/60 px-2 py-1"
                      >
                        {{ b }}
                      </li>
                    </ul>
                  </template>
                  <template v-else>
                    <div>
                      <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ activeShowcase.hook }}</p>
                      <p class="mt-1 text-lg font-black text-white">{{ activeShowcase.mockTitle }}</p>
                      <p class="mt-1 text-sm leading-6 text-slate-400">{{ activeShowcase.mockSubtitle }}</p>
                    </div>
                    <ul class="space-y-2" role="list">
                      <li
                        v-for="(row, ri) in activeShowcase.mockRows"
                        :key="ri"
                        class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5"
                      >
                        <div class="flex items-center justify-between gap-2 text-xs">
                          <span class="font-medium text-slate-200">{{ row.label }}</span>
                          <span class="tabular-nums text-slate-400">{{ row.value }}</span>
                        </div>
                        <div
                          v-if="row.pct != null"
                          class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800"
                        >
                          <div
                            class="landing-showcase-bar h-full rounded-full bg-gradient-to-l from-primary-400 to-teal-400"
                            :style="{ width: `${row.pct}%` }"
                          />
                        </div>
                      </li>
                    </ul>
                    <ul class="flex flex-wrap gap-2 border-t border-white/10 pt-4 text-[11px] text-slate-400" role="list">
                      <li
                        v-for="b in activeShowcase.bullets"
                        :key="b"
                        class="rounded-lg border border-white/10 bg-slate-900/60 px-2 py-1"
                      >
                        {{ b }}
                      </li>
                    </ul>
                  </template>
                </div>
              </article>
            </Transition>
          </div>
        </div>
        <p class="mx-auto mt-4 max-w-3xl text-center text-[11px] leading-5 text-slate-500 dark:text-slate-400">
          اللقطات الفعلية اختيارية (تُنشأ عبر capture:showcase)؛ وإلا تُستخدم أصول SVG/WebP التوضيحية.
          شكل الشاشات يختلف حسب الإصدار والصلاحيات؛ إذا تعذّر تحميل الصورة تُعرض المعاينة النصية.
        </p>
      </section>

      <section id="integrations-ecosystem" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">تكاملات، امتثال حكومي، وذكاء تشغيلي</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          أسس برو يوفّر مسارات لتشغيل نشاطك وربطه ببيئتك — مع الحفاظ على صياغة صادقة:
          نجاح التكامل يعتمد على <span class="font-semibold">إعداد شركتكم</span>،
          <span class="font-semibold">سياسات الأنظمة الخارجية</span>، و<span class="font-semibold">اشتراككم والصلاحيات</span>.
        </p>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <article
            v-for="pillar in integrationPillars"
            :key="pillar.title"
            class="landing-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 dark:shadow-none"
          >
            <p class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ pillar.title }}</p>
            <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ pillar.body }}</p>
          </article>
        </div>
        <p class="mx-auto mt-4 max-w-3xl text-center text-[11px] leading-5 text-slate-500 dark:text-slate-400">
          لا يُعتبر أي وصف أعلاه التزامًا بتكامل جاهز مع كل نظام ERP أو كل بوابة حكومية دون تهيئة وعقد واستشارة؛
          مسارات الفوترة الإلكترونية وZATCA تخضع لإعداداتكم وتفعيل مزوّد الخدمة والامتثال الفعلي لديكم.
        </p>
      </section>

      <section id="platform-atlas" class="relative mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-12">
        <div
          class="pointer-events-none absolute inset-x-0 -top-8 -z-10 h-64 bg-gradient-to-b from-primary-500/10 via-transparent to-transparent blur-3xl dark:from-primary-500/5"
          aria-hidden="true"
        />
        <h2 class="landing-section-title text-2xl font-black md:text-3xl">خريطة المنصة — ماذا يضم النظام؟</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          تصف المجموعات أدناه <span class="font-semibold text-slate-800 dark:text-slate-100">تقسيم القائمة الجانبية</span> داخل التطبيق
          (تشغيل، موارد بشرية، مالي، محاسبي، مخزون، تحليلات وذكاء أعمال، ثم الإعدادات والتكاملات).
          قد تُعرض بعض البنود أو تُخفى حسب <span class="font-semibold">خطة الاشتراك</span> و<span class="font-semibold">صلاحيات المستخدم</span> و<span class="font-semibold">إعداد الشركة</span>.
        </p>

        <!-- بطاقة مميزة: التشغيلي بعرض كامل ثم شبكة للمجموعات الأخرى -->
        <article
          v-if="atlasFeatured"
          class="landing-atlas-card group relative mt-8 flex flex-col overflow-hidden rounded-3xl border border-slate-200/90 bg-white/90 p-6 shadow-lg backdrop-blur-sm transition duration-300 dark:border-white/10 dark:bg-slate-900/50 dark:shadow-none"
          :class="atlasFeatured.cardGradient"
        >
          <div
            class="pointer-events-none absolute -start-10 -top-16 h-48 w-48 rounded-full opacity-35 blur-3xl transition duration-500 group-hover:opacity-55 motion-safe:transition"
            :class="atlasFeatured.glowClass"
            aria-hidden="true"
          />
          <div class="relative flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="flex items-start gap-3">
              <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-white shadow-xl ring-2 ring-white/30 dark:ring-white/10"
                :class="atlasFeatured.iconWrapClass"
              >
                <component :is="atlasFeatured.icon" class="h-7 w-7" aria-hidden="true" />
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-700/90 dark:text-primary-300/90">المجموعة الأوسع</p>
                <h3 class="mt-0.5 text-xl font-black text-slate-900 dark:text-white">{{ atlasFeatured.title }}</h3>
                <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-400">{{ atlasFeatured.caption }}</p>
              </div>
            </div>
          </div>
          <ul class="landing-atlas-tags relative mt-5 flex flex-wrap gap-2" role="list">
            <li
              v-for="item in atlasFeatured.items"
              :key="item"
              class="rounded-full border border-slate-200/90 bg-white/95 px-3 py-1.5 text-xs font-medium text-slate-800 shadow-sm dark:border-white/10 dark:bg-slate-800/95 dark:text-slate-100"
            >
              {{ item }}
            </li>
          </ul>
        </article>

        <div class="mt-4 grid auto-rows-fr gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="block in atlasRest"
            :key="block.id"
            class="landing-atlas-card group relative flex flex-col overflow-hidden rounded-3xl border border-slate-200/90 bg-white/90 p-5 shadow-md backdrop-blur-sm transition duration-300 motion-safe:hover:-translate-y-0.5 motion-safe:hover:shadow-lg dark:border-white/10 dark:bg-slate-900/50 dark:shadow-none"
            :class="block.cardGradient"
          >
            <div
              class="pointer-events-none absolute -start-6 -top-10 h-28 w-28 rounded-full opacity-35 blur-2xl transition duration-500 group-hover:opacity-60 motion-safe:transition"
              :class="block.glowClass"
              aria-hidden="true"
            />
            <div class="relative flex items-start gap-3">
              <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-md ring-2 ring-white/25 dark:ring-white/10"
                :class="block.iconWrapClass"
              >
                <component :is="block.icon" class="h-5 w-5" aria-hidden="true" />
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ block.title }}</h3>
                <p class="mt-0.5 text-[11px] leading-5 text-slate-600 dark:text-slate-400">{{ block.caption }}</p>
              </div>
            </div>
            <ul class="landing-atlas-tags relative mt-4 flex flex-wrap gap-1.5" role="list">
              <li
                v-for="item in block.items"
                :key="item"
                class="rounded-full border border-slate-200/90 bg-white/90 px-2.5 py-1 text-[11px] font-medium leading-snug text-slate-700 shadow-sm dark:border-white/10 dark:bg-slate-800/90 dark:text-slate-200"
              >
                {{ item }}
              </li>
            </ul>
          </article>
        </div>
        <p class="mx-auto mt-5 max-w-3xl text-center text-[11px] leading-5 text-slate-500 dark:text-slate-400">
          الأسماء أعلاه للتعريف بالنطاق؛ قد يختلف الترتيب أو التسمية بين إصدارات الواجهة. التحليلات وذكاء الأعمال يشمل مسارات مثل التقارير وذكاء الأعمال ومركز العمليات حيث تُفعّل لحسابكم.
        </p>
      </section>

      <section id="smart-compare" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">قبل وبعد — اختر أكبر تحدٍ لديك</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          السرد أدناه <span class="font-semibold text-slate-800 dark:text-slate-200">توضيحي ومبسّط</span> لمساعدتك على تصوّر التدفق؛
          لا يمثل التزامًا بنتائج محددة ويختلف التطبيق الفعلي حسب نشاطك وطريقة التشغيل.
        </p>
        <fieldset class="mt-5 border-0 p-0">
          <legend class="sr-only">اختر نوع التحدي الأقرب لوضعك</legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="opt in painOptions"
              :key="opt.id"
              type="button"
              class="rounded-xl border px-3 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:text-sm"
              :class="selectedPainId === opt.id
                ? 'border-primary-500 bg-primary-500/15 text-primary-900 dark:text-primary-100'
                : 'border-slate-300 bg-white text-slate-700 hover:border-primary-400/50 dark:border-white/15 dark:bg-white/5 dark:text-slate-200'"
              :aria-pressed="selectedPainId === opt.id"
              @click="onSelectPain(opt.id)"
            >
              {{ opt.label }}
            </button>
          </div>
        </fieldset>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
          <article class="rounded-2xl border border-red-200/90 bg-red-50/90 p-5 dark:border-red-900/45 dark:bg-red-950/25">
            <p class="text-sm font-bold text-red-800 dark:text-red-300">قبل — سرد تشغيلي مبسّط</p>
            <ul class="mt-3 space-y-2.5 text-sm leading-6 text-slate-800 dark:text-slate-300">
              <li v-for="(point, idx) in currentPainStory.before" :key="'b' + idx" class="flex gap-2">
                <span class="text-red-500 dark:text-red-400" aria-hidden="true">–</span>
                <span>{{ point }}</span>
              </li>
            </ul>
          </article>
          <article class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 p-5 dark:border-emerald-900/45 dark:bg-emerald-950/25">
            <p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">بعد — مع منصة موحّدة (نموذج)</p>
            <ul class="mt-3 space-y-2.5 text-sm leading-6 text-slate-800 dark:text-slate-300">
              <li v-for="(point, idx) in currentPainStory.after" :key="'a' + idx" class="flex gap-2">
                <span class="text-emerald-600 dark:text-emerald-400" aria-hidden="true">+</span>
                <span>{{ point }}</span>
              </li>
            </ul>
          </article>
        </div>
      </section>

      <section id="honesty" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">بصراحة: ماذا ليس أسس برو؟</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          نفضّل وضوح المنطقة التي نخدمها على وعدٍ عام لا يناسب الجميع.
        </p>
        <ul
          class="mt-5 max-w-3xl space-y-3 rounded-2xl border border-amber-200/80 bg-amber-50/50 p-5 text-sm leading-7 text-slate-800 dark:border-amber-900/35 dark:bg-amber-950/20 dark:text-slate-200"
          role="list"
        >
          <li v-for="line in antiClaims" :key="line" class="flex gap-3">
            <span class="font-bold text-amber-800 dark:text-amber-300" aria-hidden="true">•</span>
            <span>{{ line }}</span>
          </li>
        </ul>
      </section>

      <section id="use-cases" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">حلول حسب نوع نشاطك</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          صُممت المنصة لتكون متعددة الأنشطة: يمكن تكييف التدفقات مع نموذج عملك أو مع مزيج من أكثر من نشاط دون تقسيم بياناتك على عدة أدوات.
        </p>
        <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <article v-for="item in useCases" :key="item.title" class="landing-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5 dark:shadow-none">
            <p class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ item.title }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ item.body }}</p>
          </article>
        </div>
      </section>

      <section id="compliance" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-6">
        <h2 class="landing-section-title text-xl font-black md:text-2xl">امتثال وتشغيل — بصياغة عملية</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          ما يلي يصف اتجاهًا عامًا داخل المنصة؛ التفاصيل الفعلية تعتمد على <span class="font-semibold">إعداد شركتكم</span>،
          <span class="font-semibold">اشتراككم</span>، و<strong class="font-semibold">استشارتكم المحاسبية أو القانونية</strong> عند الحاجة.
        </p>
        <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/40">
          <table class="w-full min-w-[280px] border-collapse text-start text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50/90 dark:border-white/10 dark:bg-slate-800/50">
                <th class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">المجال</th>
                <th class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">داخل أسس برو (حسب الإعداد)</th>
                <th class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">تذكير مهم</th>
              </tr>
            </thead>
            <tbody class="text-slate-700 dark:text-slate-300">
              <tr
                v-for="row in complianceRows"
                :key="row.area"
                class="border-b border-slate-100 last:border-0 dark:border-white/5"
              >
                <td class="px-4 py-3 align-top font-semibold text-slate-900 dark:text-slate-100">{{ row.area }}</td>
                <td class="px-4 py-3 align-top leading-6">{{ row.product }}</td>
                <td class="px-4 py-3 align-top text-xs leading-5 text-slate-600 dark:text-slate-400">{{ row.note }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="pricing" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">{{ landingPlans.section_title }}</h2>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
          {{ landingPlans.section_subtitle }}
        </p>
        <p v-if="landingPlansLoadError" class="mt-4 text-xs text-amber-800 dark:text-amber-200">
          تعذّر تحميل الباقات من الخادم — يُعرض نموذج افتراضي. {{ landingPlansLoadError }}
        </p>
        <div
          v-if="landingPlansLoading"
          class="mt-8 grid gap-4 md:grid-cols-3"
          aria-busy="true"
        >
          <div
            v-for="sk in 3"
            :key="'pl-sk-' + sk"
            class="h-64 animate-pulse rounded-2xl border border-slate-200 bg-slate-100/80 dark:border-white/10 dark:bg-slate-800/50"
          />
        </div>
        <div v-else class="mt-8 grid gap-4 md:grid-cols-3">
          <article
            v-for="plan in landingPlans.plans"
            :key="plan.id"
            class="landing-card relative flex flex-col rounded-2xl border p-5 shadow-sm dark:shadow-none"
            :class="plan.highlight
              ? 'border-primary-400/70 bg-gradient-to-b from-primary-50/90 to-white ring-2 ring-primary-500/25 dark:border-primary-500/40 dark:from-primary-950/40 dark:to-slate-900/80 dark:ring-primary-500/20'
              : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/5'"
          >
            <p
              v-if="plan.highlight"
              class="absolute -top-2.5 start-4 rounded-full bg-primary-600 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-sm"
            >
              الأكثر طلباً
            </p>
            <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ plan.name }}</h3>
            <p class="mt-2 text-2xl font-black text-primary-700 dark:text-primary-300">
              {{ plan.price_label }}
              <span v-if="plan.period" class="text-sm font-semibold text-slate-500 dark:text-slate-400">/ {{ plan.period }}</span>
            </p>
            <ul class="mt-4 flex-1 space-y-2 text-sm text-slate-600 dark:text-slate-300" role="list">
              <li v-for="(f, fi) in plan.features" :key="plan.id + '-f-' + fi" class="flex gap-2">
                <CheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" aria-hidden="true" />
                <span>{{ f }}</span>
              </li>
            </ul>
            <div class="mt-6">
              <RouterLink
                v-if="planCtaIsInternal(plan.cta_href)"
                :to="plan.cta_href"
                class="flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @click="trackLandingCta('landing_plan_cta')"
              >
                {{ plan.cta }}
              </RouterLink>
              <a
                v-else
                :href="plan.cta_href"
                class="flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-800 transition hover:border-primary-400 hover:bg-primary-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/15 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                @click="trackLandingCta('landing_plan_cta')"
              >
                {{ plan.cta }}
              </a>
            </div>
          </article>
        </div>
        <div
          id="contact"
          class="mt-8 rounded-2xl border border-slate-200/80 bg-slate-50/90 p-4 text-center text-xs leading-relaxed text-slate-600 dark:border-white/10 dark:bg-slate-800/40 dark:text-slate-300"
        >
          <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">
            الباقات أعلاه موجزة للزائر؛ العروض الرسمية بالأرقام والحدود التفصيلية تُزوَّد عبر المبيعات أو مستند البرشور الداخلي.
          </p>
          للأسعار التفصيلية أو عقد مخصّص:
          <a
            :href="salesMailtoHref"
            class="font-semibold text-primary-700 underline dark:text-primary-300"
            @click="trackLandingCta('landing_pricing_contact_mail')"
          >
            {{ SALES_EMAIL }}
          </a>
        </div>
      </section>

      <section id="roi" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">احسب الأثر المتوقع لنشاطك</h2>
        <div class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 md:grid-cols-2">
          <div class="space-y-4">
            <label class="block">
              <span class="text-xs text-slate-500 dark:text-slate-400">عدد العمليات الشهرية</span>
              <input
                v-model.number="roiInputs.monthlyOrders"
                type="number"
                min="1"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </label>
            <label class="block">
              <span class="text-xs text-slate-500 dark:text-slate-400">متوسط وقت العملية (بالدقائق)</span>
              <input
                v-model.number="roiInputs.avgMinutes"
                type="number"
                min="1"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </label>
            <label class="block">
              <span class="text-xs text-slate-500 dark:text-slate-400">متوسط تكلفة ساعة الفريق (ر.س)</span>
              <input
                v-model.number="roiInputs.hourlyCost"
                type="number"
                min="1"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </label>
          </div>

          <div class="rounded-xl border border-primary-500/30 bg-primary-500/10 p-4" aria-live="polite">
            <p class="text-xs text-slate-600 dark:text-slate-300">تقدير التحسين الشهري</p>
            <p class="mt-2 text-3xl font-black text-primary-700 dark:text-primary-300">
              {{ roiEstimate.savedAmountSar.toLocaleString('en-US') }} ر.س
            </p>
            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
              يوفر تقريبًا {{ roiEstimate.savedHours.toLocaleString('en-US') }} ساعة تشغيل شهريًا.
            </p>
            <p class="mt-3 text-[11px] leading-5 text-slate-500 dark:text-slate-400">
              تقدير توضيحي مبني على خفض {{ roiAssumptions.efficiencyGainPercent }}% من وقت التنفيذ؛
              النتائج الفعلية تختلف حسب نوع النشاط ومرحلة التطبيق.
            </p>
            <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
              تُحفظ مدخلاتك على هذا الجهاز لتعود عند زيارة الصفحة لاحقًا.
            </p>
            <a
              :href="roiMailtoHref"
              class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-primary-500/50 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-primary-500/40 dark:bg-slate-900 dark:text-primary-300 dark:hover:bg-slate-800"
              @click="trackLandingCta('landing_cta_roi_email')"
            >
              أرسل النتيجة لفريق المبيعات
            </a>
          </div>
        </div>
      </section>

      <section class="mx-auto w-full max-w-6xl px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">ما يقوله عملاؤنا</h2>
        <p class="mt-2 max-w-3xl text-xs text-slate-500 dark:text-slate-400">
          الشهادات المعروضة نماذج سردية توضيحية ولا تدل بالضرورة على عملاء فعليين أو نتائج مضمونة.
        </p>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <article v-for="item in testimonials" :key="item.name" class="landing-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5 dark:shadow-none">
            <p class="text-sm leading-7 text-slate-700 dark:text-slate-200">"{{ item.quote }}"</p>
            <p class="mt-3 text-xs font-bold text-primary-700 dark:text-primary-300">{{ item.name }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.role }}</p>
          </article>
        </div>
      </section>

      <section id="faq" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-10">
        <h2 class="landing-section-title text-2xl font-black">الأسئلة الشائعة</h2>
        <div class="mt-6 space-y-3">
          <details v-for="item in faqs" :key="item.q" class="landing-faq rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <summary class="cursor-pointer list-none text-sm font-bold text-slate-800 dark:text-slate-100">{{ item.q }}</summary>
            <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ item.a }}</p>
          </details>
        </div>
      </section>

      <section id="continuity" class="mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-8">
        <h2 class="landing-section-title text-xl font-black md:text-2xl">استمرارية التبني — بدون مبالغة</h2>
        <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
          نؤمن أن القرار التشغيلي يستحق وضوحًا في التوقعات؛ هذه نقاط عامة وليست عقد خدمة.
        </p>
        <ul class="mt-5 grid gap-3 md:grid-cols-3">
          <li
            v-for="item in continuityPoints"
            :key="item.title"
            class="landing-card rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
          >
            <p class="font-bold text-primary-800 dark:text-primary-300">{{ item.title }}</p>
            <p class="mt-2">{{ item.body }}</p>
          </li>
        </ul>
      </section>

      <section class="mx-auto w-full max-w-6xl px-4 py-10">
        <div
          class="rounded-2xl border border-primary-500/35 bg-gradient-to-b from-primary-500/15 to-primary-500/5 p-6 text-center shadow-lg shadow-primary-900/5 ring-1 ring-primary-500/20 dark:from-primary-500/10 dark:to-transparent dark:shadow-black/20"
        >
          <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">جاهز تنقل نشاطك للمستوى التالي؟</h3>
          <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-700 dark:text-slate-300">
            ابدأ خلال دقائق، وامنح فريقك منصة تشغيل واضحة من أول عملية حتى التقارير التنفيذية.
          </p>
          <div class="mt-5 flex justify-center gap-3">
            <RouterLink
              to="/login"
              class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              @click="trackLandingCta('landing_cta_login_footer')"
            >
              ابدأ تجربة عملية الآن
            </RouterLink>
            <a
              href="mailto:sales@asaspro.sa?subject=%D8%B7%D9%84%D8%A8%20%D8%B9%D8%B1%D8%B6%20%D8%AA%D8%AC%D8%B1%D9%8A%D8%A8%D9%8A%20-%20%D8%A3%D8%B3%D8%B3%20%D8%A8%D8%B1%D9%88"
              class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/20 dark:bg-transparent dark:text-slate-100 dark:hover:bg-white/5"
              @click="trackLandingCta('landing_cta_book_demo_footer')"
            >
              احجز عرضًا مباشرًا 15 دقيقة
            </a>
          </div>
        </div>
      </section>

      <section class="mx-auto w-full max-w-2xl px-4 pb-12" aria-label="تثبيت المنصة على الجهاز">
        <AppInstallHint />
      </section>
    </main>

    <div
      class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-gradient-to-t from-white via-white/98 to-white/90 px-3 py-3 shadow-[0_-10px_40px_rgba(15,23,42,0.07)] backdrop-blur-md md:hidden dark:border-white/10 dark:from-slate-950 dark:via-slate-950/98 dark:to-slate-950/90 dark:shadow-[0_-10px_40px_rgba(0,0,0,0.4)]"
      style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
    >
      <div class="mx-auto flex max-w-6xl gap-2">
        <RouterLink
          to="/login"
          class="flex flex-1 items-center justify-center rounded-xl bg-primary-600 px-3 py-3 text-center text-sm font-bold text-white hover:bg-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          @click="trackLandingCta('landing_cta_login_mobile_bar')"
        >
          ابدأ تجربة عملية الآن
        </RouterLink>
        <a
          href="mailto:sales@asaspro.sa?subject=%D8%B7%D9%84%D8%A8%20%D8%B9%D8%B1%D8%B6%20%D8%AA%D8%AC%D8%B1%D9%8A%D8%A8%D9%8A%20-%20%D8%A3%D8%B3%D8%B3%20%D8%A8%D8%B1%D9%88"
          aria-label="حجز عرض مباشر لمدة 15 دقيقة"
          class="flex shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-xs font-semibold text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
          @click="trackLandingCta('landing_cta_book_demo_mobile')"
        >
          عرض 15 د
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckIcon } from '@heroicons/vue/24/solid'
import {
  BanknotesIcon,
  BookOpenIcon,
  ChartBarIcon,
  ClipboardDocumentListIcon,
  Cog6ToothIcon,
  CubeIcon,
  DocumentTextIcon,
  HomeIcon,
  MoonIcon,
  ShoppingCartIcon,
  SparklesIcon,
  SunIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import { useIntersectionObserver } from '@vueuse/core'
import { APP_VERSION } from '@/config/appRelease'
import { useDarkMode } from '@/composables/useDarkMode'
import { trackLandingCta } from '@/utils/landingAnalytics'
import apiClient from '@/lib/apiClient'
import AppInstallHint from '@/components/AppInstallHint.vue'
import PlatformPromoBanner from '@/components/PlatformPromoBanner.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const LANDING_PAGE_TITLE = 'أسس برو | منصة تشغيل أعمال ذكية'
/** يُفضّل مواءَمته مع حقن nginx لوسوم head في docker/nginx/conf.d/default.conf (مسارات الهبوط) */
const LANDING_META_DESCRIPTION =
  'أسس برو: منصة SaaS عربية تجمع التشغيل والمبيعات والمخزون والمالية والتقارير في تدفق واحد — أقل تشتتًا، قرارات أسرع، وتجربة فرق حقيقية. جرّب المنصة أو احجز عرضًا قصيرًا.'
const APP_DEFAULT_TITLE = 'نظام POS'
const ROI_STORAGE_KEY = 'osaspro_landing_roi_v1'
const SALES_EMAIL = 'sales@asaspro.sa'
/** لإزالة وسوم OG و canonical عند مغادرة الصفحة فقط */
const LANDING_HEAD_MARK = 'landing-head-osaspro'

const darkMode = useDarkMode()

const scrollProgressPct = ref(0)

const headerNavLinks: Array<{
  href: string
  label: string
  event:
    | 'landing_nav_header_features'
    | 'landing_nav_header_atlas'
    | 'landing_nav_header_compare'
    | 'landing_nav_header_pricing'
    | 'landing_nav_header_roi'
    | 'landing_nav_header_faq'
}> = [
  { href: '#features', label: 'المزايا', event: 'landing_nav_header_features' },
  { href: '#platform-atlas', label: 'المنصة', event: 'landing_nav_header_atlas' },
  { href: '#smart-compare', label: 'قبل وبعد', event: 'landing_nav_header_compare' },
  { href: '#pricing', label: 'الباقات', event: 'landing_nav_header_pricing' },
  { href: '#roi', label: 'الأثر', event: 'landing_nav_header_roi' },
  { href: '#faq', label: 'أسئلة', event: 'landing_nav_header_faq' },
]

interface LandingPlanItem {
  id: string
  name: string
  price_label: string
  period: string
  highlight: boolean
  features: string[]
  cta: string
  cta_href: string
}

const DEFAULT_LANDING_PLANS: {
  section_title: string
  section_subtitle: string
  plans: LandingPlanItem[]
} = {
  section_title: 'باقات مرنة تناسب نموك',
  section_subtitle: 'ابدأ بالتجربة ثم انتقل للباقة التي تناسب فروعك وفريقك.',
  plans: [
    {
      id: 'trial',
      name: 'تجريبي',
      price_label: 'مجاناً',
      period: '14 يوماً',
      highlight: false,
      features: ['حتى 3 مستخدمين', 'فرع واحد', 'أوامر عمل ومخزون أساسي'],
      cta: 'ابدأ التجربة',
      cta_href: '/login',
    },
    {
      id: 'professional',
      name: 'احترافي',
      price_label: 'حسب العقد',
      period: 'سنوياً',
      highlight: true,
      features: ['فروع متعددة', 'تقارير وتكاملات', 'دعم أولوية'],
      cta: 'تواصل مع المبيعات',
      cta_href: '#contact',
    },
    {
      id: 'enterprise',
      name: 'مؤسسات',
      price_label: 'مخصص',
      period: '',
      highlight: false,
      features: ['حوكمة وتدقيق', 'SLA مخصص', 'تكاملات على المقاس'],
      cta: 'طلب عرض',
      cta_href: '#contact',
    },
  ],
}

const landingPlans = ref({ ...DEFAULT_LANDING_PLANS, plans: [...DEFAULT_LANDING_PLANS.plans] })
const landingPlansLoading = ref(true)
const landingPlansLoadError = ref('')

function planCtaIsInternal(href: string): boolean {
  return href.startsWith('/') && !href.startsWith('//')
}

async function fetchLandingPlans(): Promise<void> {
  landingPlansLoadError.value = ''
  landingPlansLoading.value = true
  try {
    const { data } = await apiClient.get<{ data?: Record<string, unknown> }>('/public/landing-plans')
    const d = data?.data
    const plansRaw = d?.plans
    if (Array.isArray(plansRaw) && plansRaw.length > 0) {
      const plans = plansRaw as LandingPlanItem[]
      landingPlans.value = {
        section_title: typeof d?.section_title === 'string' ? d.section_title : DEFAULT_LANDING_PLANS.section_title,
        section_subtitle:
          typeof d?.section_subtitle === 'string' ? d.section_subtitle : DEFAULT_LANDING_PLANS.section_subtitle,
        plans,
      }
    }
  } catch {
    landingPlansLoadError.value = 'تعذّر الاتصال بالخادم.'
  } finally {
    landingPlansLoading.value = false
  }
}

const trustChips = [
  'واجهة عربية كاملة',
  'فروع وصلاحيات مرنة',
  'SaaS جاهز للتشغيل',
  'امتثال وتكامل حسب إعدادك',
]

function updateLandingScrollProgress(): void {
  if (typeof document === 'undefined') return
  const el = document.documentElement
  const scrollable = el.scrollHeight - el.clientHeight
  scrollProgressPct.value = scrollable > 0 ? Math.min(100, (el.scrollTop / scrollable) * 100) : 0
}

function onExploreDetailsToggle(e: Event): void {
  const t = e.target as HTMLDetailsElement
  if (t.open) trackLandingCta('landing_nav_hero_explore_open')
}

function readPrefersReducedMotion(): boolean {
  if (typeof window === 'undefined') return false
  try {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
  } catch {
    return false
  }
}

type ShowcaseId = 'pos' | 'workorder' | 'inventory' | 'reports' | 'billing' | 'intelligence'

function showcaseStemUrl(stem: string, ext: string): string {
  const base = import.meta.env.BASE_URL || '/'
  const norm = base.endsWith('/') ? base : `${base}/`
  return `${norm}landing/showcase/${stem}.${ext}`
}

const integrationPillars = [
  {
    title: 'تكامل مع أنظمة الشركة',
    body:
      'من إعدادات المنصة: تكاملات وواجهات برمجية ومفاتيح API حيث تدعم خطتكم، لربط التشغيل بأنظمة محاسبة أو ERP أو قنوات بيع — حسب التوفر والتهيئة وليس كوعدة عامة لكل نظام دون ضبط.',
  },
  {
    title: 'مسارات جهات حكومية وامتثال',
    body:
      'مسارات فوترة ومتطلبات ضريبية يمكن ضبطها لدعم الفوترة الإلكترونية ومتطلبات مثل ZATCA عند التفعيل في حسابكم؛ الامتثال النهائي يظل مسؤولية إعدادكم ومستشاريكم والجهات الرسمية المعنية.',
  },
  {
    title: 'ذكاء تشغيلي وتحليلات',
    body:
      'لوحات وتنبيهات ومركز قيادة داخلي (حسب الاشتراك والصلاحيات) يبني على نشاطكم الفعلي داخل المنصة — أداة قرار مساعدة وليست بديلاً عن حوكمة بياناتكم أو استشارة متخصصة عند الحاجة.',
  },
]

/** يعكس تجميع القائمة الجانبية في AppLayout (مع تبسيط عرضي للهبوط) */
const platformAtlasSections: Array<{
  id: string
  title: string
  caption: string
  icon: typeof HomeIcon
  items: string[]
  cardGradient: string
  glowClass: string
  iconWrapClass: string
}> = [
  {
    id: 'operations',
    title: 'تشغيلي',
    caption: 'قلب التشغيل اليومي: لوحة، مبيعات، صيانة، حجوزات، عملاء ومركبات ومسارات أسطول.',
    icon: HomeIcon,
    items: [
      'لوحة التحكم',
      'نقطة البيع',
      'أوامر العمل',
      'مناطق العمل',
      'الحجوزات',
      'الخريطة الحرارية',
      'العملاء',
      'عروض الأسعار',
      'علاقات العملاء',
      'المركبات',
      'التحقق من اللوحة (أسطول)',
      'محافظ الأسطول',
    ],
    cardGradient: 'bg-gradient-to-br from-primary-500/12 via-white to-teal-500/10 dark:from-primary-500/10 dark:via-slate-900/90 dark:to-teal-950/30',
    glowClass: 'bg-primary-500',
    iconWrapClass: 'bg-gradient-to-br from-primary-600 to-teal-600',
  },
  {
    id: 'hr',
    title: 'الموارد البشرية',
    caption: 'حضور ورواتب وعمولات وأرشفة واتصالات إدارية ضمن مسارات الموظفين.',
    icon: UserGroupIcon,
    items: [
      'إدارة الموظفين',
      'إدارة المهام',
      'الحضور',
      'الإجازات',
      'مسير الرواتب',
      'العمولات',
      'سياسات العمولات',
      'اتصالات إدارية',
      'أرشفة إلكترونية',
      'توقيع إلكتروني',
      'حماية الأجور (WPS)',
    ],
    cardGradient: 'bg-gradient-to-br from-rose-500/10 via-white to-amber-500/8 dark:from-rose-900/20 dark:via-slate-900/90 dark:to-amber-950/25',
    glowClass: 'bg-rose-500',
    iconWrapClass: 'bg-gradient-to-br from-rose-600 to-amber-600',
  },
  {
    id: 'finance',
    title: 'مالي',
    caption: 'فواتير ومحفظة ومشتريات وموردين في تدفق مالي واحد.',
    icon: BanknotesIcon,
    items: ['الفواتير', 'المحفظة', 'قائمة المشتريات', 'أوامر شراء', 'الموردون'],
    cardGradient: 'bg-gradient-to-br from-emerald-500/10 via-white to-green-600/8 dark:from-emerald-900/20 dark:via-slate-900/90 dark:to-green-950/20',
    glowClass: 'bg-emerald-500',
    iconWrapClass: 'bg-gradient-to-br from-emerald-600 to-green-700',
  },
  {
    id: 'accounting',
    title: 'محاسبي',
    caption: 'دفتر وقيود وضريبة وأصول، مع مكتبة أنظمة عمل للامتثال التشغيلي.',
    icon: BookOpenIcon,
    items: ['القيود اليومية', 'شجرة الحسابات', 'الضرائب / ZATCA', 'الأصول الثابتة', 'مكتبة أنظمة العمل'],
    cardGradient: 'bg-gradient-to-br from-slate-500/12 via-white to-slate-700/10 dark:from-slate-700/25 dark:via-slate-900/90 dark:to-slate-950/40',
    glowClass: 'bg-slate-500',
    iconWrapClass: 'bg-gradient-to-br from-slate-600 to-slate-800',
  },
  {
    id: 'inventory',
    title: 'المخزون',
    caption: 'منتجات ومستودع وموردون مرتبطون بالبيع والمشتريات.',
    icon: CubeIcon,
    items: ['المنتجات', 'المخزون', 'الموردون'],
    cardGradient: 'bg-gradient-to-br from-cyan-500/10 via-white to-blue-500/8 dark:from-cyan-900/20 dark:via-slate-900/90 dark:to-blue-950/25',
    glowClass: 'bg-cyan-500',
    iconWrapClass: 'bg-gradient-to-br from-cyan-600 to-blue-700',
  },
  {
    id: 'analytics',
    title: 'التحليلات وذكاء الأعمال',
    caption: 'ذكاء أعمال، تقارير، حوكمة، ومركز عمليات ذكي عند تفعيله لحسابكم.',
    icon: ChartBarIcon,
    items: ['ذكاء الأعمال', 'التقارير', 'السياسات والموافقات', 'مركز العمليات الذكي'],
    cardGradient: 'bg-gradient-to-br from-violet-500/12 via-white to-fuchsia-500/10 dark:from-violet-900/25 dark:via-slate-900/90 dark:to-fuchsia-950/30',
    glowClass: 'bg-violet-500',
    iconWrapClass: 'bg-gradient-to-br from-violet-600 to-fuchsia-600',
  },
  {
    id: 'settings',
    title: 'إداري، إعدادات وتكاملات',
    caption: 'فروع ومستندات ودعم، مع إعدادات المنشأة والتكاملات ومفاتيح API والاشتراك.',
    icon: Cog6ToothIcon,
    items: [
      'إدارة الفروع',
      'خريطة الفروع',
      'العقود',
      'مستندات المنشأة',
      'سجل العمليات',
      'الإعدادات',
      'التكاملات',
      'مفاتيح API',
      'الإحالات والولاء',
      'مركز الدعم',
      'الاشتراك والباقات',
      'سوق الإضافات',
      'لوحة الأدمن (للمالك)',
      'التحقق من النظام (QA)',
    ],
    cardGradient: 'bg-gradient-to-br from-amber-500/10 via-white to-orange-500/8 dark:from-amber-900/20 dark:via-slate-900/90 dark:to-orange-950/25',
    glowClass: 'bg-amber-500',
    iconWrapClass: 'bg-gradient-to-br from-amber-600 to-orange-700',
  },
]

const atlasFeatured = computed(() => platformAtlasSections[0])
const atlasRest = computed(() => platformAtlasSections.slice(1))

const featureShowcases: Array<{
  id: ShowcaseId
  label: string
  hook: string
  bullets: string[]
  tabGradient: string
  glowRgb: string
  mockChromeLabel: string
  mockTitle: string
  mockSubtitle: string
  mockRows: { label: string; value: string; pct?: number }[]
  icon: typeof ShoppingCartIcon
  /** جذر الملفات في public/landing/showcase: {stem}.png | .webp | .svg */
  screenshotStem: string
  imageAlt: string
}> = [
  {
    id: 'pos',
    label: 'نقطة البيع والجبهة',
    hook: 'سرعة صندوق أقل أخطاء',
    bullets: ['فاتورة سريعة', 'دفع وترجيع', 'مخزون لحظي'],
    tabGradient: 'from-emerald-600 to-teal-600',
    glowRgb: '16, 185, 129',
    mockChromeLabel: 'نقطة البيع',
    mockTitle: 'شيفت اليوم — فرع الرياض',
    mockSubtitle: 'حركة لحظية مع تطابق المخزون عند كل عملية بيع.',
    mockRows: [
      { label: 'إغلاق آلية', value: 'نشط', pct: 100 },
      { label: 'متوسط زمن الفاتورة', value: '42 ث', pct: 78 },
      { label: 'تنبيهات حد أدنى', value: '3 أصناف', pct: 40 },
    ],
    icon: ShoppingCartIcon,
    screenshotStem: 'pos',
    imageAlt: 'لقطة أو معاينة لواجهة نقطة البيع في أسس برو',
  },
  {
    id: 'workorder',
    label: 'أوامر العمل والصيانة',
    hook: 'شفافية حالة المركبة',
    bullets: ['تتبع مراحل', 'أجزاء وخدمات', 'إشعار للعميل'],
    tabGradient: 'from-sky-600 to-indigo-600',
    glowRgb: '14, 165, 233',
    mockChromeLabel: 'أوامر العمل',
    mockTitle: 'أمر عمل #2841 — جاري الفحص',
    mockSubtitle: 'كل الفنيّين يرون آخر تحديث دون مكالمات متكررة.',
    mockRows: [
      { label: 'المرحلة الحالية', value: 'فحص', pct: 55 },
      { label: 'قطع مفسرة', value: 'معتمد', pct: 88 },
      { label: 'جاهز للتسليم', value: 'متوقع — اليوم', pct: 72 },
    ],
    icon: ClipboardDocumentListIcon,
    screenshotStem: 'workorder',
    imageAlt: 'لقطة أو معاينة لواجهة أوامر العمل في أسس برو',
  },
  {
    id: 'inventory',
    label: 'مخزون ومشتريات',
    hook: 'توفير بدون نفاد صامت',
    bullets: ['حركات مترابطة', 'جرد وأذون', 'موردون'],
    tabGradient: 'from-amber-600 to-orange-600',
    glowRgb: '245, 158, 11',
    mockChromeLabel: 'المخزون',
    mockTitle: 'مستودع مركزي — صافي التوفر',
    mockSubtitle: 'وصلات المشتريات والبيع تغذّي نفس الأرقام.',
    mockRows: [
      { label: 'تغطية متوسطة', value: '28 يومًا', pct: 65 },
      { label: 'أصناف تحت حد الطلب', value: '12', pct: 35 },
      { label: 'جاهز للجرد الدوري', value: 'قسم ب', pct: 90 },
    ],
    icon: CubeIcon,
    screenshotStem: 'inventory',
    imageAlt: 'لقطة أو معاينة لواجهة المخزون في أسس برو',
  },
  {
    id: 'reports',
    label: 'تقارير ولوحات',
    hook: 'قرار من النشاط لا من الجداول',
    bullets: ['فروع', 'هامش', 'MRR تشغيلي'],
    tabGradient: 'from-violet-600 to-fuchsia-600',
    glowRgb: '167, 139, 250',
    mockChromeLabel: 'التقارير',
    mockTitle: 'ملخص الإدارة — هذا الأسبوع',
    mockSubtitle: 'مؤشرات مبنية على نفس حركات الفريق اليومية.',
    mockRows: [
      { label: 'صافي هامش تقديري', value: '24%', pct: 70 },
      { label: 'أعباء تشغيلية', value: '↓  مقارنةً بالأسبوع الماضي', pct: 62 },
      { label: 'مراجعة فروع', value: '4 من 4', pct: 100 },
    ],
    icon: ChartBarIcon,
    screenshotStem: 'reports',
    imageAlt: 'لقطة أو معاينة لواجهة التقارير في أسس برو',
  },
  {
    id: 'billing',
    label: 'فوترة وامتثال',
    hook: 'مسارات فوترة قابلة للضبط',
    bullets: ['ZATCA عند التفعيل', 'صلاحيات', 'تتبع إصدارات'],
    tabGradient: 'from-slate-600 to-slate-800 dark:from-slate-500 dark:to-slate-700',
    glowRgb: '148, 163, 184',
    mockChromeLabel: 'الفوترة',
    mockTitle: 'مسودة فاتورة ضريبية',
    mockSubtitle: 'يرتبط الإعداد بسياسة شركتكم ومستشاركم؛ المعاينة توضيحية.',
    mockRows: [
      { label: 'حالة الإرسال', value: 'جاهز للمراجعة', pct: 85 },
      { label: 'تطابق البنود', value: 'مكتمل', pct: 100 },
      { label: 'سجل التدقيق', value: '3 أحداث', pct: 45 },
    ],
    icon: DocumentTextIcon,
    screenshotStem: 'billing',
    imageAlt: 'لقطة أو معاينة لواجهة الفوترة والزكاة والضريبة في أسس برو',
  },
  {
    id: 'intelligence',
    label: 'ذكاء تشغيلي ومركز القيادة',
    hook: 'من الأحداث إلى قرار أوضح',
    bullets: ['تنبيهات مرحلية', 'مؤشرات تشغيلية', 'حسب الاشتراك'],
    tabGradient: 'from-indigo-600 to-violet-700',
    glowRgb: '99, 102, 241',
    mockChromeLabel: 'مركز القيادة',
    mockTitle: 'ملخص التنبيهات — اليوم',
    mockSubtitle: 'يُبنى على نشاط فريقك داخل المنصة؛ الإتاحة تعتمد على الإعداد.',
    mockRows: [
      { label: 'أولوية عالية', value: '4 عناصر', pct: 72 },
      { label: 'مزامنة البيانات', value: 'مكتملة', pct: 100 },
      { label: 'آخر تحديث', value: 'منذ 3 دقائق', pct: 88 },
    ],
    icon: SparklesIcon,
    screenshotStem: 'intelligence',
    imageAlt: 'لقطة أو معاينة لواجهة مركز القيادة والذكاء التشغيلي في أسس برو',
  },
]

const selectedShowcaseId = ref<ShowcaseId>('pos')
const showcaseImageFailed = ref(false)

const activeShowcase = computed(() => {
  return featureShowcases.find((f) => f.id === selectedShowcaseId.value) ?? featureShowcases[0]
})

const showcaseImageSrcPng = computed(() => {
  const stem = activeShowcase.value?.screenshotStem
  return stem ? showcaseStemUrl(stem, 'png') : ''
})

const showcaseImageSrcWebp = computed(() => {
  const stem = activeShowcase.value?.screenshotStem
  return stem ? showcaseStemUrl(stem, 'webp') : ''
})

const showcaseImageSrcSvg = computed(() => {
  const stem = activeShowcase.value?.screenshotStem
  return stem ? showcaseStemUrl(stem, 'svg') : ''
})

const showShowcaseImage = computed(() => {
  const s = activeShowcase.value
  return Boolean(s?.screenshotStem) && !showcaseImageFailed.value
})

const featureShowcaseSection = ref<HTMLElement | null>(null)
const showcaseInView = ref(false)
const prefersReducedMotion = ref(readPrefersReducedMotion())
const glowX = ref(50)
const glowY = ref(50)

const { stop: stopShowcaseObserver } = useIntersectionObserver(
  featureShowcaseSection,
  (entries) => {
    const e = entries[0]
    if (e?.isIntersecting) showcaseInView.value = true
  },
  { threshold: 0.1 },
)

const showcaseRevealActive = computed(() => showcaseInView.value || prefersReducedMotion.value)

const showcaseGlowStyle = computed(() => {
  const s = activeShowcase.value
  if (!s) return { opacity: '0' }
  if (prefersReducedMotion.value) return { opacity: '0' }
  return {
    background: `radial-gradient(circle at ${glowX.value}% ${glowY.value}%, rgba(${s.glowRgb}, 0.28) 0%, transparent 52%)`,
    opacity: '1',
  }
})

function onSelectShowcase(id: ShowcaseId): void {
  selectedShowcaseId.value = id
  showcaseImageFailed.value = false
  trackLandingCta('landing_feature_showcase_tab')
  glowX.value = 50
  glowY.value = 50
}

function onShowcaseImageError(): void {
  showcaseImageFailed.value = true
}

watch(selectedShowcaseId, () => {
  showcaseImageFailed.value = false
})

function onShowcasePreviewMove(e: MouseEvent): void {
  if (prefersReducedMotion.value) return
  const el = e.currentTarget as HTMLElement | null
  if (!el) return
  const r = el.getBoundingClientRect()
  glowX.value = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100))
  glowY.value = Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100))
}

function onShowcasePreviewLeave(): void {
  glowX.value = 50
  glowY.value = 50
}

const heroActivities = [
  'مراكز الخدمة والصيانة والعمليات الميدانية',
  'التجزئة ونقطة البيع',
  'الجملة وشبكات التوزيع',
  'الأساطيل والعقود والتسعير حسب الاستخدام',
  'أنشطة مختلطة في حساب واحد (مزيج نماذج عمل)',
]

type PainId = 'collection' | 'workorder' | 'fragmentation' | 'reports'

const selectedPainId = ref<PainId>('collection')

const painOptions: { id: PainId; label: string }[] = [
  { id: 'collection', label: 'تأخر أو ضعف التحصيل' },
  { id: 'workorder', label: 'ضياع وقت أمر العمل / المتابعة' },
  { id: 'fragmentation', label: 'تشتت البيانات بين أدوات' },
  { id: 'reports', label: 'تقارير الإدارة تتأخر أو تتكرر يدويًا' },
]

const painStories: Record<PainId, { before: string[]; after: string[] }> = {
  collection: {
    before: [
      'متابعة المديونيات والمستحقات تعتمد على تذكيرات فردية أو جداول منفصلة.',
      'صعوبة رؤية صورة لحظية لمن تأخر ولماذا، خصوصًا مع عدة فروع.',
      'إقفال يوم العمل المالي غالبًا يتأخر لأن المعلومات موزّعة.',
    ],
    after: [
      'تنبيهات وبطاقات متابعة تربط الفاتورة أو أمر العمل بحالة التحصيل.',
      'لوحة توضّح أولويات المتابعة بدل البحث اليدوي.',
      'نفس التدفق التشغيلي يغذّي الرؤية المالية دون نسخ يدوي متكرر.',
    ],
  },
  workorder: {
    before: [
      'تحديث حالة المركبة أو الطلب يمرّ برسائل أو أوراق يصعب أرشفتها.',
      'الفريق يضيع وقتًا في السؤال عن «أين وصلنا؟» أكثر من التنفيذ.',
      'العميل يتلقّى معلومات متأخرة أو غير موحّدة.',
    ],
    after: [
      'أمر عمل واحد يحدّثه الفريق المعني من شاشات مترابطة.',
      'حالة الطلب واضحة للمحاسب والفني وصاحب القرار.',
      'أقل تكرار للمكالمات لأن آخر تحديث ظاهر في نفس المكان.',
    ],
  },
  fragmentation: {
    before: [
      'مخزون في ملف، مبيعات في أداة، صيانة في ورقة — صعوبة مطابقة الأرقام.',
      'أخطاء إدخال مضاعفة عند نقل البيانات بين الأنظمة.',
      'تقارير الإدارة تحتاج «تجميعًا» قبل أن تصبح قابلة للقرار.',
    ],
    after: [
      'تدفق مشتريات ومخزون ومبيعات وأوامر عمل يغذي بعضه البعض.',
      'مصدر بيانات واحد للتشغيل اليومي يقلّل فجوات النسخ.',
      'تقارير تُبنى من نفس الحركات التشغيلية التي يراها الفريق.',
    ],
  },
  reports: {
    before: [
      'الإدارة تنتظر «إغلاق الشهر» لمعرفة ما يحدث اليوم.',
      'ساعات في تجهيز شرائح أو جداول قبل الاجتماعات.',
      'صعوبة ربط مؤشر تشغيلي بسبب تأخر مالي مباشرة.',
    ],
    after: [
      'مؤشرات لحظية من نشاط الفريق اليومي لا من محصلة متأخرة فقط.',
      'تقارير جاهزة من نفس النظام دون إعادة بناء يدوية في كل مرة.',
      'صلة أوضح بين التشغيل والمالية لأنها تنطلق من نفس السجل.',
    ],
  },
}

const currentPainStory = computed(() => painStories[selectedPainId.value])

function onSelectPain(id: PainId): void {
  selectedPainId.value = id
  trackLandingCta('landing_pain_select')
}

function jumpToPainStory(id: PainId): void {
  selectedPainId.value = id
  trackLandingCta('landing_pain_select')
  trackLandingCta('landing_persona_quick_jump')
  void nextTick(() => {
    document.getElementById('smart-compare')?.scrollIntoView({
      behavior: prefersReducedMotion.value ? 'auto' : 'smooth',
      block: 'start',
    })
  })
}

const antiClaims = [
  'ليس بديلاً آليًا لكل ما تقدّمه حزم ERP الضخمة لأي قطاع دون ضبط واستثمار وقت في التهيئة.',
  'لا يغني عن الاستشارة المحاسبية أو القانونية عند القرارات الحساسة أو عند ضبط الامتثال لدى جهاتكم الرسمية.',
  'النتائج المرجوّة تعتمد على جودة البيانات المدخلة، ومشاركة الإدارة، واستمرارية التبني داخل الفريق.',
  'ميزات الفوترة والضرائب والفوترة الإلكترونية (وما يرتبط بـ ZATCA عند التفعيل) تخضع لإعداداتكم، صلاحياتكم، وسياسات مزوّد الخدمة والعقد.',
]

const complianceRows = [
  {
    area: 'فوترة ومتطلبات ضريبية',
    product:
      'مسارات فواتير وتكاملات يمكن ضبطها لدعم متطلبات الفوترة الإلكترونية وZATCA حيث يُفعّل ذلك في حسابكم.',
    note: 'لا يُعتبر إقرار امتثال نهائي؛ راجع مستشاركم وإعدادات التفعيل لديكم.',
  },
  {
    area: 'فروع وصلاحيات',
    product: 'دعم تعدد الفروع والأدوار وحدود المشاهدة ضمن نفس المنظومة.',
    note: 'يُهيّأ حسب سياسة شركتكم؛ التدريب على الحوكمة مسؤولية مشتركة.',
  },
  {
    area: 'تكامل وتصدير',
    product: 'واجهات ومسارات يمكن أن تربط التشغيل بأنظمة أخرى وفق خطتكم.',
    note: 'تفاصيل التكامل والتصدير والاحتفاظ بالبيانات تختلف حسب الاشتراك والعقود؛ استوضحوا قبل الاعتماد.',
  },
]

const continuityPoints = [
  {
    title: 'تبني على مراحل',
    body: 'ننصح ببدء نطاق واضح (مثل نقطة بيع + مخزون، أو أوامر عمل) ثم التوسع حسب الاستيعاب، بدل محاولة «كل شيء دفعة واحدة».',
  },
  {
    title: 'مشاركة الإدارة',
    body: 'نجاح التشغيل أقوى عندما يخصّص القائمون على القرار وقتاً قصيراً منتظماً للمتابعة وليس فقط «بعد الإطلاق».',
  },
  {
    title: 'أسئلة قبل الالتزام',
    body: 'يمكنكم حجز عرض قصير أو مراسلة المبيعات لتوضيح حدود الخدمة، الدعم، والخيارات المناسبة لنشاطكم قبل أي التزام طويل.',
  },
]

const startSteps = [
  {
    title: 'أنشئ جلسة دخول وادخل المنصة',
    body: 'ابدأ من صفحة الدخول، ثم اربط بيانات شركتك الأساسية خلال دقائق دون تعقيد تقني.',
  },
  {
    title: 'اضبط التدفق التشغيلي لنشاطك',
    body: 'هيّئ الخدمات، المخزون، المشتريات أو نقطة البيع بحيث يعكس النظام طريقة عملك الحقيقية.',
  },
  {
    title: 'راقب الأداء وقرّر على بيانات',
    body: 'تابع المؤشرات والتنبيهات، وشارك التقارير مع الإدارة ثم وسّع الاعتماد مع فريقك تدريجيًا.',
  },
]
const roiAssumptions = {
  efficiencyGainPercent: 20,
}
const roiInputs = reactive({
  monthlyOrders: 450,
  avgMinutes: 18,
  hourlyCost: 65,
})

const roiEstimate = computed(() => {
  const monthlyOrders = Math.max(1, Number(roiInputs.monthlyOrders) || 1)
  const avgMinutes = Math.max(1, Number(roiInputs.avgMinutes) || 1)
  const hourlyCost = Math.max(1, Number(roiInputs.hourlyCost) || 1)

  const rawHours = (monthlyOrders * avgMinutes) / 60
  const savedHours = Math.round(rawHours * (roiAssumptions.efficiencyGainPercent / 100))
  const savedAmountSar = Math.round(savedHours * hourlyCost)
  return { savedHours, savedAmountSar }
})

const roiMailtoHref = computed(() => {
  const mo = Math.max(1, Number(roiInputs.monthlyOrders) || 1)
  const am = Math.max(1, Number(roiInputs.avgMinutes) || 1)
  const hc = Math.max(1, Number(roiInputs.hourlyCost) || 1)
  const { savedHours, savedAmountSar } = roiEstimate.value
  const subject = encodeURIComponent('تقدير حاسبة الأثر - أسس برو')
  const body = encodeURIComponent(
    `تقدير حاسبة الأثر - أسس برو\n\n` +
      'المدخلات:\n' +
      `- عدد العمليات الشهرية: ${mo}\n` +
      `- متوسط وقت العملية (دقائق): ${am}\n` +
      `- تكلفة ساعة الفريق (ر.س): ${hc}\n\n` +
      `النتائج التقديرية (افتراض تحسين كفاءة ${roiAssumptions.efficiencyGainPercent}%):\n` +
      `- ساعات موفرة شهريًا تقريبًا: ${savedHours}\n` +
      `- توفير تكلفة شهري تقريب (ر.س): ${savedAmountSar}\n\n` +
      'ملاحظة: أرقام توضيحية وليست التزامًا بنتائج محددة.',
  )
  return `mailto:${SALES_EMAIL}?subject=${subject}&body=${body}`
})

const salesMailtoHref = computed(() => {
  const subject = encodeURIComponent('استفسار باقات أسس برو')
  return `mailto:${SALES_EMAIL}?subject=${subject}`
})

function loadRoiFromStorage(): void {
  try {
    const raw = localStorage.getItem(ROI_STORAGE_KEY)
    if (!raw) return
    const p = JSON.parse(raw) as Record<string, unknown>
    if (typeof p.monthlyOrders === 'number' && p.monthlyOrders >= 1) roiInputs.monthlyOrders = p.monthlyOrders
    if (typeof p.avgMinutes === 'number' && p.avgMinutes >= 1) roiInputs.avgMinutes = p.avgMinutes
    if (typeof p.hourlyCost === 'number' && p.hourlyCost >= 1) roiInputs.hourlyCost = p.hourlyCost
  } catch {
    /* تجاهل البيانات التالفة */
  }
}

watch(
  roiInputs,
  () => {
    try {
      localStorage.setItem(
        ROI_STORAGE_KEY,
        JSON.stringify({
          monthlyOrders: roiInputs.monthlyOrders,
          avgMinutes: roiInputs.avgMinutes,
          hourlyCost: roiInputs.hourlyCost,
        }),
      )
    } catch {
      /* تعطّل التخزين المحلي أو وضع خاص */
    }
  },
  { deep: true },
)

let metaDescriptionEl: HTMLMetaElement | null = null
let metaDescriptionCreated = false
let previousMetaDescription: string | null = null

function publicSiteOrigin(): string {
  const raw = import.meta.env.VITE_PUBLIC_SITE_URL
  if (typeof raw === 'string' && raw.trim()) {
    try {
      return new URL(raw.trim()).origin
    } catch {
      /* تابع إلى origin الحالي */
    }
  }
  return window.location.origin
}

function canonicalLandingUrl(): string {
  const base = import.meta.env.BASE_URL || '/'
  const u = new URL(base, publicSiteOrigin())
  const prefix = u.pathname.replace(/\/$/, '')
  return prefix ? `${u.origin}${prefix}/landing` : `${u.origin}/landing`
}

/** مسار مطلق لأصول public/ (مثل og-asaspro.png) يتوافق مع BASE_URL والنشر خلف مسار فرعي */
function landingPublicAssetUrl(filename: string): string {
  const base = import.meta.env.BASE_URL || '/'
  const root = new URL(base, publicSiteOrigin())
  return new URL(filename, root).href
}

const LANDING_OG_IMAGE_FILE = 'og-asaspro.png'
const LANDING_OG_IMAGE_WIDTH = '1200'
const LANDING_OG_IMAGE_HEIGHT = '630'

function appendTaggedMeta(attr: 'name' | 'property', key: string, content: string): void {
  const m = document.createElement('meta')
  m.setAttribute(attr, key)
  m.setAttribute('content', content)
  m.classList.add(LANDING_HEAD_MARK)
  document.head.appendChild(m)
}

function applyLandingSocialHead(): void {
  /** إن حقن الخادم (nginx) وسوم og مسبقًا لبوتات لا تنفّذ JS، لا نكرر الوسوم بعد تحميل Vue */
  if (document.querySelector('meta[property="og:title"]')) {
    return
  }
  const canonical = canonicalLandingUrl()
  const ogImage = landingPublicAssetUrl(LANDING_OG_IMAGE_FILE)
  appendTaggedMeta('property', 'og:title', LANDING_PAGE_TITLE)
  appendTaggedMeta('property', 'og:description', LANDING_META_DESCRIPTION)
  appendTaggedMeta('property', 'og:type', 'website')
  appendTaggedMeta('property', 'og:url', canonical)
  appendTaggedMeta('property', 'og:locale', 'ar_SA')
  appendTaggedMeta('property', 'og:image', ogImage)
  appendTaggedMeta('property', 'og:image:width', LANDING_OG_IMAGE_WIDTH)
  appendTaggedMeta('property', 'og:image:height', LANDING_OG_IMAGE_HEIGHT)
  appendTaggedMeta('name', 'twitter:card', 'summary_large_image')
  appendTaggedMeta('name', 'twitter:title', LANDING_PAGE_TITLE)
  appendTaggedMeta('name', 'twitter:description', LANDING_META_DESCRIPTION)
  appendTaggedMeta('name', 'twitter:image', ogImage)

  const link = document.createElement('link')
  link.rel = 'canonical'
  link.href = canonical
  link.classList.add(LANDING_HEAD_MARK)
  document.head.appendChild(link)
}

function clearLandingSocialHead(): void {
  document.querySelectorAll(`.${LANDING_HEAD_MARK}`).forEach((el) => el.remove())
}

onMounted(() => {
  prefersReducedMotion.value = readPrefersReducedMotion()
  void fetchLandingPlans()
  loadRoiFromStorage()
  updateLandingScrollProgress()
  window.addEventListener('scroll', updateLandingScrollProgress, { passive: true })
  window.addEventListener('resize', updateLandingScrollProgress, { passive: true })
  document.title = LANDING_PAGE_TITLE
  metaDescriptionEl = document.querySelector('meta[name="description"]')
  if (!metaDescriptionEl) {
    metaDescriptionEl = document.createElement('meta')
    metaDescriptionEl.setAttribute('name', 'description')
    document.head.appendChild(metaDescriptionEl)
    metaDescriptionCreated = true
  } else {
    previousMetaDescription = metaDescriptionEl.getAttribute('content')
  }
  metaDescriptionEl.setAttribute('content', LANDING_META_DESCRIPTION)
  applyLandingSocialHead()
})

onBeforeUnmount(() => {
  stopShowcaseObserver()
  window.removeEventListener('scroll', updateLandingScrollProgress)
  window.removeEventListener('resize', updateLandingScrollProgress)
  document.title = APP_DEFAULT_TITLE
  if (metaDescriptionCreated && metaDescriptionEl?.parentNode) {
    metaDescriptionEl.remove()
  } else if (metaDescriptionEl) {
    if (previousMetaDescription === null) {
      metaDescriptionEl.removeAttribute('content')
    } else {
      metaDescriptionEl.setAttribute('content', previousMetaDescription)
    }
  }
  clearLandingSocialHead()
})

const features = [
  {
    title: 'واجهة ذكية وسهلة',
    body: 'تصميم واضح، تنقّل سريع، وتجربة استخدام مدروسة تقلل وقت تدريب الفريق.',
  },
  {
    title: 'تنفيذ وتشغيل من شاشة واحدة',
    body: 'إدارة الطلبات، الخدمات، المشتريات، والمخزون ضمن تدفق موحد يقلّل التشتت والأخطاء.',
  },
  {
    title: 'تقارير تنفيذية فورية',
    body: 'لوحات تشغيلية ومالية لحظية تساعد الإدارة على اتخاذ قرار سريع مبني على بيانات حقيقية.',
  },
  {
    title: 'بنية آمنة وقابلة للتوسع',
    body: 'صلاحيات متعددة، قابلية توسع للفروع، واستقرار تشغيلي يدعم نمو النشاط بثقة.',
  },
  {
    title: 'جاهزية فرق العمل',
    body: 'تجربة استخدام عربية واضحة تقلل وقت التعلم وتزيد التبنّي داخل الفريق من اليوم الأول.',
  },
  {
    title: 'تحسين التحصيل والربحية',
    body: 'تنبيهات ذكية وخطوات مقترحة ترفع الانضباط المالي وتُحسن نتائج التحصيل.',
  },
]

const proofMetrics = [
  { value: '18%+', label: 'تحسن متوسط في التحصيل' },
  { value: '35%', label: 'تقليل وقت إعداد التقارير' },
  { value: '99.9%', label: 'جاهزية تشغيل المنصة' },
  { value: '48h', label: 'زمن إطلاق تشغيلي أولي' },
]

const useCases = [
  { title: 'الخدمات الميدانية ومراكز الصيانة', body: 'إدارة أوامر العمل، الفحوصات، الفوترة، والمتابعة في مكان واحد.' },
  { title: 'التجزئة ونقطة البيع', body: 'صندوق، فواتير، مخزون فرع، ومبيعات يومية مع تقارير ربحية وتتبع الأداء.' },
  { title: 'الجملة والتوزيع', body: 'صفقات كميات، مستودعات، تسليم لزبائن أو فروع، وتسعير يتناسب مع قنوات البيع.' },
  { title: 'شركات الأساطيل والعقود', body: 'تحكم بالمصاريف، تتبع العمليات، وعقود أو تسعير يعكس استخدامًا حقيقيًا.' },
  { title: 'أنشطة مختلطة', body: 'خدمة ميدانية مع متجر، أو عمليات مع مستودع—نفس المنصة لتمرير العمليات والموارد بسلاسة.' },
  { title: 'الإدارة التنفيذية', body: 'لوحات قيادة فورية للحوكمة والأداء واتخاذ القرار بثقة عبر الفروع.' },
]

const testimonials = [
  { name: 'أحمد العتيبي', role: 'مدير تشغيل - قطاع خدمات', quote: 'بعد أسس برو صرنا ننجز العمليات اليومية أسرع، والتقارير صارت واضحة للإدارة في نفس اليوم.' },
  { name: 'رهف السبيعي', role: 'مديرة مالية - شركة متعددة الفروع', quote: 'أكبر فرق لمسناه كان في تنظيم التدفقات المالية وتقليل الأخطاء التشغيلية المتكررة.' },
  { name: 'سالم القحطاني', role: 'صاحب منشأة خدمات', quote: 'النظام سهل جدًا للفريق، والتحصيل صار أفضل بسبب المتابعة والتنبيهات المستمرة.' },
]

const faqs = [
  {
    q: 'هل أسس برو مخصّص لقطاع أو نموذج عمل واحد؟',
    a: 'لا. المنصة متعددة الأنشطة وتُضبط حسب احتياجك: من الفروع والمخزون والمبيعات إلى العقود والأساطيل والخدمات الميدانية، مع تدفقات قابلة للتهيئة دون تقييدك بنشاط محدد.',
  },
  { q: 'هل أحتاج فريق تقني لتشغيل أسس برو؟', a: 'لا. المنصة مصممة لتبدأ بسرعة مع فريقك الحالي، مع تجربة استخدام واضحة وتدريب بسيط.' },
  { q: 'هل يمكن استخدامه لعدة فروع أو فرق؟', a: 'نعم، يدعم التوسع للفروع والصلاحيات المتعددة مع رؤية مركزية للإدارة.' },
  { q: 'كم يستغرق الإطلاق الأولي؟', a: 'غالبًا يمكن إطلاق تدفق تشغيلي أولي خلال 48 ساعة حسب جاهزية بيانات النشاط.' },
  { q: 'هل يمكنني طلب عرض مباشر قبل البدء؟', a: 'نعم، يمكنك حجز عرض مباشر لمدة 15 دقيقة لفهم كيف يخدم أسس برو احتياجك بدقة.' },
  {
    q: 'لماذا قسم «ماذا ليس أسس برو»؟',
    a: 'لأن وعدًا عامًا يقلل الثقة. نفضّل تحديد منطقة الخدمة بوضوح والاعتماد على إعداد نشاطك والمتابعة مع فريقك.',
  },
]
</script>

<style scoped>
.landing-section-title {
  @apply relative inline-block pb-3;
}

.landing-section-title::after {
  content: '';
  @apply absolute bottom-0 start-0 h-1 w-14 rounded-full bg-gradient-to-l from-primary-500 to-teal-400/90 opacity-95 dark:from-primary-400 dark:to-primary-600;
}

.landing-cta-secondary {
  @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300/90 bg-white px-2.5 py-2 text-center text-xs font-semibold leading-snug text-slate-700 shadow-sm transition duration-200 hover:border-primary-400/55 hover:bg-primary-50/70 hover:shadow dark:border-white/15 dark:bg-white/5 dark:text-slate-100 dark:hover:border-primary-500/45 dark:hover:bg-primary-900/35 sm:px-3 sm:text-sm;
}

@media (prefers-reduced-motion: reduce) {
  .landing-cta-secondary,
  .landing-card,
  .motion-safe\:animate-fade-in {
    animation: none !important;
    transition: none !important;
  }

  .landing-card:hover {
    transform: none;
  }

  .landing-showcase-bar {
    transition: none !important;
  }

  .landing-atlas-card:hover {
    transform: none;
  }
}

.landing-showcase-bar {
  transition: width 0.85s cubic-bezier(0.22, 1, 0.36, 1);
}

.landing-card {
  @apply transition duration-200 hover:-translate-y-0.5 hover:border-primary-300/50 hover:shadow-md dark:hover:border-primary-600/40;
}

.landing-faq summary::-webkit-details-marker,
.landing-explore-summary::-webkit-details-marker {
  display: none;
}

.landing-faq[open] {
  @apply border-primary-400/30 shadow-md ring-1 ring-primary-500/10 dark:border-primary-500/25;
}
</style>
