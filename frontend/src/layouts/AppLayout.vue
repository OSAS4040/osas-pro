<template>
  <div class="min-h-screen bg-gray-50 dark:bg-slate-900 flex transition-colors" :dir="locale.langInfo.value.dir">
    <!-- Mobile Sidebar Overlay -->
    <Transition name="overlay-fade">
      <div
        v-if="mobileOpen"
        data-print-chrome
        class="print:hidden fixed inset-0 bg-black/50 z-40 lg:hidden"
        @click="mobileOpen = false"
      ></div>
    </Transition>

    <!-- Sidebar -->
    <aside
      data-print-chrome
      class="print:hidden"
      :class="[
        'flex flex-col flex-shrink-0 overflow-hidden bg-white dark:bg-slate-800 border-l dark:border-slate-700 border-gray-200 shadow-sm',
        'fixed lg:relative inset-y-0 right-0 z-50 lg:z-auto',
        mobileOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
        collapsed ? 'w-[60px]' : 'w-64',
        'transition-[width,transform] duration-200 ease-out',
      ]"
    >
      <!-- عنوان المنصّة (متعدد اللغات) + طيّ -->
      <div
        v-if="!collapsed"
        class="flex min-h-14 items-center justify-between gap-2 border-b border-gray-200 px-4 py-2.5 dark:border-slate-700 sticky top-0 z-10 flex-shrink-0 bg-white transition-colors dark:bg-slate-800"
      >
        <span class="min-w-0 flex-1 text-sm font-bold leading-snug text-gray-900 dark:text-slate-100">
          {{ locale.t('app.sidebarTitle') }}
        </span>
        <button
          type="button"
          class="flex-shrink-0 rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
          title="طيّ القائمة"
          @click="collapsed = true"
        >
          <ChevronRightIcon class="h-4 w-4" />
        </button>
      </div>

      <!-- Expand Button when collapsed -->
      <div v-if="collapsed" class="flex justify-center border-b border-gray-100 py-2 dark:border-slate-700">
        <button class="p-2 rounded-lg text-gray-400 hover:text-primary-600 hover:bg-primary-50 transition-colors" title="توسيع القائمة" @click="collapsed = false">
          <ChevronLeftIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto" :class="collapsed ? 'p-1.5 space-y-1' : 'p-3 space-y-4 pb-6'">
        <template v-if="collapsed">
          <div v-if="!staffShellReady" class="space-y-2 p-1" aria-busy="true" aria-label="جاري تهيئة القائمة">
            <div v-for="sk in 9" :key="'skc-'+sk" class="mx-auto h-10 w-10 rounded-xl bg-gray-100 dark:bg-slate-700 animate-pulse" />
          </div>
          <NavIconItem v-for="item in flatItems" v-else :key="item.to" v-bind="item" />
        </template>
        <template v-else>
          <div
            v-if="!staffShellReady"
            class="space-y-3 px-1 pb-4"
            aria-busy="true"
            aria-label="جاري تهيئة القائمة والصلاحيات"
          >
            <div class="h-10 w-full rounded-xl bg-gray-100 dark:bg-slate-700 animate-pulse" />
            <div class="space-y-2 rounded-2xl border border-gray-100/90 dark:border-slate-700/80 bg-gray-50/60 dark:bg-slate-900/35 p-3">
              <div class="h-3 w-24 rounded bg-gray-200 dark:bg-slate-600 animate-pulse" />
              <div v-for="j in 5" :key="'skn-'+j" class="h-9 w-full rounded-xl bg-white/80 dark:bg-slate-800/50 animate-pulse" />
            </div>
            <div class="space-y-2 rounded-2xl border border-gray-100/90 dark:border-slate-700/80 bg-gray-50/60 dark:bg-slate-900/35 p-3">
              <div class="h-3 w-28 rounded bg-gray-200 dark:bg-slate-600 animate-pulse" />
              <div v-for="k in 4" :key="'skn2-'+k" class="h-9 w-full rounded-xl bg-white/80 dark:bg-slate-800/50 animate-pulse" />
            </div>
          </div>
          <!-- بحث سريع في أقسام القائمة -->
          <template v-else>
          <div class="space-y-1.5 sticky top-0 z-[5] -mx-1 px-1 pb-2 bg-white/95 dark:bg-slate-800/95 backdrop-blur-sm border-b border-gray-100 dark:border-slate-700/80">
            <div class="relative">
              <MagnifyingGlassIcon class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-slate-500" />
              <input
                v-model="navQuickFilter"
                type="search"
                enterkeyhint="search"
                autocomplete="off"
                :placeholder="l('بحث في القائمة…', 'Search menu…')"
                class="w-full rounded-xl border border-gray-200 bg-gray-50/90 py-2 pr-9 pl-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/25 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 dark:placeholder:text-slate-500"
                @keydown.escape="navQuickFilter = ''"
              />
            </div>
            <div
              v-if="navQuickFilterTrimmed && filteredNavQuick.length"
              class="max-h-[min(42vh,16rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-800 dark:shadow-xl"
            >
              <RouterLink
                v-for="item in filteredNavQuick"
                :key="item.to + item.label"
                :to="item.to"
                class="flex flex-col gap-px border-b border-gray-50 px-3 py-2.5 text-right last:border-0 hover:bg-primary-50 dark:border-slate-700/80 dark:hover:bg-primary-950/35"
                @click="mobileOpen = false; navQuickFilter = ''"
              >
                <span class="text-sm font-medium text-gray-800 dark:text-slate-100">{{ item.label }}</span>
                <span class="text-[10px] text-gray-400 dark:text-slate-500">{{ item.section }}</span>
              </RouterLink>
            </div>
            <p v-else-if="navQuickFilterTrimmed" class="px-1 text-[11px] text-gray-400 dark:text-slate-500">
              {{ l('لا نتائج — جرّب Ctrl+K للبحث الشامل', 'No results — try Ctrl+K for global search') }}
            </p>
          </div>

          <NavSection section-key="operations" :label="l('تشغيلي', 'Operations')">
            <NavItem to="/" :icon="HomeIcon" :label="locale.t('nav.dashboard')" :exact="true" />
            <NavItem to="/pos" :icon="ShoppingCartIcon" :label="locale.t('nav.pos')" />
            <NavItem to="/work-orders" :icon="ClipboardDocumentIcon" :label="locale.t('nav.work_orders')" />
            <NavItem v-if="opsNavOpen" to="/bays" :icon="BuildingOfficeIcon" label="مناطق العمل" />
            <NavItem v-if="opsNavOpen" to="/bookings" :icon="CalendarDaysIcon" label="الحجوزات" />
            <NavItem
              v-if="opsNavOpen && auth.hasPermission('meetings.update')"
              to="/meetings"
              :icon="CalendarIcon"
              label="الاجتماعات"
            />
            <NavItem v-if="opsNavOpen" to="/bays/heatmap" :icon="FireIcon" label="الخريطة الحرارية" />
            <NavItem to="/customers" :icon="UsersIcon" :label="locale.t('nav.customers')" />
            <NavItem v-if="sectionEnabled('crm')" to="/crm/quotes" :icon="DocumentTextIcon" label="عروض الأسعار" />
            <NavItem v-if="sectionEnabled('crm')" to="/crm/relations" :icon="HeartIcon" label="علاقات العملاء" />
            <NavItem to="/vehicles" :icon="TruckIcon" :label="locale.t('nav.vehicles')" />
            <NavItem v-if="enabledPortals.fleet && sectionEnabled('fleet')" to="/fleet/verify-plate" :icon="MagnifyingGlassIcon" label="التحقق من اللوحة" />
            <NavItem v-if="enabledPortals.fleet && sectionEnabled('fleet')" to="/fleet/wallet" :icon="CreditCardIcon" label="محافظ الأسطول" />
          </NavSection>

          <NavSection v-if="sectionEnabled('hr')" section-key="hr" label="الموارد البشرية">
            <NavItem to="/workshop/employees" :icon="UserGroupIcon" label="إدارة الموظفين" />
            <NavItem to="/workshop/tasks" :icon="ClipboardDocumentCheckIcon" label="إدارة المهام" />
            <NavItem to="/workshop/attendance" :icon="ClockIcon" label="الحضور" />
            <NavItem to="/workshop/leaves" :icon="CalendarDaysIcon" label="الإجازات" />
            <NavItem to="/workshop/salaries" :icon="BanknotesIcon" label="مسير الرواتب" />
            <NavItem to="/workshop/commissions" :icon="CurrencyDollarIcon" label="العمولات" />
            <NavItem to="/workshop/commission-policies" :icon="AdjustmentsHorizontalIcon" label="سياسات العمولات" />
            <NavItem to="/workshop/hr-comms" :icon="ChatBubbleLeftRightIcon" label="اتصالات إدارية" />
          </NavSection>

          <NavSection
            v-if="sectionEnabled('finance') || sectionEnabled('accounting')"
            section-key="finance_accounting"
            :label="l('المالية والمحاسبة', 'Finance & Accounting')"
          >
            <template v-if="sectionEnabled('finance')">
              <NavItem to="/invoices" :icon="DocumentTextIcon" :label="locale.t('nav.invoices')" />
              <NavItem
                v-if="sectionEnabled('crm')"
                to="/crm/quotes"
                :icon="ClipboardDocumentIcon"
                label="عروض الأسعار"
              />
              <NavItem
                v-if="auth.hasPermission('reports.financial.view')"
                to="/financial-reconciliation"
                :icon="ArrowsRightLeftIcon"
                label="المطابقة المالية"
              />
              <NavItem to="/wallet" :icon="CreditCardIcon" label="المحفظة" />
              <NavItem
                v-if="
                  auth.hasPermission('wallet.top_up_requests.create')
                    || auth.hasPermission('wallet.top_up_requests.view')
                    || auth.hasPermission('wallet.top_up_requests.review')
                "
                to="/wallet/top-up-requests"
                :icon="QueueListIcon"
                label="طلبات شحن الرصيد"
              />
              <NavSubGroup group-key="purchases" label="المشتريات">
                <NavItem to="/purchases" :icon="ShoppingBagIcon" label="قائمة المشتريات" />
                <NavItem to="/purchases/new" :icon="ClipboardDocumentIcon" label="أوامر شراء" />
                <NavItem to="/suppliers" :icon="TruckIcon" label="الموردون" />
              </NavSubGroup>
            </template>
            <template v-if="sectionEnabled('accounting')">
              <NavSubGroup group-key="accountant" label="المحاسب">
                <NavItem to="/ledger" :icon="BookOpenIcon" label="القيود اليومية" />
                <NavItem to="/chart-of-accounts" :icon="TableCellsIcon" label="شجرة الحسابات" />
                <NavItem to="/zatca" :icon="BuildingOffice2Icon" label="الضرائب" />
                <NavItem to="/fixed-assets" :icon="ArchiveBoxIcon" label="الأصول الثابتة" />
              </NavSubGroup>
            </template>
          </NavSection>

          <NavSection v-if="sectionEnabled('inventory')" section-key="inventory" :label="l('المخزون', 'Inventory')">
            <NavItem to="/products" :icon="CubeIcon" label="المنتجات" />
            <NavItem to="/inventory" :icon="ArchiveBoxIcon" :label="locale.t('nav.inventory')" />
            <NavItem to="/suppliers" :icon="TruckIcon" label="الموردون" />
          </NavSection>

          <NavSection v-if="sectionEnabled('reports') || sectionEnabled('intelligence')" section-key="analytics" :label="l('التحليلات وذكاء الأعمال', 'Analytics & Intelligence')">
            <NavItem
              v-if="
                canAccessStaffBusinessIntelligence({
                  buildFlagOn: featureFlags.intelligenceCommandCenter,
                  isOwner: auth.isOwner,
                  isEnabled: (k) => biz.isEnabled(k),
                })
              "
              to="/business-intelligence"
              :icon="PresentationChartLineIcon"
              label="ذكاء الأعمال"
            />
            <NavItem v-if="sectionEnabled('reports')" to="/reports" :icon="ChartBarIcon" :label="locale.t('nav.reports')" />
            <NavItem to="/governance" :icon="ShieldCheckIcon" label="السياسات والموافقات" />
            <NavItem
              v-if="
                canAccessStaffCommandCenter({
                  buildFlagOn: featureFlags.intelligenceCommandCenter,
                  isOwner: auth.isOwner,
                  isEnabled: (k) => biz.isEnabled(k),
                  hasIntelligenceReportPermission: auth.hasPermission('reports.intelligence.view'),
                })
              "
              to="/internal/intelligence"
              :icon="SignalIcon"
              label="مركز العمليات الذكي"
            />
          </NavSection>

          <NavSection section-key="admin" :label="l('إداري', 'Admin')">
            <NavItem v-if="auth.isManager" to="/branches" :icon="BuildingLibraryIcon" label="إدارة الفروع" />
            <NavItem v-if="auth.isStaff" to="/branches/map" :icon="MapPinIcon" label="خريطة الفروع (Google)" />
            <NavItem to="/contracts" :icon="DocumentCheckIcon" label="العقود" />
            <NavItem to="/documents/company" :icon="FolderOpenIcon" label="مستندات المنشأة" />
            <NavItem to="/activity" :icon="ClipboardDocumentListIcon" label="سجل العمليات" />
            <NavItem v-if="auth.isStaff" to="/account/sessions" :icon="DevicePhoneMobileIcon" label="الأجهزة والجلسات" />
            <NavItem to="/settings" :icon="Cog6ToothIcon" :label="locale.t('nav.settings')" />
            <NavItem
              v-if="auth.isManager"
              to="/settings/team-users"
              :icon="UserGroupIcon"
              :label="teamUsersNavLabel"
            />
            <NavItem
              v-if="auth.isManager && biz.isEnabled('org_structure')"
              to="/settings/org-units"
              :icon="BuildingOffice2Icon"
              :label="orgUnitsNavLabel"
            />
            <NavItem to="/settings/integrations" :icon="WrenchScrewdriverIcon" label="التكاملات" />
            <NavItem
              v-if="auth.hasPermission('api_keys.manage')"
              to="/settings/api-keys"
              :icon="LockClosedIcon"
              label="مفاتيح API"
            />
            <NavItem to="/referrals" :icon="GiftIcon" label="الإحالات والولاء" />
            <NavItem to="/support" :icon="LifebuoyIcon" label="مركز الدعم الفني" />
          </NavSection>

          <NavSection v-if="auth.isPlatform && enabledPortals.admin" section-key="platform" label="إدارة المنصة">
            <NavItem to="/admin" :icon="CpuChipIcon" label="لوحة الأدمن" />
            <NavItem to="/admin/qa" :icon="BeakerIcon" label="التحقق من النظام (QA)" />
          </NavSection>

          <NavSection section-key="subscription" :label="l('الاشتراك', 'Subscription')">
            <NavItem to="/subscription" :icon="StarIcon" label="اشتراكي" />
            <NavItem to="/plans" :icon="RectangleStackIcon" label="الباقات" />
            <NavItem to="/plugins" :icon="SparklesIcon" label="سوق الإضافات AI" />
          </NavSection>
          </template>
        </template>
      </nav>

      <!-- إصدار الواجهة (للدعم والتأكد من التحديث) -->
      <div
        class="border-t border-gray-200 dark:border-slate-700 flex-shrink-0 bg-white dark:bg-slate-800 transition-colors"
        :class="collapsed ? 'px-1.5 py-2' : 'px-3 py-2.5'"
      >
        <div
          v-if="!collapsed"
          class="rounded-xl border border-gray-200/90 dark:border-slate-600/80 bg-gradient-to-br from-slate-50 via-white to-primary-50/50 dark:from-slate-900/90 dark:via-slate-900 dark:to-primary-950/25 px-3 py-2.5 shadow-sm"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500">
                {{ l('نشر الواجهة (دليل build)', 'Frontend release (build proof)') }}
              </p>
              <p class="mt-0.5 text-sm font-semibold font-mono text-gray-900 dark:text-slate-100 tabular-nums tracking-tight">
                v{{ appVersion }}
              </p>
            </div>
            <span
              class="flex-shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-md border whitespace-nowrap"
              :class="releaseEnvChipClass"
            >
              {{ releaseEnvLabel }}
            </span>
          </div>
          <p class="mt-2 text-[10px] text-gray-500 dark:text-slate-400 font-mono leading-relaxed space-y-0.5">
            <span class="block">{{ buildTimeLine }}</span>
            <span class="block">commit {{ gitCommit }} · {{ gitBranch }}</span>
          </p>
          <button
            type="button"
            class="mt-2 w-full inline-flex items-center justify-center gap-1.5 rounded-lg py-1.5 px-2 text-[11px] font-medium text-primary-700 dark:text-primary-300 bg-primary-50/90 dark:bg-primary-950/50 hover:bg-primary-100 dark:hover:bg-primary-900/40 border border-primary-200/70 dark:border-primary-800/60 transition-colors"
            @click="copyReleaseInfo"
          >
            <ClipboardDocumentIcon class="w-3.5 h-3.5 flex-shrink-0 opacity-80" />
            {{ l('نسخ للدعم الفني', 'Copy for support') }}
          </button>
          <RouterLink
            to="/about/deployment"
            class="mt-2 block text-center text-[10px] font-semibold text-primary-600 dark:text-primary-400 hover:underline"
          >
            {{ l('تفاصيل النشر ومقارنة الخادم', 'Deployment details and server parity') }}
          </RouterLink>
          <RouterLink
            to="/about/taxonomy"
            class="mt-1 block text-center text-[10px] font-medium text-gray-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400 hover:underline"
          >
            {{ l('مسرد المنصة والعميل والمستأجر', 'Platform / tenant / customer glossary') }}
          </RouterLink>
          <RouterLink
            to="/about/capabilities"
            class="mt-1 block text-center text-[10px] font-semibold text-primary-600 dark:text-primary-400 hover:underline"
          >
            {{ l('قدرات النظام (حسب نشاطك ودورك)', 'System capabilities (profile & role)') }}
          </RouterLink>
          <RouterLink
            to="/landing"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-1 block text-center text-[10px] font-medium text-gray-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400 hover:underline"
          >
            {{ l('صفحة أسس برو التعريفية (نافذة جديدة)', 'Osas Pro marketing page (new tab)') }}
          </RouterLink>
        </div>
        <button
          v-else
          type="button"
          class="w-full flex flex-col items-center gap-0.5 py-1 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700/80 transition-colors group"
          :title="collapsedReleaseTitle"
          @click="copyReleaseInfo"
        >
          <InformationCircleIcon class="w-5 h-5 text-primary-500 dark:text-primary-400 group-hover:scale-105 transition-transform" />
          <span class="text-[9px] font-bold font-mono text-gray-500 dark:text-slate-400 tabular-nums">v{{ appVersionShort }}</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
      <!-- Header -->
      <header
        data-print-chrome
        class="print:hidden h-16 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between px-4 lg:px-6 sticky top-0 z-10 gap-3 transition-colors"
      >
        <div class="flex items-center gap-3 min-w-0">
          <!-- Mobile Hamburger -->
          <button class="lg:hidden flex items-center justify-center w-9 h-9 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex-shrink-0" @click="mobileOpen = !mobileOpen">
            <Bars3Icon class="w-5 h-5 text-gray-600 dark:text-slate-300" />
          </button>
          <h1 class="text-base font-semibold text-gray-900 dark:text-slate-100 truncate">{{ pageTitle }}</h1>
          <span class="hidden sm:block text-xs text-gray-400 dark:text-slate-500 font-normal">|</span>
          <span class="hidden sm:block text-xs text-primary-600 dark:text-primary-400 font-medium whitespace-nowrap">{{ greeting }}، {{ auth.user?.name?.split(' ')[0] }}</span>
        </div>

        <div class="flex items-center gap-2">
          <!-- Command Palette Trigger -->
          <button
            class="hidden md:flex items-center gap-2 px-3 py-1.5 text-sm text-gray-500 dark:text-slate-400 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-lg transition-colors"
            @click="openPalette"
          >
            <MagnifyingGlassIcon class="w-4 h-4" />
            <span class="text-xs">بحث...</span>
            <kbd class="text-xs bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded px-1 text-gray-400">Ctrl+K</kbd>
          </button>

          <button
            v-if="showStaffCompactToggle"
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border transition-colors"
            :class="
              staffUi.compactMode
                ? 'border-amber-400 bg-amber-100 text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100'
                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
            "
            :title="l('وضع مركز الخدمة السريع: إخفاء تفاصيل ثانوية', 'Compact service center mode: fewer secondary details')"
            @click="staffUi.toggleCompact()"
          >
            <BoltIcon class="w-4 h-4 flex-shrink-0" />
            <span class="hidden sm:inline max-w-[7rem] truncate">{{ l('وضع سريع', 'Compact') }}</span>
          </button>

          <!-- Language Switcher -->
          <div ref="langMenuRef" class="relative">
            <button class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                    @click="langMenuOpen = !langMenuOpen"
            >
              <span class="text-base leading-none">{{ locale.langInfo.value.flag }}</span>
              <span class="hidden sm:block text-xs font-medium">{{ locale.langInfo.value.native }}</span>
              <ChevronDownIcon class="w-3 h-3 text-gray-400" />
            </button>
            <div v-if="langMenuOpen"
                 class="absolute top-full mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-lg z-50 py-1 min-w-[160px]"
                 :class="locale.langInfo.value.dir === 'rtl' ? 'left-0' : 'right-0'"
            >
              <button v-for="lang in LANGUAGES" :key="lang.code"
                      class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                      :class="locale.lang.value === lang.code ? 'text-primary-600 dark:text-primary-400 font-medium' : 'text-gray-700 dark:text-slate-300'"
                      @click="locale.setLang(lang.code); langMenuOpen = false"
              >
                <span class="text-base leading-none">{{ lang.flag }}</span>
                <span>{{ lang.native }}</span>
                <CheckIcon v-if="locale.lang.value === lang.code" class="w-3.5 h-3.5 mr-auto text-primary-600 dark:text-primary-400" />
              </button>
            </div>
          </div>

          <!-- Dark Mode Toggle -->
          <button class="p-2 rounded-lg text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                  :title="darkMode.isDark.value ? 'الوضع النهاري' : 'الوضع الليلي'"
                  @click="darkMode.toggle()"
          >
            <SunIcon v-if="darkMode.isDark.value" class="w-5 h-5" />
            <MoonIcon v-else class="w-5 h-5" />
          </button>

          <NotificationCenter class="flex-shrink-0" :api-security-notice="apiSecurityNotice" />

          <!-- تسجيل الخروج — أعلى الصفحة، نقرة واحدة + إعادة توجيه فورية -->
          <button
            type="button"
            data-testid="app-header-logout"
            class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 transition-colors hover:bg-red-50 dark:hover:bg-red-950/35 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40 disabled:opacity-50 disabled:pointer-events-none"
            :title="locale.t('nav.logout')"
            :aria-label="locale.t('nav.logout')"
            :disabled="loggingOut"
            @click="handleLogout"
          >
            <ArrowLeftOnRectangleIcon class="h-5 w-5 flex-shrink-0" />
            <span class="hidden sm:inline max-w-[7rem] truncate">{{ locale.t('nav.logout') }}</span>
          </button>

          <!-- Portal Switcher -->
          <PortalSwitcher />

          <!-- Plan badge — يظهر الباقة + حالة الفوترة من `/auth/me` مباشرة -->
          <RouterLink to="/subscription"
                      class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-lg text-xs font-medium hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                      :title="billingBadgeTitle"
          >
            <StarIcon class="w-3.5 h-3.5 flex-shrink-0" />
            <span class="flex flex-col items-start min-w-0 leading-tight">
              <span class="truncate max-w-[9.5rem]">{{ sub.planName }}</span>
              <span v-if="billingStateShort" class="text-[10px] font-normal opacity-80">{{ billingStateShort }}</span>
            </span>
          </RouterLink>

          <!-- User Avatar -->
          <RouterLink to="/profile" class="flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-lg px-2 py-1 transition-colors">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold shadow-sm">
              {{ auth.user?.name?.charAt(0) }}
            </div>
            <span class="hidden lg:block text-sm text-gray-700 dark:text-slate-300 font-medium">{{ auth.user?.name }}</span>
          </RouterLink>
        </div>
      </header>

      <PlatformPromoBanner class="relative z-[10] print:hidden" data-print-chrome />

      <!-- رجوع + فتات خبز -->
      <div
        v-if="route.name !== 'dashboard'"
        data-print-chrome
        class="no-print print:hidden px-6 py-2 border-b border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800"
      >
        <PageBackButton :fallback-to="breadcrumbParentPath" />
        <nav
          v-if="breadcrumbs.length > 1"
          class="flex items-center gap-1 text-xs text-gray-400 dark:text-slate-500 mt-1.5"
          aria-label="breadcrumb"
        >
          <template v-for="(crumb, i) in breadcrumbs" :key="crumb.path">
            <RouterLink v-if="i < breadcrumbs.length - 1" :to="crumb.path" class="hover:text-primary-600 transition-colors">{{ crumb.label }}</RouterLink>
            <span v-else class="text-gray-600 dark:text-slate-300 font-medium">{{ crumb.label }}</span>
            <ChevronRightIcon v-if="i < breadcrumbs.length - 1" class="w-3 h-3 text-gray-300" />
          </template>
        </nav>
      </div>

      <!-- Page -->
      <main class="flex-1 overflow-auto p-4 md:p-6 bg-gray-50 dark:bg-slate-900 transition-colors">
        <div
          v-if="billingNotice"
          data-print-chrome
          class="no-print print:hidden max-w-[1600px] mx-auto mb-4 rounded-xl border px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2"
          :class="billingNotice.boxClass"
          role="status"
        >
          <span>{{ billingNotice.text }}</span>
          <RouterLink
            v-if="billingNotice.showPlanLink"
            to="/subscription"
            class="text-xs font-bold whitespace-nowrap underline hover:opacity-90"
          >
            إدارة الاشتراك
          </RouterLink>
        </div>
        <div
          v-if="apiSecurityNotice"
          data-print-chrome
          class="no-print print:hidden max-w-[1600px] mx-auto mb-4 rounded-xl border border-red-300 bg-red-50 text-red-900 px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100"
          role="status"
        >
          <span>{{ apiSecurityNotice }}</span>
          <RouterLink to="/settings/api-keys" class="text-xs font-bold whitespace-nowrap underline hover:opacity-90">
            فتح مفاتيح API
          </RouterLink>
        </div>
        <div class="app-shell-page">
          <RouterView />
        </div>
      </main>
    </div>

    <!-- Global Overlays -->
    <CommandPalette ref="paletteRef" class="print:hidden" data-print-chrome />
    <ToastContainer />
    <SystemConfirmModal />
  </div>
  <AiAssistant class="print:hidden" data-print-chrome />
