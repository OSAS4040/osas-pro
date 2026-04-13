<template>
  <div
    class="flex min-h-screen bg-slate-50 text-slate-900 transition-colors duration-200 dark:bg-gray-950 dark:text-white"
    dir="rtl"
  >
    <!-- قائمة جانبية — إدارة المنصة فقط (لا تكرار عناصر تطبيق المستأجر) -->
    <aside
      class="sticky top-0 z-30 hidden h-screen w-[17rem] shrink-0 flex-col border-l border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:flex"
      aria-label="قائمة إدارة المنصة"
    >
      <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
        <div class="flex items-center gap-2">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-violet-200 bg-violet-50 dark:border-violet-800 dark:bg-violet-950/50">
            <CpuChipIcon class="h-6 w-6 text-violet-700 dark:text-violet-300" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wide text-violet-600 dark:text-violet-400">منصة</p>
            <p class="truncate text-sm font-black text-slate-900 dark:text-white">مركز التحكم</p>
          </div>
        </div>
        <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
          أقسام أسفل: تشغيل المنصة. روابط «فريق العمل» تفتح بوابة المستأجر (فواتير، محاسبة، موظفون) حسب صلاحياتك.
        </p>
      </div>
      <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto p-2">
        <button
          v-for="item in sidebarNavItems"
          :key="item.id"
          type="button"
          class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-right text-sm font-semibold transition-colors"
          :class="activeSection === item.id
            ? 'bg-violet-600 text-white shadow-md'
            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'"
          @click="scrollToSection(item.id)"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0 opacity-90" />
          <span class="flex-1 leading-snug">{{ item.label }}</span>
        </button>
        <div class="my-2 border-t border-slate-100 dark:border-slate-800" />
        <p class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
          بوابة فريق العمل (المستأجر)
        </p>
        <RouterLink
          v-for="link in staffPortalLinks"
          :key="'sp-' + link.to"
          :to="link.to"
          class="flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-emerald-50 dark:text-slate-100 dark:hover:bg-emerald-950/30"
        >
          <component :is="link.icon" class="h-4 w-4 shrink-0 text-emerald-700 dark:text-emerald-400" />
          <span class="flex-1 leading-snug">{{ link.label }}</span>
        </RouterLink>
        <div class="my-2 border-t border-slate-100 dark:border-slate-800" />
        <RouterLink
          to="/admin/registration-profiles"
          class="flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
        >
          طلبات التسجيل (مراجعات)
        </RouterLink>
        <RouterLink
          to="/admin/qa"
          class="flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          <BeakerIcon class="h-4 w-4 shrink-0" />
          التحقق من النظام QA
        </RouterLink>
        <RouterLink
          to="/about/taxonomy"
          class="flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-violet-800 hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-950/30"
        >
          مسرد المفاهيم
        </RouterLink>
      </nav>
      <div class="border-t border-slate-100 p-3 dark:border-slate-800">
        <RouterLink
          to="/"
          title="الانتقال إلى لوحة فريق العمل (المستأجر): الفواتير، المحاسبة، الموظفون…"
          class="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/90 py-2.5 text-xs font-bold text-emerald-950 hover:bg-emerald-100 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-900/50"
        >
          <ArrowRightIcon class="h-4 w-4" />
          العودة لفريق العمل
        </RouterLink>
      </div>
    </aside>

    <div class="flex min-h-screen min-w-0 flex-1 flex-col">
      <!-- شريط علوي مضغوط داخل المحتوى -->
      <header
        class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 px-4 py-3 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95"
      >
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <h1 class="truncate text-base font-black text-slate-900 dark:text-white sm:text-lg">
              أسس برو — إدارة المنصة
            </h1>
            <span class="hidden rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400 sm:inline">
              صفحة موحّدة
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center rounded-xl border border-slate-300/80 bg-white/80 p-0.5 shadow-sm dark:border-white/10 dark:bg-white/5" role="group" aria-label="مظهر العرض">
              <button
                type="button"
                class="rounded-lg p-2 transition-colors"
                :class="!darkMode.isDark.value && darkMode.themeMode.value === 'manual' ? 'bg-purple-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
                title="الوضع النهاري"
                @click="darkMode.setLight()"
              >
                <SunIcon class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-lg p-2 transition-colors"
                :class="darkMode.isDark.value && darkMode.themeMode.value === 'manual' ? 'bg-purple-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
                title="الوضع الليلي"
                @click="darkMode.setDark()"
              >
                <MoonIcon class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-lg px-2 py-1.5 text-xs font-bold transition-colors"
                :class="darkMode.themeMode.value === 'auto' ? 'bg-purple-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
                title="حسب الوقت (7–18 نهاري تقريباً)"
                @click="darkMode.setAuto()"
              >
                تلقائي
              </button>
            </div>
            <div class="text-left">
              <div class="text-[10px] text-slate-500 dark:text-slate-400">آخر تحديث</div>
              <div class="font-mono text-xs text-emerald-700 dark:text-green-400">{{ lastRefresh }}</div>
            </div>
            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-200/80 transition-all hover:bg-slate-300 dark:bg-white/10 dark:hover:bg-white/20"
              title="تحديث البيانات"
              @click="refresh"
            >
              <ArrowPathIcon class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
            </button>
            <RouterLink
              to="/"
              title="لوحة فريق العمل"
              class="inline-flex items-center gap-1 rounded-lg border border-emerald-300/90 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-900 shadow-sm hover:bg-emerald-100 dark:border-emerald-800/50 dark:bg-emerald-950/50 dark:text-emerald-100 lg:hidden"
            >
              <ArrowRightIcon class="h-3.5 w-3.5" />
              فريق العمل
            </RouterLink>
          </div>
        </div>
      </header>

      <main ref="adminScrollEl" class="flex-1 overflow-y-auto overscroll-y-contain">
        <div class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6">
          <!-- تنقّل سريع — شاشات صغيرة (بدون شريط جانبي) -->
          <div class="mb-4 lg:hidden">
            <label class="mb-1 block text-[11px] font-semibold text-slate-500 dark:text-slate-400">الانتقال إلى قسم</label>
            <select
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
              :value="activeSection"
              @change="onMobileSectionSelect"
            >
              <option v-for="item in sidebarNavItems" :key="'m-'+item.id" :value="item.id">{{ item.label }}</option>
            </select>
            <div v-if="auth.isPlatform" class="mt-2 flex flex-wrap gap-1.5">
              <RouterLink
                v-for="link in staffPortalLinks"
                :key="'mb-' + link.to"
                :to="link.to"
                class="rounded-md border border-emerald-200/90 bg-emerald-50/90 px-2 py-1 text-[10px] font-bold text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100"
              >
                {{ link.label }}
              </RouterLink>
            </div>
          </div>
      <PlatformAdminExecutivePanel v-if="auth.isPlatform" :refresh-tick="executiveOverviewTick" />
      <div
        v-if="platformBanner"
        class="mb-4 rounded-xl border px-4 py-3 text-sm leading-relaxed"
        :class="companiesFeedOk ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-600/30 dark:bg-emerald-950/30 dark:text-emerald-100' : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-600/40 dark:bg-amber-950/40 dark:text-amber-100'"
      >
        {{ platformBanner }}
      </div>
      <!-- حالة مصادر البيانات — شفافية تشغيلية لمشغّل المنصة -->
      <div
        v-if="auth.isPlatform"
        class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-slate-200/90 bg-white/90 px-4 py-2.5 text-[11px] text-slate-600 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-300"
      >
        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-800 dark:text-slate-100">
          <span class="h-2 w-2 rounded-full" :class="companiesFeedOk ? 'bg-emerald-500' : 'bg-amber-500'" />
          المشتركون:
          <template v-if="companiesFeedOk">
            متصل — {{ companiesTotalCount.toLocaleString('ar-SA') }} شركة
            <span v-if="companiesTotalCount > companies.length" class="font-normal text-slate-500 dark:text-slate-400">(عرض {{ companies.length }} في هذه الصفحة)</span>
          </template>
          <template v-else>غير متاح من واجهة /admin/companies</template>
        </span>
        <span class="hidden h-4 w-px bg-slate-200 sm:inline dark:bg-slate-700" aria-hidden="true" />
        <span class="inline-flex items-center gap-1.5">
          <span class="h-2 w-2 rounded-full" :class="plansLoadOk ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'" />
          الباقات: {{ plansLoadOk ? 'متصل' : 'تعذّر أو لم يُحمَّل' }}
        </span>
        <span class="hidden h-4 w-px bg-slate-200 sm:inline dark:bg-slate-700" aria-hidden="true" />
        <span class="inline-flex items-center gap-1.5">
          <span class="h-2 w-2 rounded-full" :class="pulsePayload ? 'bg-violet-500' : pulseLoading ? 'bg-amber-400 animate-pulse' : 'bg-slate-300 dark:bg-slate-600'" />
          نبض المنصة (30 يوماً): {{ pulsePayload ? 'متصل' : pulseLoading ? 'جاري…' : 'غير متاح' }}
        </span>
      </div>
      <div
        v-if="showPlatformCompaniesEmptyCallout"
        class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-relaxed text-sky-950 dark:border-sky-800/60 dark:bg-sky-950/35 dark:text-sky-100"
      >
        <p class="mb-1 font-semibold">اتصال واجهة المشتركين يعمل لكن لا توجد شركات مسجّلة (0).</p>
        <p class="mb-2 text-[13px] opacity-90">
          غالباً الخادم الذي يستقبل <span class="font-mono text-xs" dir="ltr">/api/v1</span> ليس نفس قاعدة البيانات التي شغّلت عليها البذور، أو لم تُشغَّل البذور بعد.
        </p>
        <p class="text-[12px] font-mono bg-white/70 dark:bg-slate-900/60 rounded-lg px-2 py-1.5 overflow-x-auto" dir="ltr">
          docker compose exec app php artisan db:seed --force
        </p>
      </div>

      <!-- توجيه المنصة: المحاسبة والفواتير داخل المستأجر وليس في /admin -->
      <div
        v-if="auth.isPlatform"
        class="mb-5 rounded-2xl border border-emerald-200/90 bg-gradient-to-bl from-emerald-50/95 via-white to-slate-50/80 p-4 shadow-sm dark:border-emerald-900/40 dark:from-emerald-950/25 dark:via-slate-900 dark:to-slate-900"
      >
        <h2 class="text-sm font-black text-emerald-950 dark:text-emerald-100">
          أين الفواتير والمحاسبة والموظفون؟
        </h2>
        <p class="mt-1.5 text-[12px] leading-relaxed text-slate-700 dark:text-slate-300">
          <strong class="font-semibold text-slate-900 dark:text-white">إدارة المنصة (/admin)</strong>
          تعرض المشتركين والباقات والنبض فقط. القيود والفواتير وقوائم العملاء والموظفين تُدار داخل
          <strong class="font-semibold text-slate-900 dark:text-white">بوابة فريق العمل</strong>
          لكل شركة على حدة. استخدم الروابط أدناه أو زر «العودة لفريق العمل» في الشريط الجانبي.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
          <RouterLink
            v-for="link in staffPortalLinks"
            :key="'ov-' + link.to"
            :to="link.to"
            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200/80 bg-white/90 px-2.5 py-1.5 text-[11px] font-bold text-emerald-900 shadow-sm hover:border-emerald-400 hover:bg-emerald-50/80 dark:border-emerald-800/50 dark:bg-slate-800 dark:text-emerald-100 dark:hover:bg-emerald-950/40"
          >
            <component :is="link.icon" class="h-3.5 w-3.5 shrink-0 opacity-90" />
            {{ link.label }}
          </RouterLink>
        </div>
      </div>

      <!-- ══ SECTION: OVERVIEW ══ -->
      <section id="admin-section-overview" class="scroll-mt-32 mb-16">
        <!-- Platform KPIs -->
        <div v-if="platformOverviewLoading" class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6" aria-busy="true" aria-label="جاري تحميل مؤشرات المنصة">
          <div v-for="sk in 3" :key="sk" class="animate-pulse rounded-xl border border-slate-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 h-3 w-24 rounded bg-slate-200 dark:bg-slate-700" />
            <div class="h-8 w-14 rounded bg-slate-200 dark:bg-slate-700" />
          </div>
        </div>
        <div v-else class="mb-2 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <div
            v-for="k in overviewKpis"
            :key="k.key"
            class="rounded-xl border p-3 shadow-sm transition-shadow hover:shadow-md sm:p-4"
            :class="k.cardClass"
          >
            <div class="mb-0.5 text-[11px] font-semibold text-slate-600 dark:text-slate-400">{{ k.label }}</div>
            <div class="text-lg font-black tracking-tight text-slate-900 dark:text-white sm:text-xl">{{ k.value }}</div>
            <div v-if="k.hint" class="mt-1 text-[10px] leading-snug text-slate-500 dark:text-slate-500">{{ k.hint }}</div>
          </div>
        </div>
        <p class="mb-4 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
          {{ overviewKpiFootnote }}
        </p>
        <div
          v-if="auth.isPlatform"
          class="mb-6 rounded-2xl border border-slate-200/90 bg-white/90 p-4 text-sm leading-relaxed text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300"
        >
          <p class="mb-2 font-semibold text-slate-900 dark:text-white">الإيرادات والتحليلات داخل المنصة</p>
          <p class="mb-2 text-[13px]">
            لا يُعرض هنا إيراد تحصيلي أو مخططات وهمية؛ الأرقام أعلاه مرجعية من كتالوج الباقات. التقارير المالية والتشغيلية الرسمية تُبنى داخل تطبيق فريق العمل (المستأجر).
          </p>
          <p class="text-[13px]">
            للتحليلات:
            <RouterLink class="font-medium text-violet-700 underline underline-offset-2 dark:text-violet-400" to="/reports">التقارير</RouterLink>
            و
            <RouterLink class="font-medium text-violet-700 underline underline-offset-2 dark:text-violet-400" to="/business-intelligence">ذكاء الأعمال</RouterLink>
            عند الحاجة (صلاحيات المستأجر).
          </p>
          <p class="mt-3 border-t border-slate-200/80 pt-3 text-[11px] text-slate-600 dark:border-slate-700 dark:text-slate-400">
            مرجع أوامر الخادم (<span dir="ltr" class="font-mono text-[10px]">docker</span> /
            <span dir="ltr" class="font-mono text-[10px]">artisan</span>) مع نسخ بنقرة:
            <button
              type="button"
              class="mr-1 font-semibold text-violet-700 underline underline-offset-2 hover:text-violet-900 dark:text-violet-400 dark:hover:text-violet-300"
              @click="scrollToSection('operator-commands')"
            >
              أوامر المشغّل
            </button>
          </p>
        </div>

        <!-- رسوم بيانية — بيانات حقيقية من نبض المنصة والمشتركين -->
        <div
          v-if="auth.isPlatform && !platformOverviewLoading"
          class="mb-6 space-y-5"
        >
          <div class="grid gap-5 lg:grid-cols-12">
            <div
              class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm ring-1 ring-slate-100/80 dark:border-slate-800 dark:bg-slate-900 dark:ring-slate-800/60 lg:col-span-8"
            >
              <div class="mb-3 flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
                <div>
                  <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    ملخص أسبوعي — أوامر العمل والشركات الجديدة
                  </h3>
                  <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                    من تقرير نبض المنصة؛ محوران لأن حجم أوامر العمل يختلف عن عدد الشركات.
                  </p>
                </div>
                <span
                  v-if="pulseReportPeriod"
                  class="shrink-0 rounded-lg bg-slate-50 px-2 py-1 text-[10px] font-mono text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-600"
                  dir="ltr"
                >{{ pulseReportPeriod }}</span>
              </div>
              <div v-if="!hasWeeklyTrend" class="flex min-h-[220px] items-center justify-center px-4 text-center text-sm text-slate-500 dark:text-slate-400">
                لا توجد دفعات زمنية كافية في النطاق الحالي، أو تعذّر تحميل نبض المنصة. سيظهر المخطط تلقائياً عند توفر بيانات أسبوعية.
              </div>
              <div v-else class="relative h-[260px] w-full">
                <Line
                  :key="'plt-trend-' + chartThemeKey"
                  :data="platformTrendLineData"
                  :options="platformTrendLineOptions"
                />
              </div>
            </div>

            <div
              class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm ring-1 ring-slate-100/80 dark:border-slate-800 dark:bg-slate-900 dark:ring-slate-800/60 lg:col-span-4"
            >
              <h3 class="mb-1 border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-white">
                توزيع الباقات (المحمّل)
              </h3>
              <p v-if="planDistribution.length === 0" class="mt-4 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                لا توجد شركات في الصفحة الحالية لرسم التوزيع.
              </p>
              <template v-else>
                <div class="relative mx-auto mt-2 h-[200px] w-full max-w-[280px]">
                  <Doughnut
                    :key="'plt-plan-' + chartThemeKey"
                    :data="planDoughnutData"
                    :options="planDoughnutOptions"
                  />
                </div>
                <ul class="mt-3 space-y-1.5 border-t border-slate-100 pt-3 text-[11px] dark:border-slate-800">
                  <li v-for="p in planDistribution" :key="'lg-'+p.slug" class="flex justify-between gap-2 text-slate-600 dark:text-slate-300">
                    <span class="truncate">{{ p.label }}</span>
                    <span class="shrink-0 font-semibold text-slate-900 dark:text-white">{{ p.count.toLocaleString('ar-SA') }} ({{ p.pct }}٪)</span>
                  </li>
                </ul>
              </template>
            </div>
          </div>

          <div class="grid gap-5 lg:grid-cols-2">
            <div
              class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              :class="{ 'lg:col-span-2': !hasSubscriptionMixChart }"
            >
              <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">
                مكوّن الشركات (إجمالي المنصة)
              </h3>
              <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">
                تشغيل طبيعي مقابل موقوفة مقابل الحالات الأخرى — من ملخص النبض.
              </p>
              <div v-if="!hasCompanyMixChart" class="flex min-h-[140px] items-center text-sm text-slate-500 dark:text-slate-400">
                غير متاح بدون بيانات ملخص المنصة.
              </div>
              <div v-else class="relative h-[160px] w-full">
                <Bar
                  :key="'plt-co-' + chartThemeKey"
                  :data="companyMixBarData"
                  :options="companyMixBarOptions"
                />
              </div>
            </div>
            <div
              v-if="hasSubscriptionMixChart"
              class="rounded-2xl border border-slate-200/95 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
              <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">
                الاشتراكات حسب الحالة
              </h3>
              <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">
                عدد الاشتراكات لكل حالة مسجّلة في قاعدة المنصة.
              </p>
              <div class="relative h-[160px] w-full">
                <Bar
                  :key="'plt-sub-' + chartThemeKey"
                  :data="subscriptionMixBarData"
                  :options="subscriptionMixBarOptions"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="mb-6 rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-slate-50/80 to-violet-50/40 p-5 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-violet-950/25 dark:shadow-none">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <h3 class="mb-1 flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                <SparklesIcon class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                نبض المنصة — مؤشرات موحّدة
              </h3>
              <p class="text-xs text-slate-600 dark:text-slate-400">
                يُستخرج من تقرير
                <span class="font-mono text-[10px]" dir="ltr">platform.pulse_summary</span>
                عند توفر الصلاحية؛ وإلا تُشتق الأعداد من قائمة المشتركين المحمّلة.
              </p>
            </div>
            <span v-if="pulseReportPeriod" class="rounded-lg bg-white/80 px-2 py-1 text-[10px] font-mono text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-600" dir="ltr">{{ pulseReportPeriod }}</span>
          </div>
          <div v-if="pulsePayload?.breakdown?.by_activity" class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-slate-700 dark:bg-slate-800/50">
              <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">شركات جديدة (الفترة)</div>
              <div class="text-2xl font-black text-slate-900 dark:text-white">
                {{ Number(pulsePayload.breakdown.by_activity.companies_registered_in_period ?? 0).toLocaleString('ar-SA') }}
              </div>
            </div>
            <div class="rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-slate-700 dark:bg-slate-800/50">
              <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">أوامر عمل جديدة (الفترة)</div>
              <div class="text-2xl font-black text-slate-900 dark:text-white">
                {{ Number(pulsePayload.breakdown.by_activity.work_orders_created_in_period ?? 0).toLocaleString('ar-SA') }}
              </div>
            </div>
          </div>
          <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            للتحليل التفصيلي داخل المستأجر استخدم
            <RouterLink to="/reports" class="font-medium text-violet-700 underline underline-offset-2 dark:text-violet-400">التقارير</RouterLink>
            و
            <RouterLink to="/business-intelligence" class="font-medium text-violet-700 underline underline-offset-2 dark:text-violet-400">ذكاء الأعمال</RouterLink>
            (صلاحيات المستأجر). مسار
            <RouterLink to="/operations/global-feed" class="font-medium text-violet-700 underline underline-offset-2 dark:text-violet-400">تدفق العمليات</RouterLink>
            لمتابعة التشغيل اليومي.
          </p>
        </div>

        <!-- Recent Signups -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-hidden">
          <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-gray-800">
            <h3 class="font-bold text-slate-900 dark:text-white">آخر المشتركين</h3>
            <button
              v-if="companiesFeedOk"
              type="button"
              class="text-sm font-medium text-violet-600 hover:text-violet-500 dark:text-violet-400 dark:hover:text-violet-300"
              @click="scrollToSection('tenants')"
            >
              عرض الكل
            </button>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الشركة</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الباقة</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الاشتراك</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإيراد الشهري</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">حالة الشركة</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الانضمام</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-if="platformOverviewLoading">
                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-gray-400">
                  جاري تحميل قائمة المشتركين…
                </td>
              </tr>
              <tr v-else-if="recentCompanies.length === 0">
                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-gray-400 leading-relaxed">
                  لا يوجد مشتركون في المنصة حتى الآن — القائمة تأتي من الخادم وليست وهمية.
                  أنشئ شركة من مسار التسجيل العادي، أو انتقل من القائمة الجانبية إلى «المشتركون»، أو شغّل بذور قاعدة البيانات التجريبية إن وُجدت في المشروع.
                </td>
              </tr>
              <tr
                v-for="c in recentCompanies"
                :key="c.id"
                class="cursor-pointer transition-colors hover:bg-violet-50/80 dark:hover:bg-slate-800/40"
                @click="openTenantFromOverview(c)"
              >
                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ c.name }}</td>
                <td class="px-4 py-3">
                  <PlanBadge :plan="c.plan_slug" :label="planDisplayName(c.plan_slug, c.plan_name)" />
                </td>
                <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-300">{{ subscriptionStatusLabel(c.subscription_status) }}</td>
                <td class="px-4 py-3 font-medium text-emerald-700 dark:text-emerald-400">{{ formatCurrency(Number(c.monthly_revenue) || 0) }}</td>
                <td class="px-4 py-3 text-xs">{{ companyRowStatusLabel(c) }}</td>
                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ formatDate(c.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ══ SECTION: TENANTS ══ -->
      <section v-if="auth.isPlatform" id="admin-section-tenants" class="scroll-mt-32 mb-16">
        <div
          v-if="auth.isPlatform && !companiesFeedOk"
          class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100"
        >
          لم تُحمَّل قائمة الشركات من الخادم — جدول المشتركين قد يبقى فارغاً حتى ينجح طلب
          <span class="font-mono text-xs" dir="ltr">GET /admin/companies</span>
          (صلاحيات مشغّل المنصة).
        </div>
        <div class="flex flex-wrap gap-3 mb-4">
          <input v-model="tenantSearch" placeholder="بحث في الشركات..."
                 class="flex-1 min-w-48 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 outline-none"
          />
          <select v-model="tenantPlanFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            <option value="">كل الباقات</option>
            <option v-for="p in plans" :key="p.id" :value="p.slug">{{ p.name }}</option>
          </select>
          <select v-model="tenantStatusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            <option value="">كل الحالات</option>
            <option value="active">نشط</option>
            <option value="trial">تجريبي</option>
            <option value="suspended">موقوف</option>
          </select>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الشركة</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">المالك</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الباقة</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الإيراد</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">المستخدمين</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">آخر نشاط</th>
                <th class="px-4 py-3 text-right text-gray-400 font-semibold">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-if="!platformOverviewLoading && companiesFeedOk && filteredCompanies.length === 0">
                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                  لا توجد نتائج تطابق عوامل التصفية الحالية.
                </td>
              </tr>
              <tr v-for="c in filteredCompanies" :key="c.id" class="hover:bg-slate-100 dark:hover:bg-gray-800/30">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900 dark:text-white">{{ c.name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ c.slug }}</div>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-gray-300">{{ c.owner_name || '—' }}</td>
                <td class="px-4 py-3"><PlanBadge :plan="c.plan_slug" :label="planDisplayName(c.plan_slug, c.plan_name)" /></td>
                <td class="px-4 py-3 text-emerald-400 font-semibold">{{ formatCurrency(c.monthly_revenue || planPriceMap[c.plan_slug] || 0) }}</td>
                <td class="px-4 py-3 text-slate-700 dark:text-gray-300">{{ c.users_count || 1 }}</td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ formatDate(c.updated_at) }}</td>
                <td class="px-4 py-3">
                  <button type="button" class="text-xs font-medium text-purple-400 hover:text-purple-300" @click="openTenantOps(c)">
                    تشغيل / باقة / ملف رأسي
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ══ SECTION: PLANS ══ -->
      <section v-if="auth.isPlatform" id="admin-section-plans" class="scroll-mt-32 mb-16">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">إدارة الباقات والميزات</h2>
          <p class="text-xs text-gray-500 max-w-md leading-relaxed">
            إنشاء باقة جديدة من هذه الواجهة غير متاح؛ يُضبط التسعير والباقات عبر إدارة المنصة على الخادم حسب سياسة التشغيل.
          </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="plan in plans" :key="plan.id"
               class="rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-purple-500 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-purple-600"
          >
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ plan.name }}</h3>
              <span class="text-xs bg-purple-600/30 text-purple-300 px-2 py-0.5 rounded-full">{{ plan.slug }}</span>
            </div>
            <div class="text-3xl font-black text-emerald-400 mb-1">{{ formatCurrency(plan.price_monthly) }}<span class="text-sm text-gray-500">/شهر</span></div>
            <div class="text-sm text-gray-400 mb-4">{{ formatCurrency(plan.price_yearly) }}/سنة</div>
            <div class="space-y-1.5 mb-4">
              <div v-for="(val, feat) in (plan.features || {})" :key="feat" class="flex items-center gap-2 text-sm">
                <CheckIcon v-if="val" class="w-4 h-4 text-emerald-400 flex-shrink-0" />
                <XMarkIcon v-else class="w-4 h-4 text-gray-600 flex-shrink-0" />
                <span :class="val ? 'text-gray-300' : 'text-gray-600'">{{ featureLabel(String(feat)) }}</span>
              </div>
            </div>
            <div class="mb-3 text-xs text-gray-500">المشتركون: <span class="font-bold text-slate-900 dark:text-white">{{ plan.subscribers_count || 0 }}</span></div>
            <button class="w-full rounded-lg border border-slate-300 py-2 text-sm text-slate-600 transition-all hover:border-purple-500 hover:text-slate-900 dark:border-gray-700 dark:text-gray-400 dark:hover:border-purple-500 dark:hover:text-white" @click="editPlan(plan)">
              تعديل الباقة
            </button>
          </div>
        </div>

        <!-- Edit Plan Modal -->
        <Teleport to="body">
          <div v-if="editingPlan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" dir="rtl">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
              <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-gray-700">
                <h3 class="font-bold text-slate-900 dark:text-white">تعديل: {{ editingPlan.name }}</h3>
                <button type="button" class="text-slate-500 hover:text-slate-800 dark:text-gray-400 dark:hover:text-white" @click="editingPlan = null"><XMarkIcon class="h-5 w-5" /></button>
              </div>
              <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="text-xs text-gray-400 block mb-1">السعر الشهري (ريال)</label>
                    <input v-model.number="editingPlan.price_monthly" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                  </div>
                  <div>
                    <label class="text-xs text-gray-400 block mb-1">السعر السنوي (ريال)</label>
                    <input v-model.number="editingPlan.price_yearly" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                  </div>
                </div>
                <div>
                  <label class="text-xs text-gray-400 block mb-2">الميزات المتاحة</label>
                  <div class="space-y-2">
                    <label v-for="(_, feat) in allFeatures" :key="feat" class="flex items-center gap-3 cursor-pointer">
                      <div class="relative">
                        <input type="checkbox" :checked="editingPlan.features[feat]" class="sr-only"
                               @change="toggleFeature(feat)"
                        />
                        <div :class="editingPlan.features[feat] ? 'bg-purple-600 border-purple-600' : 'border-slate-300 bg-slate-100 dark:border-gray-600 dark:bg-gray-800'"
                             class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all"
                        >
                          <CheckIcon v-if="editingPlan.features[feat]" class="w-3 h-3 text-white" />
                        </div>
                      </div>
                      <span class="text-sm text-gray-300">{{ featureLabel(String(feat)) }}</span>
                    </label>
                  </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                  <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-gray-400 dark:hover:bg-gray-800" @click="editingPlan = null">إلغاء</button>
                  <button class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium" @click="savePlan">حفظ</button>
                </div>
              </div>
            </div>
          </div>
        </Teleport>
      </section>

      <!-- ══ SECTION: OPS ══ -->
      <section v-if="auth.isPlatform" id="admin-section-ops" class="scroll-mt-32 mb-16">
        <p class="text-sm text-gray-400 mb-4 leading-relaxed">
          ملخص تشغيلي للمشغّل: طوابير الفشل، اتصال Redis وقاعدة البيانات. للتحقق من الواجهات استخدم
          <RouterLink to="/admin/qa" class="text-purple-400 underline underline-offset-2">التحقق من النظام (QA)</RouterLink>
          ؛ ولقائمة أوامر
          <span class="font-mono text-xs text-slate-300" dir="ltr">artisan</span>
          الجاهزة للنسخ انتقل إلى
          <button type="button" class="font-medium text-violet-400 underline underline-offset-2 hover:text-violet-300" @click="scrollToSection('operator-commands')">
            أوامر المشغّل
          </button>
          .
        </p>
        <div v-if="opsLoading" class="text-gray-500 py-8 text-center">جاري التحميل…</div>
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5">
            <div class="text-xs text-gray-500 mb-1">failed_jobs</div>
            <div class="font-mono text-2xl font-black text-slate-900 dark:text-white">{{ opsSummary?.failed_jobs_count ?? '—' }}</div>
          </div>
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5">
            <div class="text-xs text-gray-500 mb-1">Redis</div>
            <div class="text-lg font-bold" :class="opsSummary?.redis_ok ? 'text-emerald-400' : 'text-red-400'">
              {{ opsSummary?.redis_ok ? 'متصل' : 'غير متاح' }}
            </div>
          </div>
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5">
            <div class="text-xs text-gray-500 mb-1">قاعدة البيانات</div>
            <div class="text-lg font-bold" :class="opsSummary?.database_ok ? 'text-emerald-400' : 'text-red-400'">
              {{ opsSummary?.database_ok ? 'متصل' : 'خطأ' }}
            </div>
          </div>
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5 flex flex-col justify-center">
            <button type="button" class="text-sm text-purple-400 hover:text-purple-300" @click="loadOpsSummary">تحديث الملخص</button>
          </div>
        </div>
      </section>

      <!-- ══ SECTION: أوامر تنفيذية (خادم — مرجع للمشغّل) ══ -->
      <section
        v-if="auth.isPlatform"
        id="admin-section-operator-commands"
        class="scroll-mt-32 mb-16"
        aria-labelledby="operator-commands-heading"
      >
        <div class="mb-4">
          <h2 id="operator-commands-heading" class="text-lg font-black text-slate-900 dark:text-white">
            أوامر تنفيذية على الخادم
          </h2>
          <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
            لا تُشغَّل من المتصفح — انسخ الأمر إلى جلسة SSH أو حاوية التطبيق. استبدل
            <span class="font-mono" dir="ltr">app</span>
            باسم خدمة التطبيق في ملف Docker عند الحاجة.
          </p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
          <article
            v-for="row in operatorCommandsCatalog"
            :key="row.id"
            class="flex flex-col rounded-2xl border border-violet-200/90 bg-gradient-to-br from-white to-violet-50/40 shadow-sm dark:border-violet-900/45 dark:from-slate-900 dark:to-violet-950/20"
          >
            <div class="flex flex-wrap items-start justify-between gap-2 border-b border-violet-100/90 px-4 py-3 dark:border-violet-900/40">
              <div class="min-w-0">
                <h3 class="text-sm font-bold text-violet-950 dark:text-violet-100">{{ row.title }}</h3>
                <p class="mt-0.5 text-[11px] leading-snug text-slate-600 dark:text-slate-400">{{ row.hint }}</p>
              </div>
              <button
                type="button"
                class="shrink-0 rounded-lg border border-violet-300/80 bg-white px-2.5 py-1 text-[11px] font-bold text-violet-800 shadow-sm hover:bg-violet-50 dark:border-violet-700 dark:bg-slate-800 dark:text-violet-200 dark:hover:bg-violet-950/50"
                @click="copyOperatorCommand(row.command)"
              >
                نسخ
              </button>
            </div>
            <pre
              class="m-0 flex-1 overflow-x-auto whitespace-pre-wrap break-all p-4 font-mono text-[11px] leading-relaxed text-slate-800 dark:text-slate-200"
              dir="ltr"
              tabindex="0"
            >{{ row.command }}</pre>
          </article>
        </div>
      </section>

      <!-- ══ SECTION: AUDIT ══ -->
      <section v-if="auth.isPlatform" id="admin-section-audit" class="scroll-mt-32 mb-16">
        <div class="flex flex-wrap gap-3 mb-4">
          <input v-model="auditCompanyFilter" type="number" min="1" placeholder="تصفية برقم company_id (اختياري)"
                 class="min-w-48 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
          />
          <button type="button" class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm text-slate-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white" @click="loadAuditLogs">تحميل</button>
        </div>
        <div v-if="auditLoading" class="text-gray-500 py-8 text-center">جاري التحميل…</div>
        <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-x-auto">
          <table class="w-full text-sm min-w-[640px]">
            <thead class="bg-slate-100 dark:bg-gray-800/50">
              <tr>
                <th class="px-3 py-2 text-right text-gray-400">#</th>
                <th class="px-3 py-2 text-right text-gray-400">الإجراء</th>
                <th class="px-3 py-2 text-right text-gray-400">الشركة</th>
                <th class="px-3 py-2 text-right text-gray-400">المستخدم</th>
                <th class="px-3 py-2 text-right text-gray-400">الوقت</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-for="row in auditRows" :key="row.id" class="hover:bg-slate-100 dark:hover:bg-gray-800/30">
                <td class="px-3 py-2 font-mono text-gray-500">{{ row.id }}</td>
                <td class="px-3 py-2 text-sm text-slate-800 dark:text-slate-200" :title="row.action">{{ formatAuditAction(row.action) }}</td>
                <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300">{{ row.company_id != null ? `شركة رقم ${row.company_id}` : '—' }}</td>
                <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ formatAuditUserId(row.user_id) }}</td>
                <td class="px-3 py-2 text-xs text-gray-500">{{ row.created_at }}</td>
              </tr>
            </tbody>
          </table>
          <p v-if="!auditLoading && auditRows.length === 0" class="text-center text-gray-500 py-6 text-sm">لا سجلات بعد أو لا توجد إجراءات platform.* مسجّلة.</p>
        </div>
      </section>

      <!-- ══ SECTION: FINANCE ══ -->
      <section v-if="auth.isPlatform" id="admin-section-finance" class="scroll-mt-32 mb-16">
        <p class="text-sm text-gray-400 mb-4 leading-relaxed">
          اعتماد النموذج المالي للشركات (شحن مسبق / ائتمان / رفض / تعليق). لا يختار العميل النموذج بنفسه — يقرّره مشغّل المنصة فقط.
        </p>
        <div class="flex flex-wrap gap-3 mb-4">
          <select v-model="financeStatusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            <option value="">كل الحالات المالية</option>
            <option value="pending_platform_review">قيد مراجعة المنصة</option>
            <option value="approved_prepaid">معتمد — شحن مسبق</option>
            <option value="approved_credit">معتمد — ائتمان</option>
            <option value="rejected">مرفوض</option>
            <option value="suspended">معلّق</option>
          </select>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-400">الشركة</th>
                <th class="px-4 py-3 text-right text-gray-400">النوع</th>
                <th class="px-4 py-3 text-right text-gray-400">الحالة</th>
                <th class="px-4 py-3 text-right text-gray-400">حد الائتمان</th>
                <th class="px-4 py-3 text-right text-gray-400">إجراء</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-for="c in filteredFinanceCompanies" :key="'fin-' + c.id" class="hover:bg-slate-100 dark:hover:bg-gray-800/30">
                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ c.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200" :title="c.financial_model ?? ''">
                  {{ companyFinancialModelLabel(c.financial_model) }}
                </td>
                <td class="px-4 py-3 text-sm text-amber-800 dark:text-amber-200" :title="c.financial_model_status ?? ''">
                  {{ companyFinancialModelStatusLabel(c.financial_model_status) }}
                </td>
                <td class="px-4 py-3 text-gray-300">{{ c.credit_limit ?? '—' }}</td>
                <td class="px-4 py-3">
                  <button type="button" class="text-purple-400 hover:text-purple-300 text-xs font-medium" @click="openFinancialEdit(c)">
                    تحديث القرار
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ══ SECTION: CANCELLATIONS ══ -->
      <section v-if="auth.isPlatform" id="admin-section-cancellations" class="scroll-mt-32 mb-16">
        <p class="text-sm text-gray-400 mb-4 leading-relaxed">
          مراجعة طلبات إلغاء أوامر العمل المعتمدة أو قيد التنفيذ. الاعتماد ينفّذ عكساً مالياً رسمياً حسب نموذج الشركة.
        </p>
        <div class="flex flex-wrap gap-3 mb-4">
          <select v-model="cancelStatusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="loadCancellationRequests">
            <option value="">كل الحالات</option>
            <option value="pending">قيد المراجعة</option>
            <option value="approved">معتمد</option>
            <option value="rejected">مرفوض</option>
          </select>
          <button type="button" class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white" @click="loadCancellationRequests">تحديث</button>
        </div>
        <div v-if="cancelLoading" class="text-gray-400 text-sm py-8 text-center">جارٍ التحميل…</div>
        <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-x-auto">
          <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-slate-100 dark:bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-400">#</th>
                <th class="px-4 py-3 text-right text-gray-400">الشركة</th>
                <th class="px-4 py-3 text-right text-gray-400">أمر العمل</th>
                <th class="px-4 py-3 text-right text-gray-400">الحالة</th>
                <th class="px-4 py-3 text-right text-gray-400">السبب</th>
                <th class="px-4 py-3 text-right text-gray-400">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-for="r in cancelRows" :key="r.id" class="hover:bg-slate-100 dark:hover:bg-gray-800/30">
                <td class="px-4 py-3 font-mono text-gray-400">{{ r.id }}</td>
                <td class="px-4 py-3 text-slate-900 dark:text-white">{{ r.company?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span class="font-mono text-xs text-gray-300">{{ r.work_order?.order_number ?? '—' }}</span>
                  <span class="block text-[10px] text-gray-500">{{ workOrderStatusLabel(r.work_order?.status) }}</span>
                </td>
                <td class="px-4 py-3 text-xs" :class="r.status === 'pending' ? 'text-amber-300' : 'text-gray-400'">{{ cancellationRequestStatusLabel(r.status) }}</td>
                <td class="px-4 py-3 text-gray-400 max-w-xs truncate" :title="r.reason">{{ r.reason }}</td>
                <td class="px-4 py-3 space-x-2 space-x-reverse">
                  <template v-if="r.status === 'pending'">
                    <button type="button" class="text-emerald-400 hover:underline text-xs" @click="cancelAction = { id: r.id, mode: 'approve' }; cancelNote = ''">اعتماد</button>
                    <button type="button" class="text-red-400 hover:underline text-xs" @click="cancelAction = { id: r.id, mode: 'reject' }; cancelNote = ''">رفض</button>
                  </template>
                  <span v-else class="text-gray-600 text-xs">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ══ SECTION: BANNER ══ -->
      <section v-if="auth.isPlatform" id="admin-section-banner" class="scroll-mt-32 mb-16">
        <div class="max-w-3xl">
          <p class="text-sm text-gray-400 mb-4 leading-relaxed">
            يظهر الشريط لجميع مستخدمي بوابة فريق العمل (أسس برو) أسفل شريط العنوان مباشرة.
            عند كل حفظ يُحدَّث رمز الإخفاء فيتجدد الإعلان لمن أغلق الشريط سابقًا.
          </p>
          <div v-if="bannerLoading" class="text-gray-500 text-sm py-8">جاري التحميل…</div>
          <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-6 space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="bannerForm.is_enabled" type="checkbox" class="rounded border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-sm font-medium text-slate-900 dark:text-white">تفعيل الشريط</span>
            </label>
            <div>
              <label class="text-xs text-gray-400 block mb-1">عنوان اختياري (غامق)</label>
              <input v-model="bannerForm.title" type="text" maxlength="200" placeholder="مثال: صيانة مجدولة الليلة"
                     class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>
            <div>
              <label class="text-xs text-gray-400 block mb-1">نص الإعلان</label>
              <textarea v-model="bannerForm.message" rows="4" maxlength="2000" placeholder="النص الذي يراه المستخدمون…"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white resize-y min-h-[100px]"
              />
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="text-xs text-gray-400 block mb-1">رابط (اختياري)</label>
                <input v-model="bannerForm.link_url" type="text" placeholder="/landing أو /subscription أو https://…"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
              <div>
                <label class="text-xs text-gray-400 block mb-1">نص الزر</label>
                <input v-model="bannerForm.link_text" type="text" maxlength="120" placeholder="تفاصيل أكثر"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
            </div>
            <div>
              <label class="text-xs text-gray-400 block mb-1">المظهر</label>
              <select v-model="bannerForm.variant"
                      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-purple-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white sm:max-w-xs"
              >
                <option value="promo">ترويجي (بنفسجي / تركواز)</option>
                <option value="info">معلومات (أزرق)</option>
                <option value="success">نجاح (أخضر)</option>
                <option value="warning">تنبيه (برتقالي)</option>
              </select>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="bannerForm.dismissible" type="checkbox" class="rounded border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-sm text-slate-700 dark:text-gray-300">السماح للمستخدم بإخفاء الشريط من جهازه</span>
            </label>
            <p v-if="bannerSaveMessage" class="text-sm" :class="bannerSaveError ? 'text-red-400' : 'text-emerald-400'">
              {{ bannerSaveMessage }}
            </p>
            <div class="flex justify-end pt-2">
              <button
                type="button"
                class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-all"
                :disabled="bannerSaving"
                @click="savePlatformBanner"
              >
                {{ bannerSaving ? 'جاري الحفظ…' : 'حفظ الإعلان' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <Teleport to="body">
        <div v-if="tenantOpsVisible" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4 overflow-y-auto" dir="rtl">
          <div class="my-8 w-full max-w-lg space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-bold text-slate-900 dark:text-white">تشغيل — {{ tenantOps?.name ?? '…' }}</h3>
              <button type="button" class="text-slate-500 hover:text-slate-800 dark:text-gray-400 dark:hover:text-white" @click="closeTenantOps"><XMarkIcon class="h-5 w-5" /></button>
            </div>
            <div v-if="tenantOpsBusy && !tenantOps" class="text-gray-400 text-sm py-6 text-center">جاري تحميل بيانات الشركة…</div>
            <p v-else-if="tenantOpsLoadError" class="text-sm text-red-400">{{ tenantOpsLoadError }}</p>
            <div v-else-if="tenantOps" class="space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-gray-300">
                  <input v-model="tenantOps.is_active" type="checkbox" class="rounded border-gray-600 text-purple-600" />
                  شركة مفعّلة في المنصة
                </label>
                <div>
                  <label class="text-xs text-gray-500 block mb-1">حالة الحساب</label>
                  <select v-model="tenantOps.company_status" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                    <option value="suspended">موقوف مؤقتاً</option>
                  </select>
                </div>
              </div>
              <button type="button" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm" :disabled="tenantOpsBusy" @click="saveTenantOperational">
                {{ tenantOpsBusy ? '…' : 'حفظ التشغيل' }}
              </button>
              <hr class="border-slate-200 dark:border-gray-800" />
              <div>
                <label class="text-xs text-gray-500 block mb-1">الباقة (اشتراك الشركة)</label>
                <select v-model="tenantOps.plan_slug" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                  <option v-for="p in plans" :key="p.slug" :value="p.slug" :title="p.slug">{{ p.name }}</option>
                </select>
              </div>
              <button type="button" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm" :disabled="tenantOpsBusy" @click="saveTenantPlan">
                {{ tenantOpsBusy ? '…' : 'تطبيق الباقة' }}
              </button>
              <hr class="border-slate-200 dark:border-gray-800" />
              <div>
                <label class="text-xs text-gray-500 block mb-1">ملف قطاع النشاط</label>
                <select v-model="tenantOps.vertical_profile_code" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                  <option value="">— بدون —</option>
                  <option v-for="vp in tenantOps.profileOptions" :key="vp.code" :value="vp.code" :title="vp.code">{{ vp.name }}</option>
                </select>
              </div>
              <div>
                <label class="text-xs text-gray-500 block mb-1">سبب التغيير (مطلوب عند إعادة التعيين أو الإزالة)</label>
                <input v-model="tenantOps.vertical_reason" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="مرجع تذكرة / سبب تشغيلي" />
              </div>
              <button type="button" class="w-full py-2 bg-violet-700 hover:bg-violet-600 text-white rounded-lg text-sm" :disabled="tenantOpsBusy" @click="saveTenantVertical">
                {{ tenantOpsBusy ? '…' : 'حفظ ملف قطاع النشاط' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="financialEdit" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" dir="rtl">
          <div class="w-full max-w-md space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
            <h3 class="font-bold text-slate-900 dark:text-white">قرار مالي — {{ financialEdit.name }}</h3>
            <div>
              <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">القرار</label>
              <select v-model="financialEdit.decision" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="approved_prepaid">اعتماد — شحن مسبق</option>
                <option value="approved_credit">اعتماد — ائتمان</option>
                <option value="rejected">رفض</option>
                <option value="suspended">تعليق</option>
              </select>
            </div>
            <div v-if="financialEdit.decision === 'approved_credit'">
              <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">حد الائتمان (اختياري)</label>
              <input v-model="financialEdit.credit_limit" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="مثال: 50000" />
            </div>
            <div>
              <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">ملاحظة (اختياري)</label>
              <textarea v-model="financialEdit.note" rows="2" maxlength="2000" class="w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" class="px-4 py-2 text-sm text-slate-600 dark:text-gray-400" @click="financialEdit = null">إلغاء</button>
              <button type="button" class="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg disabled:opacity-50" :disabled="financialSaving" @click="saveFinancialModel">
                {{ financialSaving ? '…' : 'حفظ' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="cancelAction" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" dir="rtl">
          <div class="w-full max-w-md space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ cancelAction.mode === 'approve' ? 'اعتماد إلغاء أمر العمل' : 'رفض طلب الإلغاء' }}</h3>
            <p v-if="cancelAction.mode === 'reject'" class="text-xs text-amber-800 dark:text-amber-200">ملاحظات الرفض إلزامية.</p>
            <textarea v-model="cancelNote" rows="4" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white" :placeholder="cancelAction.mode === 'approve' ? 'ملاحظة داخلية (اختياري)' : 'سبب الرفض…'" />
            <div class="flex justify-end gap-2">
              <button type="button" class="px-4 py-2 text-sm text-slate-600 dark:text-gray-400" @click="cancelAction = null; cancelNote = ''">إلغاء</button>
              <button
                type="button"
                class="px-4 py-2 text-sm rounded-lg text-white disabled:opacity-50"
                :class="cancelAction.mode === 'approve' ? 'bg-emerald-600' : 'bg-red-600'"
                :disabled="cancelBusy"
                @click="submitCancelAction"
              >
                {{ cancelBusy ? '…' : (cancelAction.mode === 'approve' ? 'اعتماد' : 'رفض') }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, defineComponent } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import {
  CpuChipIcon, ArrowPathIcon, CheckIcon, XMarkIcon,
  BuildingOffice2Icon,
  ChartBarIcon, Cog8ToothIcon,
  MegaphoneIcon, BanknotesIcon, ExclamationTriangleIcon,
  WrenchScrewdriverIcon, ClipboardDocumentListIcon,
  SunIcon, MoonIcon, ArrowRightIcon,
  SparklesIcon,
  BeakerIcon,
  HomeIcon,
  DocumentTextIcon,
  UsersIcon,
  UserGroupIcon,
  PresentationChartLineIcon,
  CommandLineIcon,
} from '@heroicons/vue/24/outline'
import { useDarkMode } from '@/composables/useDarkMode'
import { formatAuditAction, formatAuditUserId } from '@/utils/governanceAuditLabels'
import { companyFinancialModelLabel, companyFinancialModelStatusLabel } from '@/utils/companyFinancialLabels'
import { workOrderStatusLabel, cancellationRequestStatusLabel } from '@/utils/workOrderStatusLabels'
import { useAuthStore } from '@/stores/auth'
import PlatformAdminExecutivePanel from '@/components/platform-admin/PlatformAdminExecutivePanel.vue'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Filler,
)

const toast = useToast()
const auth = useAuthStore()
const router = useRouter()
const darkMode = useDarkMode()
const adminScrollEl = ref<HTMLElement | null>(null)
const activeSection = ref('overview')
const refreshing  = ref(false)
const lastRefresh = ref(new Date().toLocaleTimeString('ar-SA'))
const tenantSearch = ref('')
const tenantPlanFilter = ref('')
const tenantStatusFilter = ref('')
const editingPlan = ref<any>(null)
/** نجاح تحميل قائمة الشركات من `/admin/companies` (مع إجمالي التصفح) */
const companiesFeedOk = ref(false)
const companiesTotalCount = ref(0)
const plansLoadOk = ref(false)
const platformBanner = ref('')
/** أول تحميل لمؤشرات النظرة الشاملة (خطط + شركات) */
const platformOverviewLoading = ref(true)
/** يزامن تحديث لوحة القيادة العليا مع زر التحديث العام */
const executiveOverviewTick = ref(0)

type PulseWeeklyBucket = { period_start: string; count: number }
type PulseStatusRow = { status: string; count: number }

const pulsePayload = ref<{
  summary?: Record<string, number>
  breakdown?: {
    by_activity?: Record<string, number>
    by_time_period?: {
      granularity?: string
      work_orders?: PulseWeeklyBucket[]
      companies?: PulseWeeklyBucket[]
    }
    by_status?: {
      companies?: PulseStatusRow[]
      subscriptions?: PulseStatusRow[]
      support_tickets?: PulseStatusRow[]
    }
  }
} | null>(null)
const pulseLoading = ref(false)
const pulseReportPeriod = ref('')

/** اتصال واجهة الشركات ناجح لكن لا توجد شركات — غالباً بذور غير مُشغَّلة أو قاعدة فارغة */
const showPlatformCompaniesEmptyCallout = computed(
  () => companiesFeedOk.value && !platformOverviewLoading.value && companiesTotalCount.value === 0,
)

/** روابط ثابتة إلى بوابة المستأجر (محاسبة، فواتير، عملاء، موظفون) — تتماشى مع فصل «منصة / مستأجر». */
const staffPortalLinks = [
  { to: '/', label: 'لوحة التحكم', icon: HomeIcon },
  { to: '/invoices', label: 'الفواتير', icon: DocumentTextIcon },
  { to: '/ledger', label: 'دفتر الأستاذ', icon: BanknotesIcon },
  { to: '/chart-of-accounts', label: 'دليل الحسابات', icon: ClipboardDocumentListIcon },
  { to: '/reports', label: 'التقارير', icon: ChartBarIcon },
  { to: '/business-intelligence', label: 'ذكاء الأعمال', icon: PresentationChartLineIcon },
  { to: '/customers', label: 'العملاء', icon: UsersIcon },
  { to: '/workshop/employees', label: 'الموظفون', icon: UserGroupIcon },
]

const overviewNav = { id: 'overview', label: 'النظرة الشاملة', icon: ChartBarIcon }

/** ترتيب القائمة يطابق ترتيب الأقسام في الصفحة (تمرير واحد) */
const sidebarNavItems = computed(() => {
  if (!auth.isPlatform) {
    return [overviewNav]
  }
  return [
    overviewNav,
    { id: 'tenants', label: 'المشتركون', icon: BuildingOffice2Icon },
    { id: 'plans', label: 'الباقات والميزات', icon: Cog8ToothIcon },
    { id: 'ops', label: 'تشغيل المنصة', icon: WrenchScrewdriverIcon },
    { id: 'operator-commands', label: 'أوامر المشغّل', icon: CommandLineIcon },
    { id: 'audit', label: 'تدقيق المنصة', icon: ClipboardDocumentListIcon },
    { id: 'finance', label: 'النموذج المالي', icon: BanknotesIcon },
    { id: 'cancellations', label: 'إلغاء أوامر العمل', icon: ExclamationTriangleIcon },
    { id: 'banner', label: 'شريط الإعلان', icon: MegaphoneIcon },
  ]
})

function ensureSectionData(sectionId: string): void {
  if (!auth.isPlatform) return
  if (sectionId === 'banner') void loadPlatformBannerAdmin()
  if (sectionId === 'cancellations') void loadCancellationRequests()
  if (sectionId === 'ops') void loadOpsSummary()
  if (sectionId === 'audit') void loadAuditLogs()
}

function scrollToSection(sectionId: string): void {
  activeSection.value = sectionId
  ensureSectionData(sectionId)
  const root = adminScrollEl.value
  const el = typeof document !== 'undefined' ? document.getElementById(`admin-section-${sectionId}`) : null
  if (root && el && root.contains(el)) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  } else {
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

function onMobileSectionSelect(ev: Event): void {
  const v = (ev.target as HTMLSelectElement | null)?.value
  if (v) scrollToSection(v)
}

/** مرجع أوامر الخادم — يطابق أوامر Artisan المعرّفة في المشروع (نسخ للطرفية). */
const operatorCommandsCatalog = [
  {
    id: 'db-seed',
    title: 'بذور قاعدة البيانات',
    hint: 'تشغيل DatabaseSeeder؛ مفيد عندما تظهر المنصة 0 شركات رغم تشغيل الخادم.',
    command: 'docker compose exec app php artisan db:seed --force',
  },
  {
    id: 'migrate',
    title: 'ترحيل قاعدة البيانات',
    hint: 'تطبيق الهجرات؛ في الإنتاج خذ نسخة احتياطية أولاً.',
    command: 'docker compose exec app php artisan migrate --force',
  },
  {
    id: 'integrity-sanity',
    title: 'فحص سريع (اتصال + IAM منصة)',
    hint: 'أمر integrity:sanity — بدون قراءة دفتر أستاذ أو محافظ.',
    command: 'docker compose exec app php artisan integrity:sanity',
  },
  {
    id: 'integrity-data',
    title: 'فحص سلامة بيانات (مالي/تشغيلي)',
    hint: 'أمر integrity:verify — فواتير↔قيود، مخزون، محافظ، تكرار (حسب تعريف المشروع).',
    command: 'docker compose exec app php artisan integrity:verify',
  },
  {
    id: 'tenant-integrity',
    title: 'سلامة بيانات المستأجر',
    hint: 'محاذاة الفروع وغيرها عند الحاجة (خيار إصلاح اختياري).',
    command: 'docker compose exec app php artisan tenant:integrity --fix-branches',
  },
  {
    id: 'demo-seed',
    title: 'بذور تجريبية سريعة (تطوير)',
    hint: 'مسار dev:demo-seed إن وُجد في بيئة التطوير فقط.',
    command: 'docker compose exec app php artisan dev:demo-seed',
  },
  {
    id: 'platform-admin',
    title: 'إنشاء / تحديث مشغّل منصة',
    hint: 'يتطلب كلمة مرور تفاعلية؛ عيّن الهاتف والبريد حسب سياسة المنصة.',
    command: 'docker compose exec app php artisan platform-admin:provision --phone=9665XXXXXXXX --email=ops@your-domain.test',
  },
]

async function copyOperatorCommand(text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    toast.success('تم النسخ', 'الأمر جاهز للصق في الطرفية.')
  } catch {
    toast.error('تعذر النسخ', 'حدد النص من المربع وانسخه يدوياً.')
  }
}

const bannerForm = ref({
  is_enabled: false,
  title: '',
  message: '',
  link_url: '',
  link_text: '',
  variant: 'promo' as 'info' | 'success' | 'warning' | 'promo',
  dismissible: true,
})
const bannerLoading = ref(false)
const bannerSaving = ref(false)
const bannerSaveMessage = ref('')
const bannerSaveError = ref(false)

const financeStatusFilter = ref('')
const financialEdit = ref<{
  id: number
  name: string
  decision: string
  credit_limit: string
  note: string
} | null>(null)
const financialSaving = ref(false)

const cancelRows = ref<any[]>([])
const cancelLoading = ref(false)
const cancelStatusFilter = ref('pending')
const cancelAction = ref<{ id: number; mode: 'approve' | 'reject' } | null>(null)
const cancelNote = ref('')
const cancelBusy = ref(false)

const opsSummary = ref<{
  failed_jobs_count: number | null
  redis_ok: boolean
  database_ok: boolean
  integrity_hint?: string
} | null>(null)
const opsLoading = ref(false)

const auditRows = ref<any[]>([])
const auditLoading = ref(false)
const auditCompanyFilter = ref('')

const tenantOpsVisible = ref(false)
const tenantOps = ref<any>(null)
const tenantOpsBusy = ref(false)
const tenantOpsLoadError = ref('')

function closeTenantOps(): void {
  tenantOpsVisible.value = false
  tenantOps.value = null
  tenantOpsLoadError.value = ''
}

const companies = ref<any[]>([])
const plans = ref<any[]>([])

const planPriceMap: Record<string, number> = { trial: 0, basic: 299, professional: 799, enterprise: 2499 }

const allFeatures: Record<string, boolean> = {
  pos: true, invoices: true, work_orders: true, fleet: true,
  reports: true, api_access: true, zatca: true, booking: true,
}

async function refresh() {
  refreshing.value = true
  executiveOverviewTick.value += 1
  await fetchData()
  lastRefresh.value = new Date().toLocaleTimeString('ar-SA')
  refreshing.value = false
}

function extractPlansPayload(res: { data?: unknown }): any[] {
  const body = res?.data as Record<string, unknown> | undefined
  const d = body?.data
  return Array.isArray(d) ? d : []
}

function extractCompaniesListPayload(res: { data?: unknown }): any[] {
  const body = res?.data as Record<string, unknown> | undefined
  const d = body?.data
  if (Array.isArray(d)) {
    return d
  }
  const nested = (d as Record<string, unknown> | undefined)?.data
  return Array.isArray(nested) ? nested : []
}

function extractCompaniesFromResponse(res: { data?: unknown }): { items: any[]; total: number } {
  const items = extractCompaniesListPayload(res)
  const body = res?.data as Record<string, unknown> | undefined
  const pag = body?.pagination as Record<string, unknown> | undefined
  const total = typeof pag?.total === 'number' ? pag.total : items.length

  return { items, total }
}

async function loadPlatformPulse(): Promise<void> {
  if (!auth.isPlatform) return
  pulseLoading.value = true
  pulsePayload.value = null
  pulseReportPeriod.value = ''
  try {
    const end = new Date()
    const start = new Date()
    start.setDate(start.getDate() - 30)
    const from = start.toISOString().slice(0, 10)
    const to = end.toISOString().slice(0, 10)
    const { data } = await apiClient.get('/reporting/v1/platform/pulse-summary', { params: { from, to } })
    pulsePayload.value = (data?.data ?? null) as typeof pulsePayload.value
    const period = data?.report?.period as { from?: string; to?: string } | undefined
    if (period?.from && period?.to) {
      pulseReportPeriod.value = `${period.from} → ${period.to}`
    }
  } catch {
    pulsePayload.value = null
  } finally {
    pulseLoading.value = false
  }
}

async function fetchData() {
  platformOverviewLoading.value = true
  let comp: any[] = []
  plansLoadOk.value = false
  companiesFeedOk.value = false
  companiesTotalCount.value = 0
  try {
    try {
      const plansRes = await apiClient.get('/plans')
      plans.value = extractPlansPayload(plansRes)
      plansLoadOk.value = true
    } catch (e) {
      plans.value = []
      plansLoadOk.value = false
      if (import.meta.env.DEV) {
        console.warn('[admin] GET /plans failed — will still try companies list', e)
      }
    }

    try {
      const companiesRes = await apiClient.get('/admin/companies')
      const { items, total } = extractCompaniesFromResponse(companiesRes)
      comp = items
      companiesTotalCount.value = total
      companiesFeedOk.value = true
      platformBanner.value =
        'وضع مشغّل منصة: قائمة الشركات والمؤشرات من الخادم. الإيراد المعروض شهري حسب سعر الباقة في الكتالوج وليس تحصيلاً فعلياً.'
    } catch (e: any) {
      companiesFeedOk.value = false
      companiesTotalCount.value = 0
      comp = []
      if (import.meta.env.DEV) {
        console.warn(
          '[admin] GET /admin/companies failed',
          e.response?.status,
          e.response?.data?.code ?? e.response?.data?.message,
        )
      }
      if (e.response?.status === 403 && e.response?.data?.code === 'PLATFORM_ACCESS_ONLY') {
        platformBanner.value =
          'لا يمكن عرض قائمة المشتركين: تحقق من صلاحيات مشغّل المنصة (is_platform_user أو إعدادات البريد/الجوال في الخادم).'
      } else if (e.response?.status === 403) {
        platformBanner.value = 'الخادم رفض طلب قائمة المشتركين (صلاحية platform.companies.read أو ما يعادلها).'
      } else {
        platformBanner.value = 'تعذر تحميل قائمة المشتركين. تحقق من الشبكة أو سجلات الخادم.'
      }
    }

    companies.value = comp
    if (auth.isPlatform) {
      void loadPlatformPulse()
    }
  } catch (e) {
    console.error(e)
  } finally {
    platformOverviewLoading.value = false
  }
}

async function loadPlatformBannerAdmin(): Promise<void> {
  if (!auth.isPlatform) return
  bannerLoading.value = true
  bannerSaveMessage.value = ''
  try {
    const { data } = await apiClient.get('/platform/announcement-banner/admin')
    const d = data?.data
    if (d && typeof d === 'object') {
      bannerForm.value = {
        is_enabled: Boolean(d.is_enabled),
        title: String(d.title ?? ''),
        message: String(d.message ?? ''),
        link_url: String(d.link_url ?? ''),
        link_text: String(d.link_text ?? ''),
        variant: ['info', 'success', 'warning', 'promo'].includes(d.variant) ? d.variant : 'promo',
        dismissible: d.dismissible !== false,
      }
    }
  } catch {
    bannerSaveMessage.value = 'تعذر تحميل إعدادات الإعلان.'
    bannerSaveError.value = true
  } finally {
    bannerLoading.value = false
  }
}

async function loadOpsSummary(): Promise<void> {
  if (!auth.isPlatform) return
  opsLoading.value = true
  try {
    const { data } = await apiClient.get('/platform/ops-summary')
    opsSummary.value = data?.data ?? null
  } catch {
    opsSummary.value = null
    toast.error('تعذر تحميل ملخص التشغيل.')
  } finally {
    opsLoading.value = false
  }
}

async function loadAuditLogs(): Promise<void> {
  if (!auth.isPlatform) return
  auditLoading.value = true
  try {
    const params: Record<string, string | number> = { per_page: 40 }
    const cid = auditCompanyFilter.value.trim()
    if (cid !== '' && !Number.isNaN(Number(cid))) params.company_id = Number(cid)
    const { data } = await apiClient.get('/platform/audit-logs', { params })
    auditRows.value = data?.data ?? []
  } catch {
    auditRows.value = []
    toast.error('تعذر تحميل سجل التدقيق.')
  } finally {
    auditLoading.value = false
  }
}

async function openTenantOps(c: any): Promise<void> {
  if (!auth.isPlatform) return
  tenantOpsVisible.value = true
  tenantOpsLoadError.value = ''
  tenantOps.value = null
  tenantOpsBusy.value = true
  try {
    const { data } = await apiClient.get(`/platform/companies/${c.id}`)
    const d = data?.data
    if (!d?.company) {
      tenantOpsLoadError.value = 'استجابة غير متوقعة من الخادم.'
      return
    }
    const subPlan = d.subscription?.plan ?? c.plan_slug
    tenantOps.value = {
      id: c.id,
      name: d.company.name,
      is_active: Boolean(d.company.is_active),
      company_status: d.company.company_status || 'active',
      plan_slug: typeof subPlan === 'string' && subPlan !== '—' ? subPlan : (plans.value[0]?.slug ?? 'trial'),
      vertical_profile_code: d.company.vertical_profile_code || '',
      vertical_reason: '',
      profileOptions: d.vertical_profile_options ?? [],
    }
  } catch (e: any) {
    tenantOpsLoadError.value = e?.response?.data?.message ?? 'تعذر تحميل تفاصيل الشركة.'
  } finally {
    tenantOpsBusy.value = false
  }
}

async function saveTenantOperational(): Promise<void> {
  const t = tenantOps.value
  if (!t?.id) return
  tenantOpsBusy.value = true
  try {
    await apiClient.patch(`/platform/companies/${t.id}/operational`, {
      is_active: t.is_active,
      status: t.company_status,
    })
    toast.success('تم تحديث حالة التشغيل.')
    closeTenantOps()
    await fetchData()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'فشل الحفظ.')
  } finally {
    tenantOpsBusy.value = false
  }
}

async function saveTenantPlan(): Promise<void> {
  const t = tenantOps.value
  if (!t?.id || !t.plan_slug) return
  tenantOpsBusy.value = true
  try {
    await apiClient.patch(`/platform/companies/${t.id}/subscription`, { plan_slug: t.plan_slug })
    toast.success('تم تحديث باقة الاشتراك.')
    closeTenantOps()
    await fetchData()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'فشل تغيير الباقة.')
  } finally {
    tenantOpsBusy.value = false
  }
}

async function saveTenantVertical(): Promise<void> {
  const t = tenantOps.value
  if (!t?.id) return
  tenantOpsBusy.value = true
  try {
    await apiClient.patch(`/platform/companies/${t.id}/vertical-profile`, {
      vertical_profile_code: t.vertical_profile_code || null,
      reason: t.vertical_reason?.trim() || undefined,
    })
    toast.success('تم تحديث ملف قطاع النشاط.')
    closeTenantOps()
    await fetchData()
  } catch (e: any) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    if (errs && typeof errs === 'object') {
      toast.error(Object.values(errs).flat().join(' — ') || msg || 'فشل الحفظ')
    } else {
      toast.error(msg ?? 'فشل الحفظ.')
    }
  } finally {
    tenantOpsBusy.value = false
  }
}

const filteredFinanceCompanies = computed(() => {
  const q = financeStatusFilter.value.trim()
  if (!q) return companies.value
  return companies.value.filter((c: any) => (c.financial_model_status ?? '') === q)
})

function openFinancialEdit(c: any): void {
  financialEdit.value = {
    id: c.id,
    name: c.name,
    decision: 'approved_prepaid',
    credit_limit: c.credit_limit != null ? String(c.credit_limit) : '',
    note: '',
  }
}

async function saveFinancialModel(): Promise<void> {
  if (!financialEdit.value) return
  financialSaving.value = true
  try {
    const body: Record<string, unknown> = {
      decision: financialEdit.value.decision,
      note: financialEdit.value.note || undefined,
    }
    if (financialEdit.value.decision === 'approved_credit' && financialEdit.value.credit_limit.trim() !== '') {
      body.credit_limit = Number(financialEdit.value.credit_limit)
    }
    await apiClient.patch(`/platform/companies/${financialEdit.value.id}/financial-model`, body)
    toast.success('تم تحديث النموذج المالي.')
    financialEdit.value = null
    await fetchData()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'تعذر الحفظ.')
  } finally {
    financialSaving.value = false
  }
}

async function loadCancellationRequests(): Promise<void> {
  if (!auth.isPlatform) return
  cancelLoading.value = true
  try {
    const { data } = await apiClient.get('/platform/work-order-cancellation-requests', {
      params: { status: cancelStatusFilter.value || undefined, per_page: 50 },
    })
    cancelRows.value = data.data ?? []
  } catch {
    cancelRows.value = []
    toast.error('تعذر تحميل طلبات الإلغاء.')
  } finally {
    cancelLoading.value = false
  }
}

async function submitCancelAction(): Promise<void> {
  if (!cancelAction.value) return
  cancelBusy.value = true
  try {
    if (cancelAction.value.mode === 'approve') {
      await apiClient.post(`/platform/work-order-cancellation-requests/${cancelAction.value.id}/approve`, {
        note: cancelNote.value || undefined,
      })
      toast.success('تم اعتماد الإلغاء.')
    } else {
      if (cancelNote.value.trim().length < 3) {
        toast.error('ملاحظات الرفض مطلوبة (3 أحرف على الأقل).')
        return
      }
      await apiClient.post(`/platform/work-order-cancellation-requests/${cancelAction.value.id}/reject`, {
        review_notes: cancelNote.value.trim(),
      })
      toast.success('تم رفض طلب الإلغاء.')
    }
    cancelAction.value = null
    cancelNote.value = ''
    await loadCancellationRequests()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'تعذر التنفيذ.')
  } finally {
    cancelBusy.value = false
  }
}

async function savePlatformBanner(): Promise<void> {
  bannerSaving.value = true
  bannerSaveMessage.value = ''
  bannerSaveError.value = false
  try {
    const { data } = await apiClient.put('/platform/announcement-banner', {
      is_enabled: bannerForm.value.is_enabled,
      title: bannerForm.value.title.trim() || null,
      message: bannerForm.value.message.trim() || null,
      link_url: bannerForm.value.link_url.trim() || null,
      link_text: bannerForm.value.link_text.trim() || null,
      variant: bannerForm.value.variant,
      dismissible: bannerForm.value.dismissible,
    })
    bannerSaveMessage.value = data?.message ?? 'تم الحفظ.'
    const d = data?.data
    if (d?.dismiss_token) {
      /* يبقى النموذج متزامنًا مع الرمز الجديد */
      await loadPlatformBannerAdmin()
    }
  } catch (e: any) {
    bannerSaveError.value = true
    const msg = e.response?.data?.message
    const errs = e.response?.data?.errors
    if (errs && typeof errs === 'object') {
      bannerSaveMessage.value = Object.values(errs).flat().join(' — ') || msg || 'فشل الحفظ'
    } else {
      bannerSaveMessage.value = msg || 'فشل الحفظ'
    }
  } finally {
    bannerSaving.value = false
  }
}

const recentCompanies = computed(() => companies.value.slice(0, 8))

const filteredCompanies = computed(() => {
  let list = companies.value
  if (tenantSearch.value) list = list.filter(c => c.name?.toLowerCase().includes(tenantSearch.value.toLowerCase()))
  if (tenantPlanFilter.value) list = list.filter(c => c.plan_slug === tenantPlanFilter.value)
  const st = tenantStatusFilter.value
  if (st === 'trial') list = list.filter(c => c.plan_slug === 'trial')
  else if (st === 'suspended') list = list.filter(c => c.company_status === 'suspended')
  else if (st === 'active') {
    list = list.filter(c => c.is_active !== false && c.company_status !== 'suspended' && c.plan_slug !== 'trial')
  }
  return list
})

const planDistribution = computed(() => {
  const list = companies.value
  const total = Math.max(list.length, 1)
  const slugs = [...new Set(list.map((c) => c.plan_slug || '—'))]
  if (slugs.length === 0) {
    return []
  }
  return slugs.map((slug) => {
    const count = list.filter((c) => (c.plan_slug || '—') === slug).length
    const sample = list.find((c) => (c.plan_slug || '—') === slug)
    const label = planDisplayName(slug, sample?.plan_name)

    return {
      slug,
      label,
      count,
      pct: Math.round((count / total) * 100),
    }
  })
})

function formatWeekBucketLabel(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso.slice(0, 10)

  return d.toLocaleDateString('ar-SA', { month: 'numeric', day: 'numeric' })
}

const mergedWeeklyTrend = computed(() => {
  const tp = pulsePayload.value?.breakdown?.by_time_period
  const wo = tp?.work_orders ?? []
  const co = tp?.companies ?? []
  const keys = new Set<string>()
  wo.forEach((b) => {
    if (b.period_start) keys.add(b.period_start)
  })
  co.forEach((b) => {
    if (b.period_start) keys.add(b.period_start)
  })
  const sorted = [...keys].sort((a, b) => new Date(a).getTime() - new Date(b).getTime())
  const woMap = Object.fromEntries(wo.map((b) => [b.period_start, b.count]))
  const coMap = Object.fromEntries(co.map((b) => [b.period_start, b.count]))

  return {
    labels: sorted.map(formatWeekBucketLabel),
    workOrders: sorted.map((k) => Number(woMap[k] ?? 0)),
    companies: sorted.map((k) => Number(coMap[k] ?? 0)),
  }
})

const hasWeeklyTrend = computed(() => mergedWeeklyTrend.value.labels.length > 0)

const chartThemeKey = computed(() => (darkMode.isDark.value ? 'd' : 'l'))

const platformTrendLineData = computed(() => {
  const m = mergedWeeklyTrend.value

  return {
    labels: m.labels,
    datasets: [
      {
        label: 'أوامر عمل جديدة',
        data: m.workOrders,
        yAxisID: 'y',
        borderColor: 'rgb(139, 92, 246)',
        backgroundColor: 'rgba(139, 92, 246, 0.1)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 5,
      },
      {
        label: 'شركات جديدة',
        data: m.companies,
        yAxisID: 'y1',
        borderColor: 'rgb(13, 148, 136)',
        backgroundColor: 'rgba(13, 148, 136, 0.08)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 5,
      },
    ],
  }
})

const platformTrendLineOptions = computed(() => {
  const d = darkMode.isDark.value
  const tick = d ? '#94a3b8' : '#64748b'
  const grid = d ? 'rgba(148,163,184,0.14)' : 'rgba(100,116,139,0.12)'

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
      legend: {
        position: 'bottom' as const,
        rtl: true,
        labels: { color: tick, boxWidth: 10, padding: 14, font: { size: 11 } },
      },
      tooltip: {
        rtl: true,
        titleAlign: 'right' as const,
        bodyAlign: 'right' as const,
      },
    },
    scales: {
      x: {
        ticks: { color: tick, maxRotation: 40, autoSkip: true, maxTicksLimit: 16 },
        grid: { color: grid },
      },
      y: {
        type: 'linear' as const,
        display: true,
        position: 'right' as const,
        beginAtZero: true,
        title: { display: true, text: 'أوامر العمل', color: tick, font: { size: 10 } },
        ticks: { color: tick },
        grid: { color: grid },
      },
      y1: {
        type: 'linear' as const,
        display: true,
        position: 'left' as const,
        beginAtZero: true,
        title: { display: true, text: 'شركات', color: tick, font: { size: 10 } },
        ticks: { color: tick },
        grid: { drawOnChartArea: false },
      },
    },
  }
})

