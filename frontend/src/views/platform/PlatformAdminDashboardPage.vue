<template>
  <!-- محتوى مسارات /platform/* — الشريط الجانبي في PlatformAdminLayout -->
  <div class="app-shell-page space-y-6" dir="rtl">
    <div class="page-head">
      <div class="page-title-wrap">
        <p class="text-[11px] font-bold tracking-wide text-primary-700 dark:text-primary-400">أسس برو — إدارة المنصة</p>
        <h1 class="page-title-xl">{{ activePlatformSectionLabel }}</h1>
        <p class="page-subtitle">{{ pageHeadSubtitle }}</p>
        <nav class="mt-1 flex flex-wrap items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400" aria-label="breadcrumb">
          <RouterLink to="/platform/overview" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">إدارة المنصة</RouterLink>
          <span>/</span>
          <span class="font-semibold text-slate-700 dark:text-slate-200">{{ activePlatformSectionLabel }}</span>
        </nav>
      </div>
      <div class="page-toolbar">
        <div class="flex items-center rounded-xl border border-slate-300/80 bg-white/80 p-0.5 shadow-sm dark:border-white/10 dark:bg-white/5" role="group" aria-label="مظهر العرض">
          <button
            type="button"
            class="rounded-lg p-2 transition-colors"
            :class="!darkMode.isDark.value && darkMode.themeMode.value === 'manual' ? 'bg-primary-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
            title="الوضع النهاري"
            @click="darkMode.setLight()"
          >
            <SunIcon class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded-lg p-2 transition-colors"
            :class="darkMode.isDark.value && darkMode.themeMode.value === 'manual' ? 'bg-primary-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
            title="الوضع الليلي"
            @click="darkMode.setDark()"
          >
            <MoonIcon class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded-lg px-2 py-1.5 text-xs font-bold transition-colors"
            :class="darkMode.themeMode.value === 'auto' ? 'bg-primary-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
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
      </div>
    </div>

    <PlatformAdminOverviewHub v-if="auth.isPlatform && sectionKey === 'overview'" class="mx-auto max-w-[1600px]" />

    <PlatformAdminQuickNav />

    <div class="mx-auto max-w-[1600px]">
      <PlatformAdminWelcomeStrip v-if="auth.isPlatform" />

      <PlatformSubscriptionAttentionBanner v-if="auth.isPlatform" />

      <PlatformAdminInPageNav
        v-if="auth.isPlatform && inPageNavItems.length > 0"
        :items="inPageNavItems"
      />

      <!-- ══ SECTION: OVERVIEW — Phase 3: مكوّن مستقل ══ -->
      <PlatformAdminOverviewSection
        v-if="sectionVisible('overview')"
        :is-platform="auth.isPlatform"
        :executive-overview-tick="executiveOverviewTick"
        :platform-banner="platformBanner"
        :companies-feed-ok="companiesFeedOk"
        :companies-total-count="companiesTotalCount"
        :companies-page-length="companies.length"
        :plans-load-ok="plansLoadOk"
        :pulse-payload="pulsePayload"
        :pulse-loading="pulseLoading"
        :pulse-report-period="pulseReportPeriod"
        :show-platform-companies-empty-callout="showPlatformCompaniesEmptyCallout"
        :platform-overview-loading="platformOverviewLoading"
        :companies="companies"
        :plan-display-name="planDisplayName"
        :finance-why-engine-teaser="financeWhyEngineTeaser"
        @go-section="goToPlatformSection"
        @open-tenant="openTenantOps"
      />

      <PlatformAdminGovernanceSection
        v-if="auth.isPlatform && sectionVisible('governance')"
        :user="governanceUserDisplay"
        :platform-role="governancePlatformRole"
        :principal-label="governancePrincipalLabel"
        :permissions-count="governancePermissionsCount"
        :api-base-display="governanceApiBaseDisplay"
        :version-loading="governanceVersionLoading"
        :version-error="governanceVersionError"
        :version-payload="governanceVersionPayload"
      />

      <!-- ══ SECTION: OPS — Phase 3 إغلاق ══ -->
      <PlatformAdminOpsSection
        v-if="auth.isPlatform && sectionVisible('ops')"
        :ops-loading="opsLoading"
        :ops-error="opsError"
        :ops-summary="opsSummary"
        @go-operator-commands="goToPlatformSection('operator-commands')"
        @open-qa="openPlatformQa"
        @refresh-ops-summary="loadOpsSummary"
      />

      <!-- ══ SECTION: TENANTS ══ -->
      <section v-if="auth.isPlatform && sectionVisible('tenants')" id="admin-section-tenants" class="scroll-mt-32 mb-16">
        <div class="mb-4 border-b border-slate-200 pb-3 dark:border-slate-700">
          <h2 class="platform-admin-section-heading">المشتركون (شركات المنصة)</h2>
          <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
            سطح إشراف ومراقبة على مستوى المنصة: حالة الشركات، المخاطر، الإيراد، والأولوية التشغيلية مع نزول مباشر لملف الشركة داخل سياق المنصة.
          </p>
        </div>
        <div
          v-if="auth.isPlatform && !companiesFeedOk"
          class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100"
        >
          لم تُحمَّل قائمة الشركات من الخادم — جدول المشتركين يبقى فارغاً حتى ينجح جلب المشتركين (صلاحيات مشغّل المنصة).
        </div>
        <PlatformAdminCompaniesSection
          :companies="filteredCompanies"
          :all-companies="companies"
          :loading="platformOverviewLoading"
          :companies-feed-ok="companiesFeedOk"
          :search-query="tenantSearch"
          :status-filter="tenantStatusFilter"
          :risk-filter="tenantRiskFilter"
          :revenue-filter="tenantRevenueFilter"
          :plan-filter="tenantPlanFilter"
          @update:search-query="tenantSearch = $event"
          @update:status-filter="tenantStatusFilter = $event"
          @update:risk-filter="tenantRiskFilter = $event"
          @update:revenue-filter="tenantRevenueFilter = $event"
          @update:plan-filter="tenantPlanFilter = $event"
          @open-company="onCompaniesOpenCompany"
        />
      </section>

      <!-- ══ SECTION: CUSTOMERS — سجل عملاء المستأجرين عبر المنصة (قراءة منصة) ══ -->
      <section v-if="auth.isPlatform && sectionVisible('customers')" id="admin-section-customers" class="scroll-mt-32 mb-16">
        <div
          id="platform-customers-head"
          class="scroll-mt-28 mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 dark:border-slate-700 sm:flex-row sm:items-end sm:justify-between"
        >
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">عملاء المنصة (عرض شامل)</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              قائمة عملاء المستأجرين عبر جميع الشركات — مصدرها
              <span class="font-mono text-[10px]" dir="ltr">GET /api/v1/platform/customers</span>
              بلا سياق شركة في الطلب. التعديل التفصيلي لملف العميل يبقى داخل بوابة فريق العمل للشركة المعنية.
            </p>
          </div>
          <PlatformOperationsExitLink
            to="/customers"
            aria-name="شاشة العملاء في فريق العمل"
            variant="toolbar"
            class="shrink-0"
          >
            عملاء فريق العمل
          </PlatformOperationsExitLink>
        </div>
        <div id="platform-customers-filters" class="scroll-mt-28 mb-4 flex flex-wrap items-end gap-3">
          <input
            v-model="platformCustomerSearch"
            type="search"
            placeholder="بحث بالاسم أو الهاتف أو البريد…"
            class="min-w-48 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            @keyup.enter="loadPlatformCustomers(1)"
          />
          <select v-model="platformCustomerStatus" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="loadPlatformCustomers(1)">
            <option value="all">كل الحالات</option>
            <option value="active">نشط</option>
            <option value="inactive">غير مفعّل</option>
          </select>
          <select v-model="platformCustomerCompanyFilter" class="min-w-44 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="loadPlatformCustomers(1)">
            <option value="">كل الشركات</option>
            <option v-for="co in companies" :key="'pco-'+co.id" :value="co.id">{{ co.name }}</option>
          </select>
          <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700" @click="loadPlatformCustomers(1)">بحث</button>
        </div>
        <div id="platform-customers-table" class="scroll-mt-28">
          <div v-if="platformCustomersLoading" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">جاري تحميل عملاء المنصة…</div>
          <div
            v-else-if="platformCustomersError"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100"
          >
            تعذر تحميل قائمة العملاء — تحقق من الصلاحية
            <span class="font-mono text-xs" dir="ltr">platform.companies.read</span>
            والشبكة ثم أعد المحاولة.
          </div>
          <div
            v-else
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-gray-900 dark:shadow-none"
          >
            <table class="w-full min-w-[720px] text-sm">
              <thead class="bg-slate-100 dark:bg-gray-800/50">
                <tr>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الشركة</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">تاريخ الإنشاء</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
                <tr v-if="platformCustomersRows.length === 0">
                  <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                    لا يوجد عملاء يطابقون التصفية — أو لا توجد بيانات عملاء بعد على المنصة.
                  </td>
                </tr>
                <tr v-for="row in platformCustomersRows" :key="'pc-'+row.id" class="hover:bg-slate-50 dark:hover:bg-gray-800/30">
                  <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ row.name }}</td>
                  <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ row.company_name }}<span v-if="row.company_id" class="mr-1 font-mono text-[10px] text-slate-400" dir="ltr">#{{ row.company_id }}</span></td>
                  <td class="px-4 py-3 text-xs">{{ row.status_label }}</td>
                  <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(row.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="platformCustomersPagination && platformCustomersPagination.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 dark:text-slate-400">
            <span>صفحة {{ platformCustomersPagination.current_page }} من {{ platformCustomersPagination.last_page }} — {{ platformCustomersPagination.total.toLocaleString('ar-SA') }} عميلاً</span>
            <div class="flex gap-2">
              <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40 dark:border-slate-600"
                :disabled="platformCustomersPagination.current_page <= 1 || platformCustomersLoading"
                @click="loadPlatformCustomers(platformCustomersPagination.current_page - 1)"
              >
                السابق
              </button>
              <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40 dark:border-slate-600"
                :disabled="platformCustomersPagination.current_page >= platformCustomersPagination.last_page || platformCustomersLoading"
                @click="loadPlatformCustomers(platformCustomersPagination.current_page + 1)"
              >
                التالي
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- ══ SECTION: PLANS ══ -->
      <section v-if="auth.isPlatform && sectionVisible('plans')" id="admin-section-plans" class="scroll-mt-32 mb-16">
        <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 dark:border-slate-700 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">الباقات والتمكين</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              كتالوج الباقات على مستوى المنصة (أسعار وميزات). إنشاء باقة جديدة من هذه الواجهة غير متاح؛ يُضبط التسعير والباقات على الخادم حسب سياسة التشغيل.
              الإضافات المدفوعة أدناه تسمح ببيع ميزات (مثل تقارير ذكية) بسعر إضافي فوق الباقة دون إنشاء باقة جديدة.
            </p>
          </div>
        </div>

        <div id="platform-plans-catalog" class="scroll-mt-28">
          <div v-if="platformOverviewLoading" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">جاري تحميل كتالوج الباقات…</div>
          <div
            v-else-if="!plansLoadOk"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100"
          >
            تعذّر تحميل كتالوج الباقات من الخادم — لن تُعرض البطاقات حتى ينجح طلب الباقات (صلاحيات مشغّل المنصة والشبكة).
          </div>
          <div
            v-else-if="plans.length === 0"
            class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-400"
          >
            لا توجد باقات في الكتالوج — تحقق من إعدادات الخادم أو البذور التجريبية إن وُجدت.
          </div>

          <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div v-for="plan in plans" :key="plan.id"
                 class="rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600"
            >
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ plan.name }}</h3>
                <span class="text-xs bg-primary-600/30 text-primary-300 px-2 py-0.5 rounded-full">{{ plan.slug }}</span>
              </div>
              <div class="mb-1 text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400 md:text-3xl">{{ formatCurrency(plan.price_monthly) }}<span class="text-sm font-medium text-slate-500 dark:text-slate-400"> شهرياً</span></div>
              <div class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ formatCurrency(plan.price_yearly) }} سنوياً</div>
              <div class="space-y-1.5 mb-4">
                <div v-for="(val, feat) in (plan.features || {})" :key="feat" class="flex items-center gap-2 text-sm">
                  <CheckIcon v-if="val" class="w-4 h-4 text-emerald-400 flex-shrink-0" />
                  <XMarkIcon v-else class="w-4 h-4 text-gray-600 flex-shrink-0" />
                  <span :class="val ? 'text-gray-300' : 'text-gray-600'">{{ featureLabel(String(feat)) }}</span>
                </div>
              </div>
              <div class="mb-3 text-xs text-gray-500">المشتركون: <span class="font-bold text-slate-900 dark:text-white">{{ plan.subscribers_count || 0 }}</span></div>
              <button class="w-full rounded-lg border border-slate-300 py-2 text-sm text-slate-600 transition-all hover:border-primary-500 hover:text-slate-900 dark:border-gray-700 dark:text-gray-400 dark:hover:border-primary-500 dark:hover:text-white" @click="editPlan(plan)">
                تعديل الباقة
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="plansLoadOk"
          id="platform-plans-addons"
          class="scroll-mt-28 mt-10 rounded-2xl border border-primary-200/80 bg-gradient-to-bl from-primary-50/90 via-white to-slate-50/90 p-5 dark:border-primary-900/40 dark:from-primary-900/20 dark:via-slate-900 dark:to-slate-950"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">الإضافات المدفوعة على الباقة</h2>
              <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                كتالوج إضافات المنصة: كل إضافة لها سعر ومفتاح تمكين (`feature_key`) يُدمَج في اشتراك الشركة عند التفعيل.
                يمكنك إنشاء إضافة جديدة دون إعادة نشر الخادم (صلاحية كتالوج الباقات).
              </p>
            </div>
            <button
              v-if="plans.length > 0"
              type="button"
              class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700"
              @click="openCreateAddonModal"
            >
              <PlusIcon class="h-4 w-4" aria-hidden="true" />
              إنشاء إضافة
            </button>
          </div>

          <p v-if="planAddons.length === 0" class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white/60 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
            لا توجد إضافات في الكتالوج بعد. استخدم «إنشاء إضافة» لتعريف أول إضافة، أو شغّل البذور التجريبية على الخادم إن وُجدت.
          </p>

          <div v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="a in planAddons"
              :key="a.id"
              class="rounded-xl border border-slate-200/90 bg-white/95 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70"
            >
              <div class="flex items-start justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ a.name_ar || a.slug }}</h3>
                <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ a.feature_key }}</span>
              </div>
              <p v-if="a.description_ar" class="mt-2 text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">{{ a.description_ar }}</p>
              <p class="mt-3 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                {{ formatCurrency(Number(a.price_monthly || 0)) }} <span class="text-xs font-normal text-slate-500">شهرياً</span>
              </p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ formatCurrency(Number(a.price_yearly || 0)) }} سنوياً</p>
              <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                مؤهّلة لـ: {{ formatEligibleAddonPlans(a.eligible_plan_slugs) }}
              </p>
            </div>
          </div>
          <p class="mt-4 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
            للعميل: تفعيل/إلغاء من صفحة الاشتراك؛ لمشغّل المنصة: ربط إضافة بشركة عبر <span class="font-mono" dir="ltr">POST /platform/companies/{id}/subscription-addons</span>.
          </p>
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
                    <input v-model.number="editingPlan.price_monthly" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                  </div>
                  <div>
                    <label class="text-xs text-gray-400 block mb-1">السعر السنوي (ريال)</label>
                    <input v-model.number="editingPlan.price_yearly" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
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
                        <div :class="editingPlan.features[feat] ? 'bg-primary-600 border-primary-600' : 'border-slate-300 bg-slate-100 dark:border-gray-600 dark:bg-gray-800'"
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
                  <button class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium" @click="savePlan">حفظ</button>
                </div>
              </div>
            </div>
          </div>
        </Teleport>

        <Teleport to="body">
          <div v-if="showCreateAddonModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" dir="rtl" @keydown.esc="closeCreateAddonModal">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
              <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-gray-700">
                <h3 class="font-bold text-slate-900 dark:text-white">إنشاء إضافة في الكتالوج</h3>
                <button type="button" class="text-slate-500 hover:text-slate-800 dark:text-gray-400 dark:hover:text-white" @click="closeCreateAddonModal">
                  <XMarkIcon class="h-5 w-5" />
                </button>
              </div>
              <div class="space-y-4 p-5">
                <div>
                  <label class="mb-1 block text-xs text-gray-400">المعرّف الفني (slug)</label>
                  <input
                    v-model="newAddonForm.slug"
                    type="text"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="مثال: addon_my_feature"
                    dir="ltr"
                  />
                  <p class="mt-1 text-[10px] text-slate-500">أحرف إنجليزية صغيرة وأرقام وشرطة سفلية أو وسط، يبدأ بحرف أو رقم.</p>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-gray-400">مفتاح التمكين (feature_key)</label>
                  <input
                    v-model="newAddonForm.feature_key"
                    type="text"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="مثال: smart_reports"
                    dir="ltr"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs text-gray-400">الاسم (عربي) — مطلوب</label>
                  <input v-model="newAddonForm.name_ar" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                </div>
                <div>
                  <label class="mb-1 block text-xs text-gray-400">الاسم (إنجليزي — اختياري)</label>
                  <input v-model="newAddonForm.name" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" dir="ltr" />
                </div>
                <div>
                  <label class="mb-1 block text-xs text-gray-400">الوصف (عربي — اختياري)</label>
                  <textarea v-model="newAddonForm.description_ar" rows="2" class="w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-xs text-gray-400">سعر شهري (ريال)</label>
                    <input v-model.number="newAddonForm.price_monthly" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-gray-400">سعر سنوي (ريال)</label>
                    <input v-model.number="newAddonForm.price_yearly" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-gray-400">ترتيب العرض</label>
                  <input v-model.number="newAddonForm.sort_order" type="number" min="0" class="w-full max-w-[8rem] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                  <input v-model="newAddonForm.is_active" type="checkbox" class="rounded border-slate-400 text-primary-600" />
                  مفعّلة في الكتالوج
                </label>
                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-600 dark:bg-slate-800/50">
                  <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                    <input v-model="newAddonForm.eligible_all" type="checkbox" class="rounded border-slate-400 text-primary-600" />
                    مؤهّلة لجميع الباقات
                  </label>
                  <p v-if="!newAddonForm.eligible_all" class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">اختر باقة واحدة على الأقل.</p>
                  <div v-if="!newAddonForm.eligible_all" class="mt-2 max-h-36 space-y-2 overflow-y-auto">
                    <label v-for="p in plans" :key="String(p.slug)" class="flex cursor-pointer items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                      <input v-model="newAddonForm.eligible_plan_slugs[String(p.slug)]" type="checkbox" class="rounded border-slate-400 text-primary-600" />
                      <span>{{ p.name_ar || p.name || p.slug }}</span>
                      <span class="font-mono text-[10px] text-slate-400" dir="ltr">({{ p.slug }})</span>
                    </label>
                  </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                  <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-gray-400 dark:hover:bg-gray-800" @click="closeCreateAddonModal">إلغاء</button>
                  <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                    :disabled="creatingAddon"
                    @click="submitCreateAddon"
                  >
                    {{ creatingAddon ? '…' : 'حفظ الإضافة' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Teleport>
      </section>

      <!-- ══ SECTION: أوامر تنفيذية (خادم — مرجع للمشغّل) — Phase 3 Step 3 ══ -->
      <PlatformAdminOperatorCommandsSection
        v-if="auth.isPlatform && sectionVisible('operator-commands')"
        :commands="operatorCommandsForDisplay"
        @copy-command="copyOperatorCommand"
      />

      <!-- ══ SECTION: AUDIT — Phase 3 Step 6 ══ -->
      <PlatformAdminAuditSection
        v-if="auth.isPlatform && sectionVisible('audit')"
        v-model:audit-company-filter="auditCompanyFilter"
        :audit-loading="auditLoading"
        :audit-error="auditError"
        :audit-rows="auditRows"
        @load-audit="loadAuditLogs"
      />

      <!-- ══ SECTION: FINANCE — Phase 3 Step 5 ══ -->
      <PlatformAdminFinanceSection
        v-if="auth.isPlatform && sectionVisible('finance')"
        :finance-status-filter="financeStatusFilter"
        :finance-plan-filter="financePlanFilter"
        :platform-overview-loading="platformOverviewLoading"
        :companies-feed-ok="companiesFeedOk"
        :all-companies="companies"
        :rows="filteredFinanceCompanies"
        :plan-label-for-row="planDisplayName"
        @update:finance-status-filter="financeStatusFilter = $event"
        @update:finance-plan-filter="financePlanFilter = $event"
        @open-financial-edit="openFinancialEdit"
      />

      <!-- ══ SECTION: CANCELLATIONS ══ -->
      <section v-if="auth.isPlatform && sectionVisible('cancellations')" id="admin-section-cancellations" class="scroll-mt-32 mb-16">
        <div class="mb-4 border-b border-slate-200 pb-3 dark:border-slate-700">
          <h2 class="platform-admin-section-heading">إلغاء أوامر العمل</h2>
          <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
            مراجعة طلبات الإلغاء على مستوى المنصة للأوامر المعتمدة أو قيد التنفيذ. الاعتماد ينفّذ عكساً مالياً رسمياً حسب نموذج الشركة.
          </p>
        </div>
        <div id="platform-cancellations-controls" class="scroll-mt-28 mb-4 flex flex-wrap gap-3">
          <select v-model="cancelStatusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="loadCancellationRequests">
            <option value="">كل الحالات</option>
            <option value="pending">قيد المراجعة</option>
            <option value="approved">معتمد</option>
            <option value="rejected">مرفوض</option>
          </select>
          <button type="button" class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white" @click="loadCancellationRequests">تحديث</button>
        </div>
        <div id="platform-cancellations-table" class="scroll-mt-28">
          <div v-if="cancelLoading" class="text-gray-400 text-sm py-8 text-center">جارٍ التحميل…</div>
          <div
            v-else
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:shadow-none"
          >
            <table class="w-full min-w-[720px] text-sm">
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
                <tr v-if="!cancelLoading && cancelRows.length === 0">
                  <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                    لا توجد طلبات إلغاء تطابق التصفية الحالية، أو لم يُحمَّل السجل بعد — استخدم «تحديث».
                  </td>
                </tr>
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
        </div>
      </section>

      <!-- ══ SECTION: BANNER ══ -->
      <section v-if="auth.isPlatform && sectionVisible('banner')" id="admin-section-banner" class="scroll-mt-32 mb-16">
        <div class="max-w-3xl">
          <div class="mb-4 border-b border-slate-200 pb-3 dark:border-slate-700">
            <h2 class="platform-admin-section-heading">شريط الإعلان على مستوى المنصة</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              يظهر الشريط لجميع مستخدمي بوابة فريق العمل (أسس برو) أسفل شريط العنوان مباشرة.
              عند كل حفظ يُحدَّث رمز الإخفاء فيتجدد الإعلان لمن أغلق الشريط سابقًا.
            </p>
          </div>
          <div v-if="bannerLoading" class="text-gray-500 text-sm py-8">جاري التحميل…</div>
          <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-6 space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="bannerForm.is_enabled" type="checkbox" class="rounded border-gray-600 text-primary-600 focus:ring-primary-500" />
              <span class="text-sm font-medium text-slate-900 dark:text-white">تفعيل الشريط</span>
            </label>
            <div>
              <label class="text-xs text-gray-400 block mb-1">عنوان اختياري (غامق)</label>
              <input v-model="bannerForm.title" type="text" maxlength="200" placeholder="مثال: صيانة مجدولة الليلة"
                     class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>
            <div>
              <label class="text-xs text-gray-400 block mb-1">نص الإعلان</label>
              <textarea v-model="bannerForm.message" rows="4" maxlength="2000" placeholder="النص الذي يراه المستخدمون…"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white resize-y min-h-[100px]"
              />
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="text-xs text-gray-400 block mb-1">رابط (اختياري)</label>
                <input v-model="bannerForm.link_url" type="text" placeholder="رابط نسبي أو كامل، مثال: /landing"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
              <div>
                <label class="text-xs text-gray-400 block mb-1">نص الزر</label>
                <input v-model="bannerForm.link_text" type="text" maxlength="120" placeholder="تفاصيل أكثر"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
            </div>
            <div>
              <label class="text-xs text-gray-400 block mb-1">المظهر</label>
              <select v-model="bannerForm.variant"
                      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white sm:max-w-xs"
              >
                <option value="promo">ترويجي (بنفسجي / تركواز)</option>
                <option value="info">معلومات (أزرق)</option>
                <option value="success">نجاح (أخضر)</option>
                <option value="warning">تنبيه (برتقالي)</option>
              </select>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="bannerForm.dismissible" type="checkbox" class="rounded border-gray-600 text-primary-600 focus:ring-primary-500" />
              <span class="text-sm text-slate-700 dark:text-gray-300">السماح للمستخدم بإخفاء الشريط من جهازه</span>
            </label>
            <p v-if="bannerSaveMessage" class="text-sm" :class="bannerSaveError ? 'text-red-400' : 'text-emerald-400'">
              {{ bannerSaveMessage }}
            </p>
            <div class="flex justify-end pt-2">
              <button
                type="button"
                class="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-all"
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
                  <input v-model="tenantOps.is_active" type="checkbox" class="rounded border-gray-600 text-primary-600" />
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
              <button type="button" class="w-full py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm" :disabled="tenantOpsBusy" @click="saveTenantPlan">
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
              <button type="button" class="w-full py-2 bg-primary-700 hover:bg-primary-600 text-white rounded-lg text-sm" :disabled="tenantOpsBusy" @click="saveTenantVertical">
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
              <button type="button" class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg disabled:opacity-50" :disabled="financialSaving" @click="saveFinancialModel">
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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import {
  ArrowPathIcon, CheckIcon, XMarkIcon,
  SunIcon, MoonIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'
import { useDarkMode } from '@/composables/useDarkMode'
import { workOrderStatusLabel, cancellationRequestStatusLabel } from '@/utils/workOrderStatusLabels'
import { useAuthStore } from '@/stores/auth'
import PlatformAdminOverviewSection from '@/components/platform-admin/sections/PlatformAdminOverviewSection.vue'
import PlatformAdminCompaniesSection from '@/components/platform-admin/sections/PlatformAdminCompaniesSection.vue'
import PlatformAdminOperatorCommandsSection from '@/components/platform-admin/sections/PlatformAdminOperatorCommandsSection.vue'
import PlatformAdminGovernanceSection from '@/components/platform-admin/sections/PlatformAdminGovernanceSection.vue'
import PlatformAdminFinanceSection from '@/components/platform-admin/sections/PlatformAdminFinanceSection.vue'
import PlatformAdminAuditSection from '@/components/platform-admin/sections/PlatformAdminAuditSection.vue'
import PlatformAdminOpsSection from '@/components/platform-admin/sections/PlatformAdminOpsSection.vue'
import PlatformAdminQuickNav from '@/components/platform-admin/PlatformAdminQuickNav.vue'
import PlatformAdminOverviewHub from '@/components/platform-admin/PlatformAdminOverviewHub.vue'
import PlatformAdminWelcomeStrip from '@/components/platform-admin/PlatformAdminWelcomeStrip.vue'
import PlatformSubscriptionAttentionBanner from '@/components/platform-admin/PlatformSubscriptionAttentionBanner.vue'
import PlatformAdminInPageNav from '@/components/platform-admin/PlatformAdminInPageNav.vue'
import PlatformOperationsExitLink from '@/components/platform-admin/PlatformOperationsExitLink.vue'
import { platformInPageNavBySection } from '@/config/platformAdminInPageNav'
import { platformAdminNavItems, type PlatformAdminSectionId } from '@/config/platformAdminNav'
import {
  buildPlatformFinanceInsights,
  readFinanceSnapshot,
} from '@/components/platform-admin/intelligence/usePlatformInsights'

const props = defineProps<{
  /** معرف القسم (مطابق لـ ensureSectionData ومسارات المنصة) */
  sectionKey: string
}>()