</template>

<script setup lang="ts">
/* eslint-disable vue/one-component-per-file -- مكوّنات تنقّل داخلية مصاحبة للتخطيط */
import { ref, computed, defineComponent, h, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import router from '@/router'
import { NAV_SEARCH_ITEMS, itemMatchesNavQuery, normNavSearch, navSearchItemVisibleForPortals } from '@/config/navSearchItems'
import { enabledPortals } from '@/config/portalAccess'
import {
  HomeIcon, DocumentTextIcon, CubeIcon, UsersIcon, ChartBarIcon, Cog6ToothIcon,
  ArrowLeftOnRectangleIcon, TruckIcon, ShoppingCartIcon, ClipboardDocumentIcon,
  BuildingOfficeIcon, CalendarDaysIcon, FireIcon, UserGroupIcon, ClockIcon,
  CurrencyDollarIcon, CreditCardIcon, BookOpenIcon, TableCellsIcon, BanknotesIcon,
  ArchiveBoxIcon, ShoppingBagIcon, ShieldCheckIcon, StarIcon, RectangleStackIcon, LifebuoyIcon,
  MagnifyingGlassIcon, ClipboardDocumentCheckIcon, ClipboardDocumentListIcon, WrenchScrewdriverIcon, DocumentCheckIcon,
  LockClosedIcon, ChevronRightIcon, ChevronLeftIcon,
  SunIcon, MoonIcon, CheckIcon, ChevronDownIcon,
  BuildingOffice2Icon, CpuChipIcon, GiftIcon, Bars3Icon, SparklesIcon,
  SignalIcon, PresentationChartLineIcon, BeakerIcon,
  ChatBubbleLeftRightIcon, FolderOpenIcon,
  AdjustmentsHorizontalIcon, HeartIcon, InformationCircleIcon,
  BuildingLibraryIcon, MapPinIcon, BoltIcon, ArrowsRightLeftIcon, CalendarIcon,
  QueueListIcon, DevicePhoneMobileIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useStaffUiStore } from '@/stores/staffUi'
import { useSubscriptionStore } from '@/stores/subscription'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { featureFlags } from '@/config/featureFlags'
import {
  canAccessStaffBusinessIntelligence,
  canAccessStaffCommandCenter,
  canAccessStaffOperationsArea,
  canAccessWorkshopArea,
  tenantSectionOpen,
} from '@/config/staffFeatureGate'
import { useLocale, LANGUAGES } from '@/composables/useLocale'
import { useDarkMode } from '@/composables/useDarkMode'
import PortalSwitcher from '@/components/PortalSwitcher.vue'
import CommandPalette from '@/components/CommandPalette.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import SystemConfirmModal from '@/components/SystemConfirmModal.vue'
import AiAssistant from '@/components/AiAssistant.vue'
import NotificationCenter from '@/components/NotificationCenter.vue'
import PageBackButton from '@/components/PageBackButton.vue'
import PlatformPromoBanner from '@/components/PlatformPromoBanner.vue'
import {
  APP_VERSION,
  APP_BUILD_TIME,
  GIT_COMMIT,
  GIT_BRANCH,
  DEPLOY_ENV,
  releaseEnvironmentLabel,
  releaseCopyLine,
  buildTimeUtcDisplay,
} from '@/config/appRelease'
import { useToast } from '@/composables/useToast'
import { useNavigationContext } from '@/composables/useNavigationContext'
import apiClient from '@/lib/apiClient'

const auth = useAuthStore()
const { staffShellReady } = useNavigationContext()
const staffUi = useStaffUiStore()
const sub  = useSubscriptionStore()
const biz = useBusinessProfileStore()
const route = useRoute()
const locale = useLocale()
const darkMode = useDarkMode()
const toast = useToast()
const paletteRef = ref<InstanceType<typeof CommandPalette> | null>(null)
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const teamUsersNavLabel = computed(() => {
  void locale.lang.value
  return locale.t('teamUsers.nav')
})
const orgUnitsNavLabel = computed(() => {
  void locale.lang.value
  return locale.t('orgUnits.nav')
})

const appVersion = APP_VERSION
const gitCommit = GIT_COMMIT
const gitBranch = GIT_BRANCH
const buildTimeLine = buildTimeUtcDisplay(APP_BUILD_TIME)
const appVersionShort = APP_VERSION
const releaseEnvLabel = releaseEnvironmentLabel()
const releaseEnvChipClass = computed(() => {
  if (DEPLOY_ENV === 'production') {
    return 'bg-emerald-100 text-emerald-900 border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800/50'
  }
  if (DEPLOY_ENV === 'staging') {
    return 'bg-sky-100 text-sky-900 border-sky-200/80 dark:bg-sky-950/50 dark:text-sky-200 dark:border-sky-800/60'
  }
  return 'bg-amber-100 text-amber-900 border-amber-200/80 dark:bg-amber-950/50 dark:text-amber-200 dark:border-amber-800/60'
})
const collapsedReleaseTitle = computed(
  () => `${releaseCopyLine()}\n\nانقر لنسخ السطر للدعم الفني`,
)

async function copyReleaseInfo() {
  const line = releaseCopyLine()
  try {
    await navigator.clipboard.writeText(line)
    toast.success('تم النسخ', 'يمكنك لصق معلومات الإصدار في تذكرة الدعم')
  } catch {
    toast.warning('تعذّر النسخ', line)
  }
}

const langMenuOpen = ref(false)
const langMenuRef = ref<HTMLElement | null>(null)
const apiSecurityNotice = ref('')
const loggingOut = ref(false)

/** وعد واحد فقط: يمنع عدة نقرات سريعة من إطلاق أكثر من طلب خروج قبل اكتمال الأول */
let logoutInFlight: Promise<void> | null = null

function handleLogout(): void {
  if (logoutInFlight) return
  loggingOut.value = true
  logoutInFlight = (async () => {
    try {
      await auth.logout()
      await router.replace({ name: 'login' })
    } finally {
      loggingOut.value = false
      logoutInFlight = null
    }
  })()
}

const greeting = computed(() => locale.greeting.value)

const showStaffCompactToggle = computed(
  () => auth.isStaff && !auth.isFleet && !auth.isCustomer && route.path.startsWith('/work-orders'),
)

/** تنبيه فوترة خفيف: فترة سماح / انتهاء قريب — بدون تعقيد إضافي */
const BILLING_STATE_AR: Record<string, string> = {
  active: 'نشط',
  grace: 'فترة سماح',
  expired: 'منتهٍ',
  suspended: 'موقوف',
  none: 'غير مربوط',
}

const billingStateShort = computed(() => {
  const st = auth.user?.subscription?.billing_state
  if (!st || !auth.isStaff || auth.isFleet || auth.isCustomer) return ''
  return BILLING_STATE_AR[st] ?? st
})

const billingBadgeTitle = computed(() => {
  const s = auth.user?.subscription
  if (!s) return 'الاشتراك والفوترة'
  const parts = [s.plan ? `باقة: ${s.plan}` : '', s.ends_at ? `ينتهي: ${new Date(s.ends_at).toLocaleDateString('ar-SA')}` : '']
  return parts.filter(Boolean).join(' · ') || 'الاشتراك والفوترة'
})

const billingNotice = computed((): { text: string; boxClass: string; showPlanLink: boolean } | null => {
  if (!auth.isStaff || auth.isFleet || auth.isCustomer) return null
  const s = auth.user?.subscription
  if (!s) return null

  if (s.billing_state === 'grace') {
    return {
      text: 'الاشتراك في فترة سماح: قد يُسمح بالقراءة فقط للتعديلات حتى التجديد.',
      boxClass: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/35 dark:text-amber-100',
      showPlanLink: true,
    }
  }
  if (s.billing_state === 'suspended' || s.billing_state === 'expired' || s.billing_state === 'none') {
    return {
      text: 'حالة الاشتراك تحتاج تجديدًا أو تفعيلًا — بعض العمليات قد تتوقف.',
      boxClass: 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100',
      showPlanLink: true,
    }
  }
  if (s.billing_state === 'active' && s.ends_at) {
    const end = new Date(s.ends_at).getTime()
    const days = Math.ceil((end - Date.now()) / 86400000)
    if (days >= 0 && days <= 14) {
      return {
        text: `ينتهي اشتراكك خلال ${days} يومًا — خطّط للتجديد لتفادي انقطاع الخدمة.`,
        boxClass: 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/35 dark:text-sky-100',
        showPlanLink: true,
      }
    }
  }
  return null
})

const collapsed  = ref(localStorage.getItem('sidebar_collapsed') === 'true')
const mobileOpen = ref(false)
const navQuickFilter = ref('')
const openNavSection = ref((() => {
  const raw = localStorage.getItem('open_nav_section') || 'operations'
  if (raw === 'finance' || raw === 'accounting') return 'finance_accounting'
  return raw
})())
const openNavGroups = ref<Record<string, boolean>>(
  (() => {
    try {
      return JSON.parse(localStorage.getItem('open_nav_groups') || '{}')
    } catch {
      return {}
    }
  })(),
)

function sectionEnabled(key: string): boolean {
  if (auth.isOwner) return true
  return biz.isEnabled(key)
}

/** بيارات / حجوزات / اجتماعات / خريطة حرارية — فقط عند تفعيل بوابة «operations» في ملف النشاط */
const opsNavOpen = computed(() =>
  canAccessStaffOperationsArea(auth.isOwner, (k) => biz.isEnabled(k)),
)

watch(collapsed, v => localStorage.setItem('sidebar_collapsed', String(v)))
watch(openNavSection, (v) => localStorage.setItem('open_nav_section', v))
watch(openNavGroups, (v) => localStorage.setItem('open_nav_groups', JSON.stringify(v)), { deep: true })
watch(
  () => route.path,
  () => {
    mobileOpen.value = false
    const p = route.path

    if (p.startsWith('/workshop') || p.startsWith('/hr')) {
      openNavSection.value = 'hr'
    } else if (
      p.startsWith('/invoices')
      || p.startsWith('/wallet')
      || p.startsWith('/purchases')
      || p.startsWith('/goods-receipts')
      || p.startsWith('/ledger')
      || p.startsWith('/chart-of-accounts')
      || p.startsWith('/zatca')
      || p.startsWith('/fixed-assets')
      || p.startsWith('/compliance')
      || p.startsWith('/financial-reconciliation')
    ) {
      openNavSection.value = 'finance_accounting'
    } else if (p.startsWith('/crm/')) {
      // نفس الرابط تحت «تشغيلي» و«المالية»؛ نفتح المالية فقط إن وُجدت فيها عناصر CRM (وحدة المالية مفعّلة).
      openNavSection.value = sectionEnabled('finance') ? 'finance_accounting' : 'operations'
    } else if (p.startsWith('/suppliers')) {
      openNavSection.value = sectionEnabled('finance') ? 'finance_accounting' : 'inventory'
    } else if (p.startsWith('/products') || p.startsWith('/inventory')) {
      openNavSection.value = 'inventory'
    } else if (
      p.startsWith('/reports')
      || p.startsWith('/business-intelligence')
      || p.startsWith('/governance')
      || p.startsWith('/internal/intelligence')
    ) {
      openNavSection.value = 'analytics'
    } else if (
      p.startsWith('/settings')
      || p.startsWith('/branches')
      || p.startsWith('/documents')
      || p.startsWith('/support')
      || p.startsWith('/activity')
      || p.startsWith('/contracts')
      || p.startsWith('/referrals')
    ) {
      openNavSection.value = 'admin'
    } else if (p.startsWith('/admin')) {
      openNavSection.value = 'platform'
    } else if (p.startsWith('/meetings')) {
      openNavSection.value = 'operations'
    } else if (p.startsWith('/subscription') || p.startsWith('/plans') || p.startsWith('/plugins')) {
      openNavSection.value = 'subscription'
    } else {
      openNavSection.value = 'operations'
    }

    // توسيع المجموعة الفرعية التي تحتوي الصفحة الحالية (أسرع من البحث يدوياً داخل القسم)
    if (p.startsWith('/purchases') || p.startsWith('/goods-receipts') || (p.startsWith('/suppliers') && sectionEnabled('finance'))) {
      openNavGroups.value = { ...openNavGroups.value, purchases: true }
    }
    if (
      p.startsWith('/ledger')
      || p.startsWith('/chart-of-accounts')
      || p.startsWith('/zatca')
      || p.startsWith('/fixed-assets')
    ) {
      openNavGroups.value = { ...openNavGroups.value, accountant: true }
    }
  },
  { immediate: true },
)

const navQuickFilterTrimmed = computed(() => normNavSearch(navQuickFilter.value))
const filteredNavQuick = computed(() => {
  void locale.lang.value
  void biz.loaded
  void biz.effectiveFeatureMatrix
  const q = navQuickFilterTrimmed.value
  if (!q) return []
  const labelFor = (to: string, fallback: string) => {
    if (to === '/settings/team-users') return locale.t('teamUsers.nav')
    if (to === '/settings/org-units') return locale.t('orgUnits.nav')
    return fallback
  }
  return NAV_SEARCH_ITEMS.filter((item) => {
    if (!navSearchItemVisibleForPortals(item, enabledPortals)) return false
    if (item.requiresManager && !auth.isManager) return false
    if (item.requiresStaff && !auth.isStaff) return false
    if (item.requiresPlatform && !auth.isPlatform) return false
    if (item.requiresOwner && !auth.isOwner) return false
    if (item.requiresIntelligence && !featureFlags.intelligenceCommandCenter) return false
    if (item.to === '/business-intelligence') {
      if (
        !canAccessStaffBusinessIntelligence({
          buildFlagOn: featureFlags.intelligenceCommandCenter,
          isOwner: auth.isOwner,
          isEnabled: (k) => biz.isEnabled(k),
        })
      ) {
        return false
      }
    }
    if (item.to === '/internal/intelligence') {
      if (
        !canAccessStaffCommandCenter({
          buildFlagOn: featureFlags.intelligenceCommandCenter,
          isOwner: auth.isOwner,
          isEnabled: (k) => biz.isEnabled(k),
          hasIntelligenceReportPermission: auth.hasPermission('reports.intelligence.view'),
        })
      ) {
        return false
      }
    }
    if (item.to === '/settings/org-units' && !tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'org_structure')) {
      return false
    }
    if (item.to.startsWith('/crm/') && !tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'crm')) {
      return false
    }
    if (
      item.to.startsWith('/fleet/')
      && (!enabledPortals.fleet || !tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'fleet'))
    ) {
      return false
    }
    if (
      (item.to.startsWith('/bays') || item.to.startsWith('/bookings') || item.to.startsWith('/meetings')) &&
      !tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'operations')
    ) {
      return false
    }
    if (item.to.startsWith('/workshop') && !canAccessWorkshopArea(auth.isOwner, (k) => biz.isEnabled(k))) return false
    if (item.requiresPermission && !auth.hasPermission(item.requiresPermission)) return false
    if (item.requiresAnyPermission?.length) {
      const ok = item.requiresAnyPermission.some((p) => auth.hasPermission(p))
      if (!ok) return false
    }
    return itemMatchesNavQuery(item, q)
  })
    .slice(0, 14)
    .map((item) => ({ ...item, label: labelFor(item.to, item.label) }))
})