const PLAN_CHART_COLORS = [
  'rgba(139, 92, 246, 0.88)',
  'rgba(45, 212, 191, 0.88)',
  'rgba(251, 191, 36, 0.9)',
  'rgba(56, 189, 248, 0.88)',
  'rgba(244, 63, 94, 0.82)',
  'rgba(100, 116, 139, 0.85)',
]

const planDoughnutData = computed(() => {
  const rows = planDistribution.value

  return {
    labels: rows.map((r) => r.label),
    datasets: [
      {
        data: rows.map((r) => r.count),
        backgroundColor: rows.map((_, i) => PLAN_CHART_COLORS[i % PLAN_CHART_COLORS.length]),
        borderWidth: 2,
        borderColor: darkMode.isDark.value ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.95)',
      },
    ],
  }
})

const planDoughnutOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '58%',
  plugins: {
    legend: { display: false },
    tooltip: {
      rtl: true,
      callbacks: {
        label(ctx: { dataset: { data: number[] }; parsed: number; label: string }) {
          const data = ctx.dataset.data
          const total = data.reduce((a, b) => a + Number(b), 0)
          const v = Number(ctx.parsed)
          const pct = total > 0 ? Math.round((v / total) * 100) : 0

          return ` ${ctx.label}: ${v.toLocaleString('ar-SA')} (${pct}٪)`
        },
      },
    },
  },
}))