/** يُستخدم في فهرس التنقل داخل صفحة الباقات — يُعرّف مبكراً لربط computed الفهرس */
const planAddons = ref<any[]>([])

const inPageNavItems = computed(() => {
  const k = props.sectionKey as PlatformAdminSectionId
  let items = platformInPageNavBySection[k] ?? []
  if (k === 'plans' && planAddons.value.length === 0) {
    items = items.filter((i) => i.id !== 'platform-plans-addons')
  }
  return items
})

/** تحميل الشركات/الباقات/النبض مرة واحدة لكل جلسة SPA — تفادي إعادة جلب عند التنقّل بين مسارات /platform/* */
let platformListsBootstrapped = false

const toast = useToast()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const darkMode = useDarkMode()

function sectionVisible(sectionId: string): boolean {
  return props.sectionKey === sectionId
}

const activePlatformSectionLabel = computed(() => {
  const hit = platformAdminNavItems.find((i) => i.id === props.sectionKey)
  return hit?.label ?? 'مركز المنصة'
})

const pageHeadSubtitle = computed(() => {
  if (props.sectionKey === 'overview') {
    return 'لوحة تشغيل موحّدة للمشتركين والباقات والنبض والتنبيهات — استخدم بطاقات الوصول السريع أو الشريط أدناه للانتقال المباشر.'
  }
  return 'أنت داخل مسار المنصة — المحتوى أدناه يتبع القسم المفتوح من القائمة الجانبية.'
})