function handleClickOutside(e: MouseEvent) {
  if (langMenuRef.value && !langMenuRef.value.contains(e.target as Node)) {
    langMenuOpen.value = false
  }
}
onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  biz.load().catch(() => {})
  loadApiSecurityNotice().catch(() => {})
})
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))

watch(() => route.fullPath, () => {
  if (!auth.hasPermission('api_keys.manage')) return
  loadApiSecurityNotice().catch(() => {})
})

async function loadApiSecurityNotice() {
  if (!auth.hasPermission('api_keys.manage')) {
    apiSecurityNotice.value = ''
    return
  }
  const [{ data: keysRes }, { data: logsRes }] = await Promise.all([
    apiClient.get('/api-keys', { skipGlobalErrorToast: true }),
    apiClient.get('/api-usage-logs', { params: { per_page: 50 }, skipGlobalErrorToast: true }),
  ])
  const keys = keysRes?.data?.data ?? keysRes?.data ?? []
  const logs = logsRes?.data?.data ?? logsRes?.data ?? []

  const now = Date.now()
  const expSoon = Array.isArray(keys) && keys.some((k: any) => {
    if (!k?.expires_at) return false
    const diffDays = Math.ceil((new Date(k.expires_at).getTime() - now) / 86400000)
    return diffDays >= 0 && diffDays <= 7
  })
  const errors = Array.isArray(logs) ? logs.filter((l: any) => Number(l?.status_code) >= 400).length : 0
  const slow = Array.isArray(logs) ? logs.filter((l: any) => Number(l?.duration_ms) >= 1500).length : 0

  if (errors >= 10) {
    apiSecurityNotice.value = `تحذير API: ${errors} طلبات فاشلة ضمن آخر 50 سجل.`
    return
  }
  if (expSoon) {
    apiSecurityNotice.value = 'تحذير API: توجد مفاتيح تنتهي خلال 7 أيام.'
    return
  }
  if (slow >= 8) {
    apiSecurityNotice.value = `تنبيه API: ${slow} طلبات بطيئة (أكثر من 1500ms).`
    return
  }
  apiSecurityNotice.value = ''
}