const subscriptionBreakdownRows = computed(() => {
  const rows = pulsePayload.value?.breakdown?.by_status?.subscriptions
  if (!Array.isArray(rows)) return []

  return rows.filter((r) => Number(r.count) > 0)
})

const hasSubscriptionMixChart = computed(() => subscriptionBreakdownRows.value.length > 0)

function subscriptionStatusArLabel(s: string): string {
  const m: Record<string, string> = {
    active: 'نشط',
    trialing: 'تجريبي',
    canceled: 'ملغى',
    cancelled: 'ملغى',
    past_due: 'متأخر السداد',
    unpaid: 'غير مسدد',
    incomplete: 'غير مكتمل',
    incomplete_expired: 'منتهي غير مكتمل',
    paused: 'متوقف',
  }

  return m[String(s).toLowerCase()] ?? s
}

const subscriptionMixBarData = computed(() => {
  const rows = subscriptionBreakdownRows.value

  return {
    labels: rows.map((r) => subscriptionStatusArLabel(r.status)),
    datasets: [
      {
        label: 'اشتراكات',
        data: rows.map((r) => r.count),
        backgroundColor: rows.map((_, i) => `hsla(${248 - i * 38}, 68%, 52%, 0.75)`),
        borderColor: rows.map((_, i) => `hsl(${248 - i * 38}, 68%, 42%)`),
        borderWidth: 1,
      },
    ],
  }
})