function goToPlatformSection(sectionId: string): void {
  const hit = platformAdminNavItems.find((i) => i.id === sectionId)
  if (hit) void router.push({ name: hit.routeName })
  else void router.push({ name: 'platform-overview' })
}

function openPlatformQa(): void {
  void router.push('/admin/qa')
}

function onCompaniesOpenCompany(companyId: string | number): void {
  const id = typeof companyId === 'string' ? Number(companyId) : companyId
  if (!Number.isFinite(id)) return
  void router.push({ name: 'platform-company-detail', params: { id: String(id) } })
}


const refreshing  = ref(false)
const lastRefresh = ref(new Date().toLocaleTimeString('ar-SA'))
const tenantSearch = ref('')
const tenantPlanFilter = ref('')
const tenantStatusFilter = ref('')
const tenantRiskFilter = ref('')
const tenantRevenueFilter = ref('')
const editingPlan = ref<any>(null)

const showCreateAddonModal = ref(false)
const creatingAddon = ref(false)
type NewAddonForm = {
  slug: string
  feature_key: string
  name: string
  name_ar: string
  description_ar: string
  price_monthly: number
  price_yearly: number
  sort_order: number
  is_active: boolean
  eligible_all: boolean
  eligible_plan_slugs: Record<string, boolean>
}
const newAddonForm = ref<NewAddonForm>({
  slug: '',
  feature_key: '',
  name: '',
  name_ar: '',
  description_ar: '',
  price_monthly: 0,
  price_yearly: 0,
  sort_order: 0,
  is_active: true,
  eligible_all: true,
  eligible_plan_slugs: {},
})
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