function openPalette() {
  window.dispatchEvent(new KeyboardEvent('keydown', { ctrlKey: true, key: 'k', bubbles: true }))
}

const prefetchedRoutes = new Set<string>()
function prefetchRoute(path?: string) {
  const target = String(path || '').trim()
  if (!target || prefetchedRoutes.has(target)) return
  const resolved = router.resolve(target)
  const records = resolved.matched ?? []
  for (const record of records) {
    const maybeComponent = (record.components as any)?.default
    if (typeof maybeComponent === 'function') {
      Promise.resolve(maybeComponent()).catch(() => {})
    }
  }
  prefetchedRoutes.add(target)
}

const flatItems = computed(() => {
  void biz.loaded
  void biz.effectiveFeatureMatrix
  const opsOpen = canAccessStaffOperationsArea(auth.isOwner, (k) => biz.isEnabled(k))
  const hrOpen = canAccessWorkshopArea(auth.isOwner, (k) => biz.isEnabled(k))
  const items: { to: string; icon: object; label: string; locked: boolean }[] = [
    { to: '/',                     icon: HomeIcon,                label: 'الرئيسية',          locked: false },
    { to: '/pos',                  icon: ShoppingCartIcon,        label: 'نقطة البيع',         locked: false },
    { to: '/invoices',             icon: DocumentTextIcon,        label: 'الفواتير',           locked: false },
    ...(auth.hasPermission('reports.financial.view')
      ? [{ to: '/financial-reconciliation', icon: ArrowsRightLeftIcon, label: 'مطابقة مالية', locked: false }]
      : []),
    { to: '/work-orders',          icon: ClipboardDocumentIcon,   label: 'أوامر العمل',        locked: false },
    ...(opsOpen ? [{ to: '/bays', icon: BuildingOfficeIcon, label: 'مناطق العمل', locked: false }] : []),
    ...(opsOpen ? [{ to: '/bookings', icon: CalendarDaysIcon, label: 'الحجوزات', locked: false }] : []),
    ...(opsOpen && auth.hasPermission('meetings.update')
      ? [{ to: '/meetings', icon: CalendarIcon, label: 'الاجتماعات', locked: false }]
      : []),
    ...(auth.isManager ? [{ to: '/branches', icon: BuildingLibraryIcon, label: 'الفروع', locked: false }] : []),
    ...(auth.isStaff ? [{ to: '/branches/map', icon: MapPinIcon, label: 'خريطة الفروع', locked: false }] : []),
    { to: '/customers',            icon: UsersIcon,               label: 'العملاء',            locked: false },
    ...(hrOpen ? [{ to: '/workshop/employees', icon: UserGroupIcon, label: 'الموظفون', locked: false }] : []),
    { to: '/vehicles',             icon: TruckIcon,               label: 'المركبات',           locked: false },
    { to: '/wallet',               icon: CreditCardIcon,          label: 'المحفظة',            locked: false },
    ...(auth.hasPermission('wallet.top_up_requests.create')
      || auth.hasPermission('wallet.top_up_requests.view')
      || auth.hasPermission('wallet.top_up_requests.review')
      ? [{ to: '/wallet/top-up-requests', icon: QueueListIcon, label: 'طلبات شحن الرصيد', locked: false }]
      : []),
    { to: '/products',             icon: CubeIcon,                label: 'المنتجات',           locked: false },
    ...(canAccessStaffBusinessIntelligence({
      buildFlagOn: featureFlags.intelligenceCommandCenter,
      isOwner: auth.isOwner,
      isEnabled: (k) => biz.isEnabled(k),
    })
      ? [{ to: '/business-intelligence', icon: PresentationChartLineIcon, label: 'ذكاء الأعمال', locked: false }]
      : []),
    { to: '/reports',              icon: ChartBarIcon,            label: 'التقارير',           locked: false },
    { to: '/settings',             icon: Cog6ToothIcon,           label: 'الإعدادات',          locked: false },
    ...(canAccessStaffCommandCenter({
      buildFlagOn: featureFlags.intelligenceCommandCenter,
      isOwner: auth.isOwner,
      isEnabled: (k) => biz.isEnabled(k),
      hasIntelligenceReportPermission: auth.hasPermission('reports.intelligence.view'),
    })
      ? [{ to: '/internal/intelligence', icon: SignalIcon, label: 'مركز العمليات', locked: false }]
      : []),
  ]
  if (auth.isPlatform && enabledPortals.admin) {
    items.push({ to: '/admin/qa', icon: BeakerIcon, label: 'التحقق من النظام', locked: false })
  }
  return items
})