const subscriptionMixBarOptions = computed(() => {
  const d = darkMode.isDark.value
  const tick = d ? '#94a3b8' : '#64748b'
  const grid = d ? 'rgba(148,163,184,0.12)' : 'rgba(100,116,139,0.1)'

  return {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { color: tick }, grid: { color: grid } },
      y: { ticks: { color: tick }, grid: { display: false } },
    },
  }
})

const companyMixBarData = computed(() => {
  const s = pulsePayload.value?.summary
  if (!s) {
    return { labels: [] as string[], datasets: [] as { label: string; data: number[]; backgroundColor: string[]; borderColor: string[]; borderWidth: number }[] }
  }
  const op = Number(s.companies_operational ?? 0)
  const sus = Number(s.companies_suspended ?? 0)
  const oth = Number(s.companies_other ?? 0)

  return {
    labels: ['تشغيل طبيعي', 'موقوفة', 'أخرى'],
    datasets: [
      {
        label: 'عدد الشركات',
        data: [op, sus, oth],
        backgroundColor: [
          'rgba(16, 185, 129, 0.78)',
          'rgba(251, 146, 60, 0.82)',
          'rgba(148, 163, 184, 0.78)',
        ],
        borderColor: ['rgb(5, 150, 105)', 'rgb(234, 88, 12)', 'rgb(71, 85, 105)'],
        borderWidth: 1,
      },
    ],
  }
})