type GovernanceVersionPayload = {
  version: string
  commit: string
  branch: string
  environment: string
  build_time?: string | null
}

const governanceVersionLoading = ref(true)
const governanceVersionError = ref('')
const governanceVersionPayload = ref<GovernanceVersionPayload | null>(null)

async function loadGovernanceSystemVersion(): Promise<void> {
  if (!auth.isPlatform) return
  governanceVersionLoading.value = true
  governanceVersionError.value = ''
  try {
    const { data } = await apiClient.get<{ data: GovernanceVersionPayload }>('/system/version', {
      skipGlobalErrorToast: true,
    })
    governanceVersionPayload.value = data.data
  } catch {
    governanceVersionError.value =
      'تعذّر جلب نسخة النظام — تحقق من الاتصال أو المسار العام /system/version'
    governanceVersionPayload.value = null
  } finally {
    governanceVersionLoading.value = false
  }
}

const governanceUserDisplay = computed(() => {
  const u = auth.user
  if (!u) return null
  return {
    name: u.name,
    email: u.email,
    is_platform_user: u.is_platform_user,
  }
})

const governancePlatformRole = computed(() => auth.user?.platform_role ?? null)

const governancePrincipalLabel = computed(() => {
  const k = auth.accountContext?.principal_kind
  if (k === 'platform_employee') return 'موظف / مشغّل منصة (سياق علوي)'
  if (k === 'staff') return 'فريق عمل مستأجر — ليس سياق منصة علوي'
  if (k === 'customer') return 'عميل — ليس إدارة منصة'
  if (k) return String(k)
  if (auth.user?.is_platform_user) return 'مشغّل منصة (من بيانات المستخدم)'
  return 'غير محدد — راجع /auth/me'
})