const NavIconItem = defineComponent({
  props: { to: String, icon: Object, label: String, locked: Boolean },
  setup(props) {
    return () => {
      if (props.locked) {
        return h('div', {
          class: 'flex justify-center p-2 rounded-xl text-gray-300 cursor-not-allowed',
          title: `${props.label} — يتطلب ترقية`,
        }, [h(props.icon as any, { class: 'w-5 h-5' })])
      }
      return h(RouterLink as any, {
        to: props.to,
        class: 'flex justify-center p-2 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-primary-50 dark:hover:bg-primary-950/30 hover:text-primary-700 dark:hover:text-primary-300 transition-colors',
        activeClass: 'bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 ring-1 ring-primary-200/70 dark:ring-primary-800/60',
        title: props.label,
        'data-smart-tip': props.label,
        onMouseenter: () => prefetchRoute(props.to),
        onFocus: () => prefetchRoute(props.to),
      }, () => h(props.icon as any, { class: 'w-5 h-5' }))
    }
  },
})

const NavItem = defineComponent({
  props: { to: String, icon: Object, label: String, exact: Boolean, locked: Boolean },
  setup(props) {
    return () => {
      if (props.locked) {
        return h('div', {
          class: 'flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-medium text-gray-300 cursor-not-allowed select-none',
          title: 'يتطلب ترقية الباقة',
        }, [
          h(props.icon as any, { class: 'w-4 h-4 flex-shrink-0' }),
          h('span', { class: 'flex-1' }, props.label),
          h(LockClosedIcon, { class: 'w-3 h-3' }),
        ])
      }
      return h(RouterLink as any, {
        to: props.to,
        class: 'flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-primary-50 dark:hover:bg-primary-950/30 hover:text-primary-700 dark:hover:text-primary-300 transition-colors',
        activeClass: 'bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 ring-1 ring-primary-200/70 dark:ring-primary-800/60',
        exactActiveClass: props.exact ? 'bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 ring-1 ring-primary-200/70 dark:ring-primary-800/60' : undefined,
        'data-smart-tip': props.label,
        onMouseenter: () => prefetchRoute(props.to),
        onFocus: () => prefetchRoute(props.to),
      }, () => [
        h(props.icon as any, { class: 'w-4 h-4 flex-shrink-0' }),
        h('span', {}, props.label),
      ])
    }
  },
})