const hasCompanyMixChart = computed(() => {
  const s = pulsePayload.value?.summary
  if (!s) return false
  const sum = Number(s.companies_operational ?? 0) + Number(s.companies_suspended ?? 0) + Number(s.companies_other ?? 0)

  return sum > 0
})

const companyMixBarOptions = computed(() => {
  const d = darkMode.isDark.value
  const tick = d ? '#94a3b8' : '#64748b'
  const grid = d ? 'rgba(148,163,184,0.12)' : 'rgba(100,116,139,0.1)'

  return {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { color: tick }, grid: { color: grid } },
      y: { ticks: { color: tick }, grid: { display: false } },
    },
  }
})

const kpiCard = {
  purple: 'border-violet-200/90 bg-gradient-to-br from-violet-50 to-white dark:border-violet-900/50 dark:from-violet-950/40 dark:to-slate-900',
  green: 'border-emerald-200/90 bg-gradient-to-br from-emerald-50 to-white dark:border-emerald-900/50 dark:from-emerald-950/35 dark:to-slate-900',
  yellow: 'border-amber-200/90 bg-gradient-to-br from-amber-50 to-white dark:border-amber-900/45 dark:from-amber-950/30 dark:to-slate-900',
  slate: 'border-slate-200/90 bg-gradient-to-br from-slate-50 to-white dark:border-slate-700 dark:from-slate-800/50 dark:to-slate-900',
  teal: 'border-teal-200/90 bg-gradient-to-br from-teal-50 to-white dark:border-teal-900/45 dark:from-teal-950/30 dark:to-slate-900',
  amber: 'border-orange-200/90 bg-gradient-to-br from-orange-50 to-white dark:border-orange-900/40 dark:from-orange-950/25 dark:to-slate-900',
} as const