const governancePermissionsCount = computed(() => auth.permissions?.length ?? 0)

const governanceApiBaseDisplay = computed(() => {
  const v = import.meta.env.VITE_API_BASE_URL
  if (typeof v === 'string' && v.trim() !== '') return v.trim()
  return '/api/v1 (نسبي — نفس أصل المتصفح)'
})

function ensureSectionData(sectionId: string): void {
  if (!auth.isPlatform) return
  if (sectionId === 'governance') void loadGovernanceSystemVersion()
  if (sectionId === 'banner') void loadPlatformBannerAdmin()
  if (sectionId === 'cancellations') void loadCancellationRequests()
  if (sectionId === 'ops') void loadOpsSummary()
  if (sectionId === 'audit') void loadAuditLogs()
  if (sectionId === 'customers') void loadPlatformCustomers(1)
  if (sectionId === 'operator-commands') void loadOpsSummary()
}


/** مرجع أوامر الخادم — يُدمج مع أوامر سياقية حسب ملخص التشغيل. */
const operatorCommandsBaseline = [
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
const financePlanFilter = ref('')
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
  queue_pending_count?: number | null
} | null>(null)
const opsLoading = ref(false)
const opsError = ref(false)

const operatorCommandsForDisplay = computed(() => {
  type Cmd = { id: string; title: string; hint: string; command: string }
  const dyn: Cmd[] = []
  const o = opsSummary.value
  if (o && o.redis_ok === false) {
    dyn.push({
      id: 'ctx-redis',
      title: 'فحص ذاكرة التخزين المؤقت',
      hint: 'ذاكرة التخزين المؤقت غير متصلة — تحقق من الخدمة وملف الإعدادات.',
      command: 'docker compose exec app redis-cli ping',
    })
  }
  if (o?.failed_jobs_count != null && o.failed_jobs_count > 0) {
    dyn.push({
      id: 'ctx-retry-failed',
      title: 'إعادة محاولة المهام الفاشلة',
      hint: `يوجد ${Number(o.failed_jobs_count).toLocaleString('ar-SA')} مهمة في جدول الفشل.`,
      command: 'docker compose exec app php artisan queue:retry all',
    })
  }
  const qp = typeof o?.queue_pending_count === 'number' ? o.queue_pending_count : null
  if (qp !== null && qp > 50) {
    dyn.push({
      id: 'ctx-queue',
      title: 'معالجة طابور المهام',
      hint: `${qp.toLocaleString('ar-SA')} مهمة في انتظار التنفيذ — شغّل عامل طابور أو راقب السعة.`,
      command: 'docker compose exec app php artisan queue:work --once',
    })
  }
  if (companiesFeedOk.value && !pulsePayload.value && !pulseLoading.value) {
    dyn.push({
      id: 'ctx-overview-cache',
      title: 'تحديث كاش ملخص المنصة التنفيذي',
      hint: 'لم يُحمَّل نبض المنصة بعد — قد يكون الكاش القديم. استبدل اسم المفتاح إن تغيّر في الخادم.',
      command: 'docker compose exec app php artisan cache:forget platform:admin:overview:v2',
    })
  }
  return [...dyn, ...operatorCommandsBaseline]
})