const NavSection = defineComponent({
  props: { label: String, sectionKey: String },
  setup(props, { slots }) {
    const isOpen = computed(() => openNavSection.value === String(props.sectionKey))
    const toggle = () => {
      const key = String(props.sectionKey)
      openNavSection.value = openNavSection.value === key ? '' : key
    }
    return () => h('section', {
      class: 'rounded-2xl border border-gray-100/90 dark:border-slate-700/80 bg-gray-50/60 dark:bg-slate-900/35 px-2 py-2.5 space-y-1',
    }, [
      h(
        'button',
        {
          type: 'button',
          class: 'w-full px-2 mb-1.5 flex items-center gap-2 text-right hover:bg-white/70 dark:hover:bg-slate-800/50 rounded-lg py-1 transition-colors',
          onClick: toggle,
        },
        [
          h('span', { class: 'inline-flex h-1.5 w-1.5 rounded-full bg-primary-500/80' }),
          h('p', { class: 'flex-1 text-[12px] font-bold text-gray-600 dark:text-slate-300 tracking-wide' }, props.label),
          h(ChevronDownIcon, { class: `w-3.5 h-3.5 text-gray-400 transition-transform duration-150 ease-out ${isOpen.value ? 'rotate-180' : ''}` }),
        ],
      ),
      ...(isOpen.value ? (slots.default?.() ?? []) : []),
    ])
  },
})