const overviewKpis = computed(() => {
  const s = pulsePayload.value?.summary
  const list = companies.value
  const trialPage = list.filter((c: any) => c.plan_slug === 'trial').length
  const pageActive = list.filter(
    (c: any) => c.is_active !== false && c.company_status !== 'suspended',
  ).length

  if (s && typeof s.companies_total === 'number') {
    return [
      { key: 'ct', label: 'شركات المنصة', value: s.companies_total.toLocaleString('ar-SA'), hint: 'إجمالي مسجّل', cardClass: kpiCard.purple },
      { key: 'co', label: 'تشغيل نِطاقي', value: (s.companies_operational ?? 0).toLocaleString('ar-SA'), hint: 'نشطة ومفعّلة', cardClass: kpiCard.green },
      { key: 'ut', label: 'مستخدمون', value: (s.users_total ?? 0).toLocaleString('ar-SA'), hint: 'كل الحسابات', cardClass: kpiCard.slate },
      { key: 'cust', label: 'عملاء نهائيون', value: (s.customers_total ?? 0).toLocaleString('ar-SA'), hint: 'سجل العملاء', cardClass: kpiCard.teal },
      { key: 'wo', label: 'أوامر عمل (30 يوماً)', value: (s.work_orders_in_period ?? 0).toLocaleString('ar-SA'), hint: 'في نطاق التقرير', cardClass: kpiCard.amber },
      {
        key: 'tr',
        label: 'باقة تجريبية (الصفحة)',
        value: trialPage.toLocaleString('ar-SA'),
        hint: companiesTotalCount.value > list.length ? 'من أول صفحة فقط' : 'من البيانات المحمّلة',
        cardClass: kpiCard.yellow,
      },
    ]
  }

  const total = companiesFeedOk.value ? companiesTotalCount.value || list.length : list.length
  return [
    {
      key: 'tc',
      label: 'إجمالي المشتركين',
      value: total.toLocaleString('ar-SA'),
      hint: companiesFeedOk.value ? 'من الخادم (تصفح)' : 'بدون واجهة الشركات',
      cardClass: kpiCard.purple,
    },
    { key: 'at', label: 'نشطون (الصفحة)', value: pageActive.toLocaleString('ar-SA'), hint: 'من الصفحة الحالية', cardClass: kpiCard.green },
    { key: 'tr', label: 'تجريبي (الصفحة)', value: trialPage.toLocaleString('ar-SA'), hint: '', cardClass: kpiCard.yellow },
  ]
})