const auditRows = ref<any[]>([])
const auditLoading = ref(false)
const auditCompanyFilter = ref('')
const auditError = ref(false)

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

const allFeatures: Record<string, boolean> = {
  pos: true, invoices: true, work_orders: true, fleet: true,
  reports: true, api_access: true, zatca: true, booking: true,
  smart_reports: true,
  work_order_advanced_pricing: true,
  dedicated_support: true,
  sla: true,
}

async function refresh() {
  refreshing.value = true
  executiveOverviewTick.value += 1
  await fetchData()
  lastRefresh.value = new Date().toLocaleTimeString('ar-SA')
  refreshing.value = false
}

function extractPlansApi(res: { data?: unknown }): { plans: any[]; planAddons: any[] } {
  const body = res?.data as Record<string, unknown> | undefined
  const d = body?.data
  const plansArr = Array.isArray(d) ? d : []
  const addonArr = Array.isArray(body?.plan_addons) ? (body.plan_addons as any[]) : []

  return { plans: plansArr, planAddons: addonArr }
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
      const extracted = extractPlansApi(plansRes)
      plans.value = extracted.plans
      planAddons.value = extracted.planAddons
      plansLoadOk.value = true
    } catch (e) {
      plans.value = []
      planAddons.value = []
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
  opsError.value = false
  try {
    const { data } = await apiClient.get('/platform/ops-summary')
    opsSummary.value = data?.data ?? null
  } catch {
    opsError.value = true
    opsSummary.value = null
    toast.error('تعذر تحميل ملخص التشغيل.')
  } finally {
    opsLoading.value = false
  }
}