const NavSubGroup = defineComponent({
  props: { label: String, groupKey: String },
  setup(props, { slots }) {
    const isOpen = computed(() => !!openNavGroups.value[String(props.groupKey)])
    const toggle = () => {
      const key = String(props.groupKey)
      openNavGroups.value = { ...openNavGroups.value, [key]: !openNavGroups.value[key] }
    }
    return () => h('div', { class: 'rounded-xl border border-gray-100/90 dark:border-slate-700/80 px-2 py-1.5 bg-white/70 dark:bg-slate-800/30' }, [
      h(
        'button',
        {
          type: 'button',
          class: 'w-full flex items-center gap-2 text-right text-xs font-bold text-gray-600 dark:text-slate-300 px-1.5 py-1 rounded hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors',
          onClick: toggle,
        },
        [
          h('span', { class: 'flex-1' }, props.label),
          h('span', { class: 'text-primary-600 dark:text-primary-400 text-base leading-none' }, isOpen.value ? '−' : '+'),
        ],
      ),
      ...(isOpen.value ? (slots.default?.() ?? []) : []),
    ])
  },
})

const pageTitles: Record<string, string> = {
  dashboard: 'الرئيسية', pos: 'نقطة البيع', customers: 'العملاء',
  vehicles: 'المركبات', 'vehicles.show': 'تفاصيل المركبة',
  'work-orders': 'أوامر العمل', 'work-orders.show': 'تفاصيل أمر العمل',
  'work-orders.create': 'أمر عمل جديد', invoices: 'الفواتير',
  'financial-reconciliation': 'المطابقة المالية',
  meetings: 'الاجتماعات',
  'invoices.show': 'تفاصيل الفاتورة', products: 'المنتجات',
  'products.create': 'منتج جديد', inventory: 'المخزون',
  'inventory.units': 'وحدات القياس', 'inventory.reservations': 'الحجوزات',
  suppliers: 'الموردون', purchases: 'المشتريات',
  'business-intelligence': 'ذكاء الأعمال', reports: 'التقارير',
  referrals: 'الإحالات والولاء',
  ledger: 'دفتر الأستاذ العام', 'ledger.show': 'تفاصيل القيد',
  'chart-of-accounts': 'دليل الحسابات', 'fixed-assets': 'الأصول الثابتة', wallet: 'المحفظة',
  'wallet.top-up-requests': 'طلبات شحن الرصيد',
  'wallet-transactions': 'معاملات المحفظة',
  'fleet.wallet': 'محافظ الأسطول', 'fleet.verify-plate': 'التحقق من اللوحة',
  'fleet.transactions': 'سجل المعاملات', governance: 'الحوكمة والسياسات',
  'workshop.employees': 'الموظفون', 'workshop.tasks': 'إدارة المهام',
  'workshop.attendance': 'الحضور والانصراف', 'workshop.salaries': 'مسير الرواتب',
  'workshop.leaves': 'الإجازات', 'workshop.commissions': 'العمولات',
  bays: 'مناطق العمل', 'bays.heatmap': 'الخريطة الحرارية',
  bookings: 'الحجوزات', plans: 'باقات الاشتراك', subscription: 'اشتراكي',
  settings: 'الإعدادات', 'settings.team-users': 'حسابات الفريق', 'settings.org-units': 'هيكل القطاعات', 'settings.integrations': 'التكاملات', 'settings.api-keys': 'مفاتيح API', activity: 'سجل العمليات',
  'internal.intelligence': 'مركز العمليات الذكي',
  admin: 'لوحة الأدمن', AdminQA: 'التحقق من النظام (QA)',
  'workshop.hr-comms': 'الاتصالات الإدارية',
  'workshop.commission-policies': 'سياسات العمولات',
  'crm.relations': 'علاقات العملاء',
  'documents.company': 'مستندات المنشأة',
  'electronic-archive': 'الأرشفة الإلكترونية',
  'workshop.hr-archive': 'الأرشفة الإلكترونية',
  branches: 'إدارة الفروع',
  'branches.map': 'خريطة الفروع',
  'about.deployment': 'بيانات النشر',
}