const overviewKpiFootnote = computed(() => {
  if (pulsePayload.value?.summary) {
    return 'المؤشرات الأساسية من تقرير نبض المنصة الموحّد؛ صف «باقة تجريبية» يعتمد على صفحة المشتركين المحمّلة حالياً.'
  }
  if (!companiesFeedOk.value) {
    return 'تعذّر تحميل قائمة الشركات — جرّب التحقق من الصلاحيات أو المسار /admin/companies. يمكنك ما زال استخدام أقسام لوحة المنصة من القائمة الجانبية إن رخّصها الخادم.'
  }
  if (companiesTotalCount.value > companies.value.length) {
    return `إجمالي الشركات على المنصة ${companiesTotalCount.value.toLocaleString('ar-SA')} — الجداول تعرض أول ${companies.value.length.toLocaleString('ar-SA')} صفاً حسب إعدادات التصفح من الخادم.`
  }
  return 'الإيراد الشهري المعروض يستند إلى سعر الباقة في الكتالوج وليس مبالغ محصّلة فعلياً من العملاء.'
})

function planDisplayName(slug: string, planNameFromApi?: string): string {
  if (planNameFromApi && String(planNameFromApi).trim() !== '' && planNameFromApi !== '—') {
    return String(planNameFromApi)
  }
  const p = plans.value.find((x: any) => x.slug === slug)
  if (p?.name_ar) return String(p.name_ar)
  if (p?.name) return String(p.name)
  const fallback: Record<string, string> = {
    trial: 'تجريبي',
    basic: 'أساسي',
    professional: 'احترافي',
    enterprise: 'مؤسسي',
  }

  return fallback[slug] || slug || '—'
}