async function loadAuditLogs(): Promise<void> {
  if (!auth.isPlatform) return
  auditLoading.value = true
  auditError.value = false
  try {
    const params: Record<string, string | number> = { per_page: 40 }
    const cid = auditCompanyFilter.value.trim()
    if (cid !== '' && !Number.isNaN(Number(cid))) params.company_id = Number(cid)
    const { data } = await apiClient.get('/platform/audit-logs', { params })
    auditRows.value = data?.data ?? []
  } catch {
    auditRows.value = []
    auditError.value = true
    toast.error('تعذر تحميل سجل التدقيق.')
  } finally {
    auditLoading.value = false
  }
}

const platformCustomersRows = ref<any[]>([])
const platformCustomersLoading = ref(false)
const platformCustomersError = ref(false)
const platformCustomersPagination = ref<{
  current_page: number
  last_page: number
  per_page: number
  total: number
} | null>(null)
const platformCustomerSearch = ref('')
const platformCustomerStatus = ref<'all' | 'active' | 'inactive'>('all')
const platformCustomerCompanyFilter = ref<number | ''>('')
const platformCustomersPage = ref(1)

async function loadPlatformCustomers(page = 1): Promise<void> {
  if (!auth.isPlatform) return
  platformCustomersLoading.value = true
  platformCustomersError.value = false
  platformCustomersPage.value = page
  try {
    const params: Record<string, string | number> = { page, per_page: 25 }
    const q = platformCustomerSearch.value.trim()
    if (q !== '') params.q = q
    if (platformCustomerStatus.value !== 'all') params.status = platformCustomerStatus.value
    if (platformCustomerCompanyFilter.value !== '') params.company_id = Number(platformCustomerCompanyFilter.value)
    const { data } = await apiClient.get('/platform/customers', { params })
    platformCustomersRows.value = Array.isArray(data?.data) ? data.data : []
    platformCustomersPagination.value = data?.pagination ?? null
  } catch {
    platformCustomersRows.value = []
    platformCustomersPagination.value = null
    platformCustomersError.value = true
    toast.error('تعذر تحميل عملاء المنصة.')
  } finally {
    platformCustomersLoading.value = false
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
  let list = companies.value
  const q = financeStatusFilter.value.trim()
  if (q) list = list.filter((c: any) => (c.financial_model_status ?? '') === q)
  const p = financePlanFilter.value.trim()
  if (p) list = list.filter((c: any) => String(c.plan_slug ?? '') === p)

  return list
})