const pageTitle = computed(() => {
  void locale.lang.value
  const name = route.name as string
  if (name === 'settings.team-users') return locale.t('teamUsers.title')
  if (name === 'settings.org-units') return locale.t('orgUnits.title')
  if (name === 'access-denied') return locale.t('pages.accessDenied')
  if (name === 'about.capabilities') return locale.t('pages.capabilities')
  if (name === 'about.taxonomy') return locale.t('pages.taxonomy')
  return pageTitles[name] ?? locale.t('app.name')
})

const breadcrumbMap: Record<string, { label: string; parent?: string }> = {
  admin:                  { label: 'لوحة الأدمن', parent: 'dashboard' },
  dashboard:              { label: 'الرئيسية' },
  pos:                    { label: 'نقطة البيع', parent: 'dashboard' },
  invoices:               { label: 'الفواتير', parent: 'dashboard' },
  'financial-reconciliation': { label: 'المطابقة المالية', parent: 'dashboard' },
  meetings:               { label: 'الاجتماعات', parent: 'dashboard' },
  'invoices.show':        { label: 'تفاصيل الفاتورة', parent: 'invoices' },
  'work-orders':          { label: 'أوامر العمل', parent: 'dashboard' },
  'work-orders.show':     { label: 'تفاصيل أمر العمل', parent: 'work-orders' },
  products:               { label: 'المنتجات', parent: 'dashboard' },
  'products.create':      { label: 'منتج جديد', parent: 'products' },
  customers:              { label: 'العملاء', parent: 'dashboard' },
  'vehicles.show':        { label: 'تفاصيل المركبة', parent: 'vehicles' },
  vehicles:               { label: 'المركبات', parent: 'customers' },
  settings:               { label: 'الإعدادات', parent: 'dashboard' },
  'settings.team-users':  { label: 'حسابات الفريق', parent: 'settings' },
  'settings.org-units':   { label: 'هيكل القطاعات', parent: 'settings' },
  'settings.integrations':{ label: 'التكاملات', parent: 'settings' },
  'settings.api-keys':    { label: 'مفاتيح API', parent: 'settings' },
  bays:                   { label: 'مناطق العمل', parent: 'dashboard' },
  'bays.heatmap':         { label: 'الخريطة الحرارية', parent: 'bays' },
  bookings:               { label: 'الحجوزات', parent: 'bays' },
  'business-intelligence': { label: 'ذكاء الأعمال', parent: 'dashboard' },
  AdminQA:                { label: 'التحقق من النظام', parent: 'admin' },
  reports:                { label: 'التقارير', parent: 'dashboard' },
  ledger:                 { label: 'دفتر الأستاذ', parent: 'dashboard' },
  'fixed-assets':         { label: 'الأصول الثابتة', parent: 'dashboard' },
  'internal.intelligence': { label: 'مركز العمليات الذكي', parent: 'dashboard' },
  'about.capabilities':      { label: 'قدرات النظام', parent: 'dashboard' },
  'about.taxonomy':          { label: 'مسرد المنصة', parent: 'dashboard' },
  'access-denied':           { label: 'لا صلاحية', parent: 'dashboard' },
  wallet:                    { label: 'المحفظة', parent: 'dashboard' },
  'wallet.top-up-requests':  { label: 'طلبات شحن الرصيد', parent: 'wallet' },
}

const routePaths: Record<string, string> = {
  dashboard: '/', admin: '/admin', AdminQA: '/admin/qa', pos: '/pos', invoices: '/invoices',
  'financial-reconciliation': '/financial-reconciliation',
  meetings: '/meetings',
  'work-orders': '/work-orders',
  products: '/products', customers: '/customers', vehicles: '/vehicles',
  settings: '/settings', 'settings.team-users': '/settings/team-users', 'settings.org-units': '/settings/org-units', 'settings.integrations': '/settings/integrations',
  'settings.api-keys': '/settings/api-keys',
  bays: '/bays', 'bays.heatmap': '/bays/heatmap', bookings: '/bookings',
  'business-intelligence': '/business-intelligence',
  reports: '/reports', ledger: '/ledger',
  'fixed-assets': '/fixed-assets',
  'internal.intelligence': '/internal/intelligence',
  branches: '/branches',
  'branches.map': '/branches/map',
  'about.capabilities': '/about/capabilities',
  'about.taxonomy': '/about/taxonomy',
  'access-denied': '/access-denied',
  wallet: '/wallet',
  'wallet.top-up-requests': '/wallet/top-up-requests',
}

const breadcrumbs = computed(() => {
  void locale.lang.value
  const current = route.name as string
  const chain: { label: string; path: string }[] = []
  let key: string | undefined = current
  const labelFor = (routeKey: string, fallback: string) => {
    if (routeKey === 'settings.team-users') return locale.t('teamUsers.nav')
    if (routeKey === 'settings.org-units') return locale.t('orgUnits.nav')
    if (routeKey === 'about.capabilities') return locale.t('pages.capabilities')
    if (routeKey === 'about.taxonomy') return locale.t('pages.taxonomy')
    if (routeKey === 'access-denied') return locale.t('pages.accessDenied')
    return fallback
  }
  while (key) {
    const entry: { label: string; parent?: string } | undefined = breadcrumbMap[key]
    if (!entry) break
    chain.unshift({ label: labelFor(key, entry.label), path: routePaths[key] ?? '/' })
    key = entry.parent
  }
  return chain
})

/** مستوى أعلى في مسار الفتات للرجوع */
const breadcrumbParentPath = computed(() => {
  const b = breadcrumbs.value
  if (b.length >= 2) return b[b.length - 2]!.path
  return '/'
})
</script>

<style scoped>
.overlay-fade-enter-active, .overlay-fade-leave-active { transition: opacity 0.2s; }
.overlay-fade-enter-from, .overlay-fade-leave-to { opacity: 0; }
</style>