function subscriptionStatusLabel(status: string | null | undefined): string {
  const s = String(status ?? '').toLowerCase()
  const map: Record<string, string> = {
    active: 'نشط',
    grace_period: 'فترة سماح',
    suspended: 'موقوف',
  }

  return map[s] || (s ? s : '—')
}

function companyRowStatusLabel(c: any): string {
  if (c.company_status === 'suspended') return 'شركة موقوفة'
  if (c.is_active === false) return 'غير مفعّلة'
  if (c.company_status === 'inactive') return 'غير نشطة'

  return c.is_active ? 'تشغيل طبيعي' : '—'
}

function openTenantFromOverview(c: any): void {
  void openTenantOps(c)
}

function normalizePlanFeatures(raw: any): Record<string, boolean> {
  const out: Record<string, boolean> = { ...allFeatures }
  if (!raw) return out
  if (Array.isArray(raw)) {
    raw.forEach((t: string) => {
      const k = t === 'api' ? 'api_access' : t
      out[k] = true
    })
    return out
  }
  if (typeof raw === 'object') {
    Object.assign(out, raw)
  }
  return out
}

function editPlan(plan: any) {
  editingPlan.value = JSON.parse(JSON.stringify(plan))
  editingPlan.value.features = normalizePlanFeatures(plan.features)
}

function toggleFeature(feat: string) {
  if (editingPlan.value) editingPlan.value.features[feat] = !editingPlan.value.features[feat]
}

async function savePlan() {
  if (!editingPlan.value?.slug) return
  const p = editingPlan.value
  const features = Object.entries(p.features || {}).filter(([, v]) => v).map(([k]) => k)
  try {
    const { data } = await apiClient.put(`/plans/${p.slug}`, {
      price_monthly: p.price_monthly,
      price_yearly: p.price_yearly,
      features,
    })
    const updated = data?.data ?? p
    const idx = plans.value.findIndex(x => x.slug === p.slug)
    if (idx >= 0) plans.value[idx] = { ...plans.value[idx], ...updated }
    toast.success('تم الحفظ', 'تم تحديث الباقة.')
    editingPlan.value = null
    await fetchData()
  } catch (e: any) {
    const msg = e.response?.data?.message || 'فشل الحفظ'
    toast.error('فشل الحفظ', msg)
  }
}

const featureLabels: Record<string, string> = {
  pos: 'نقطة البيع', invoices: 'الفواتير', work_orders: 'أوامر العمل',
  fleet: 'إدارة الأسطول', reports: 'التقارير', api_access: 'وصول API',
  zatca: 'ZATCA المرحلة 2', booking: 'نظام الحجوزات',
}
const featureLabel = (f: string) => featureLabels[f] || f
const formatCurrency = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  const x = new Date(d)

  return Number.isNaN(x.getTime()) ? '—' : x.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}

// Inline components
const PlanBadge = defineComponent({
  props: {
    plan: { type: String, default: '' },
    label: { type: String, default: '' },
  },
  setup(props) {
    const displayText = computed(() => {
      const lbl = props.label
      if (lbl && String(lbl).trim() !== '') return String(lbl)
      const m: Record<string, string> = {
        trial: 'تجريبي',
        basic: 'أساسي',
        professional: 'احترافي',
        enterprise: 'مؤسسي',
      }
      const p = props.plan

      return m[p] || p || '—'
    })
    const badgeClass = computed(() => {
      const p = props.plan
      const map: Record<string, string> = {
        trial: 'bg-amber-500/15 text-amber-800 ring-1 ring-amber-500/25 dark:text-amber-300',
        basic: 'bg-sky-500/15 text-sky-800 ring-1 ring-sky-500/25 dark:text-sky-300',
        professional: 'bg-violet-500/15 text-violet-800 ring-1 ring-violet-500/25 dark:text-violet-300',
        enterprise: 'bg-emerald-500/15 text-emerald-800 ring-1 ring-emerald-500/25 dark:text-emerald-300',
      }

      return map[p] || 'bg-slate-500/10 text-slate-700 ring-1 ring-slate-500/20 dark:text-slate-300'
    })

    return { displayText, badgeClass }
  },
  template:
    '<span :class="[badgeClass, \'inline-flex max-w-full items-center truncate rounded-full px-2 py-0.5 text-xs font-semibold\']">{{ displayText }}</span>',
})

watch(
  () => sidebarNavItems.value.map((t) => t.id).join(','),
  () => {
    if (!sidebarNavItems.value.some((t) => t.id === activeSection.value)) {
      activeSection.value = 'overview'
    }
  },
)

onMounted(() => {
  if (!auth.isPlatform) {
    void router.replace('/dashboard')
    return
  }
  void fetchData()
})
</script>