/** تلميح من محرك «لماذا؟» المالي (قراءة فقط من نفس بيانات المشتركين + لقطة جلسة) */
const financeWhyEngineTeaser = computed(() => {
  if (!auth.isPlatform || !companiesFeedOk.value || companies.value.length === 0) return ''
  const insights = buildPlatformFinanceInsights(companies.value, readFinanceSnapshot())
  if (insights.length === 0) return ''
  return `محرك «لماذا؟»: تم رصد ${insights.length.toLocaleString('ar-SA')} إشارة مالية — راجع عرض المالية للتفاصيل.`
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

const filteredCompanies = computed(() => {
  let list = companies.value
  if (tenantSearch.value) list = list.filter(c => c.name?.toLowerCase().includes(tenantSearch.value.toLowerCase()))
  if (tenantPlanFilter.value) list = list.filter(c => c.plan_slug === tenantPlanFilter.value)
  const st = tenantStatusFilter.value
  if (st === 'suspended') list = list.filter(c => c.company_status === 'suspended')
  else if (st === 'struggling') {
    list = list.filter((c) =>
      String(c.subscription_status ?? '').toLowerCase() === 'grace_period'
      || c.company_status === 'suspended'
      || c.is_active === false,
    )
  } else if (st === 'active') {
    list = list.filter(c => c.is_active !== false && c.company_status !== 'suspended' && c.plan_slug !== 'trial')
  }
  if (tenantRiskFilter.value === 'high') {
    list = list.filter((c) =>
      String(c.subscription_status ?? '').toLowerCase() === 'grace_period'
      || c.company_status === 'suspended',
    )
  }
  if (tenantRiskFilter.value === 'normal') {
    list = list.filter((c) =>
      String(c.subscription_status ?? '').toLowerCase() !== 'grace_period'
      && c.company_status !== 'suspended',
    )
  }
  if (tenantRevenueFilter.value === 'high') {
    list = list.filter((c) => Number(c.monthly_revenue) >= 2000)
  }
  return list
})

watch(
  () => route.query,
  (q) => {
    if (!sectionVisible('tenants')) return
    tenantSearch.value = typeof q.q === 'string' ? q.q : ''
    tenantPlanFilter.value = typeof q.plan === 'string' ? q.plan : ''
    tenantStatusFilter.value = typeof q.status === 'string' ? q.status : ''
    tenantRiskFilter.value = typeof q.risk === 'string' ? q.risk : ''
    tenantRevenueFilter.value = typeof q.revenue === 'string' ? q.revenue : ''
  },
  { immediate: true },
)

function planDisplayName(slug: string | null | undefined, planNameFromApi?: string | null): string {
  if (planNameFromApi && String(planNameFromApi).trim() !== '' && planNameFromApi !== '—') {
    return String(planNameFromApi)
  }
  const key = String(slug ?? '').trim()
  const p = plans.value.find((x: any) => x.slug === key)
  if (p?.name_ar) return String(p.name_ar)
  if (p?.name) return String(p.name)
  const fallback: Record<string, string> = {
    trial: 'تجريبي',
    basic: 'أساسي',
    professional: 'احترافي',
    enterprise: 'مؤسسي',
  }

  return fallback[key] || key || '—'
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

function openCreateAddonModal() {
  const slugs: Record<string, boolean> = {}
  for (const p of plans.value) {
    if (p?.slug) slugs[String(p.slug)] = false
  }
  const nextSort = planAddons.value.length
    ? Math.max(...planAddons.value.map((a: { sort_order?: number }) => Number(a.sort_order) || 0)) + 1
    : 0
  newAddonForm.value = {
    slug: '',
    feature_key: '',
    name: '',
    name_ar: '',
    description_ar: '',
    price_monthly: 0,
    price_yearly: 0,
    sort_order: nextSort,
    is_active: true,
    eligible_all: true,
    eligible_plan_slugs: slugs,
  }
  showCreateAddonModal.value = true
}

function closeCreateAddonModal() {
  showCreateAddonModal.value = false
}

async function submitCreateAddon() {
  const f = newAddonForm.value
  const slug = f.slug.trim().toLowerCase()
  const fk = f.feature_key.trim()
  const nar = f.name_ar.trim()
  if (!slug || !fk || !nar) {
    toast.warning('حقول ناقصة', 'أدخل slug ومفتاح التمكين والاسم العربي.')
    return
  }
  let eligible: string[] | null = null
  if (!f.eligible_all) {
    eligible = Object.entries(f.eligible_plan_slugs)
      .filter(([, v]) => Boolean(v))
      .map(([k]) => k)
    if (eligible.length === 0) {
      toast.warning('الباقات', 'فعّل «جميع الباقات» أو اختر باقة واحدة على الأقل.')
      return
    }
  }
  creatingAddon.value = true
  try {
    await apiClient.post('/platform/plan-addons', {
      slug,
      feature_key: fk,
      name: f.name.trim() || undefined,
      name_ar: nar,
      description_ar: f.description_ar.trim() || undefined,
      price_monthly: Number(f.price_monthly),
      price_yearly: Number(f.price_yearly),
      sort_order: Number(f.sort_order) || 0,
      is_active: f.is_active,
      eligible_plan_slugs: eligible,
    })
    toast.success('تم الإنشاء', 'أُضيفت الإضافة إلى كتالوج المنصة.')
    closeCreateAddonModal()
    await fetchData()
  } catch (e: any) {
    const msg = e.response?.data?.message || 'فشل الإنشاء'
    toast.error('فشل الإنشاء', String(msg))
  } finally {
    creatingAddon.value = false
  }
}

function toggleFeature(feat: string) {
  if (editingPlan.value) editingPlan.value.features[feat] = !editingPlan.value.features[feat]
}

async function savePlan() {
  if (!editingPlan.value?.slug) return
  const p = editingPlan.value
  const features = Object.entries(p.features || {}).filter(([, v]) => v).map(([k]) => k)
  try {
    const { data } = await apiClient.put(`/platform/plans/${p.slug}`, {
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
  fleet: 'إدارة الأسطول', reports: 'التقارير', api_access: 'وصول برمجي للواجهات',
  zatca: 'الربط الضريبي — المرحلة الثانية', booking: 'نظام الحجوزات',
  smart_reports: 'التقارير الذكية والتنبيهات',
  work_order_advanced_pricing: 'تسعير أوامر عمل متقدم',
  dedicated_support: 'دعم مخصص',
  sla: 'اتفاقية مستوى الخدمة',
}
const featureLabel = (f: string) => featureLabels[f] || f

function formatEligibleAddonPlans(slugs: unknown): string {
  if (!Array.isArray(slugs) || slugs.length === 0) {
    return 'كل الباقات النشطة'
  }
  return slugs.map((s) => planDisplayName(String(s))).join('، ')
}
const formatCurrency = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  const x = new Date(d)

  return Number.isNaN(x.getTime()) ? '—' : x.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}

watch(
  () => props.sectionKey,
  (s) => {
    if (typeof s === 'string' && auth.isPlatform) ensureSectionData(s)
  },
  { immediate: true },
)

onMounted(() => {
  if (!auth.isPlatform) {
    void router.replace('/dashboard')
    return
  }
  if (!platformListsBootstrapped) {
    platformListsBootstrapped = true
    void fetchData()
    void loadOpsSummary()
  }
})
</script>
