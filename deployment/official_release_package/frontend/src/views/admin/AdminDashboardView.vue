<template>
  <div class="flex min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100" dir="rtl">
    <PlatformSidebarNav
      :active="activeSection"
      :items="navItems"
      @jump="jumpToSection"
      @open-ticket="openTicket"
      @open-help="openHelp"
    />

    <div class="min-w-0 flex-1">
      <PlatformTopbar
        v-model:search="globalSearch"
        @refresh="refresh"
        @go-back="goBack"
        @go-home="goHome"
        @open-notifications="openNotifications"
        @open-help="openHelp"
      />

      <main class="mx-auto max-w-[1500px] space-y-5 p-5">
        <div v-if="!auth.isPlatform" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          هذا المسار مخصص لإدارة المنصة فقط.
        </div>

        <template v-else-if="loading">
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="n in 6" :key="n" class="h-28 animate-pulse rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" />
          </div>
          <div class="h-48 animate-pulse rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" />
        </template>

        <template v-else-if="error">
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
            {{ error }}
          </div>
        </template>

        <template v-else>
          <section v-if="globalSearch.trim()" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-2 flex items-center justify-between">
              <h3 class="text-sm font-bold">نتائج البحث الشامل</h3>
              <span class="text-xs text-slate-500">العبارة: {{ globalSearch }}</span>
            </div>
            <p v-if="globalSearchLoading" class="mb-2 text-xs text-slate-500">جارٍ البحث...</p>
            <p v-else-if="globalSearchError" class="mb-2 text-xs text-red-600 dark:text-red-300">{{ globalSearchError }}</p>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <p class="mb-1 text-xs font-bold text-slate-700 dark:text-slate-200">الشركات</p>
                <div class="space-y-1">
                  <button
                    v-for="row in quickSearchCompanies"
                    :key="row.id"
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                    @click="openCompany(row.id)"
                  >
                    <span>{{ row.name }}</span>
                    <span class="text-slate-500">{{ row.plan_name || row.plan_slug || '—' }}</span>
                  </button>
                  <p v-if="quickSearchCompanies.length === 0" class="text-xs text-slate-500">لا نتائج شركات مطابقة.</p>
                </div>
              </div>
              <div>
                <p class="mb-1 text-xs font-bold text-slate-700 dark:text-slate-200">وصول سريع</p>
                <p class="mb-2 text-[11px] text-slate-500">
                  مستخدمون: {{ globalSearchResults.users.length }} ·
                  عملاء: {{ globalSearchResults.customers.length }} ·
                  فواتير: {{ globalSearchResults.invoices.length }} ·
                  أوامر عمل: {{ globalSearchResults.work_orders.length }}
                </p>
                <div class="space-y-1">
                  <button type="button" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-right text-xs hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" :disabled="quickSearchCompanies.length === 0" @click="openCompanyScopedGlobal('/customers')">فتح العملاء بالسياق</button>
                  <button type="button" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-right text-xs hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" :disabled="quickSearchCompanies.length === 0" @click="openCompanyScopedGlobal('/vehicles')">فتح المركبات بالسياق</button>
                  <button type="button" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-right text-xs hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" :disabled="quickSearchCompanies.length === 0" @click="openCompanyScopedGlobal('/work-orders')">فتح أوامر العمل بالسياق</button>
                  <button type="button" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-right text-xs hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" :disabled="quickSearchCompanies.length === 0" @click="openCompanyScopedGlobal('/invoices')">فتح الفواتير بالسياق</button>
                </div>
              </div>
            </div>
          </section>

          <section id="overview" class="space-y-4">
            <PlatformStatusOverview :cards="statusCards" />
          </section>

          <section id="intervention" class="space-y-4">
            <PlatformInterventionCards :items="interventionItems" @action="handleInterventionAction" />
          </section>

          <section id="intelligence" class="space-y-4">
            <PlatformIntelligencePanel :recommendations="intelligenceRecommendations" @action="handleIntelligenceAction" />
          </section>

          <section id="analytics" class="grid gap-4 xl:grid-cols-2">
            <PlatformRevenueChart :points="revenuePoints" :empty="revenuePoints.length === 0" />
            <PlatformPlanDistribution :rows="planRows" :empty="planRows.length === 0" />
          </section>

          <section id="companies" class="space-y-3">
            <div class="grid gap-2 md:grid-cols-4">
              <input v-model="filters.search" type="search" placeholder="بحث بالشركة" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
              <select v-model="filters.plan" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">كل الباقات</option>
                <option v-for="p in uniquePlans" :key="p" :value="p">{{ p }}</option>
              </select>
              <select v-model="filters.subscription" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">كل حالات الاشتراك</option>
                <option value="active">نشط</option>
                <option value="trial">تجريبي</option>
                <option value="suspended">موقوف</option>
              </select>
              <select v-model="filters.health" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">كل الصحة</option>
                <option value="excellent">ممتاز</option>
                <option value="good">جيد</option>
                <option value="risk">خطر</option>
              </select>
            </div>
            <PlatformCompaniesTable :rows="companyRows" @open="openCompany" @manage="manageCompany" @add-company="addCompany" @export="exportCompanies" />
          </section>

          <section id="controls" class="space-y-3">
            <PlatformControlModules :modules="controlModules" @open="openControlModule" />
          </section>

          <section id="support">
            <PlatformQuickSupportCard @open-ticket="openTicket" @open-help="openHelp" @contact-ops="contactOps" />
          </section>
        </template>
      </main>
    </div>

    <div
      v-if="companyModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="closeCompanyModal"
    >
      <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-slate-100">إدارة الشركة</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">تفاصيل الشركة وإجراءات تشغيلية مباشرة</p>
          </div>
          <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="closeCompanyModal">إغلاق</button>
        </div>

        <div v-if="companyModalLoading" class="space-y-2">
          <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
          <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
        </div>
        <div v-else-if="companyModalError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
          {{ companyModalError }}
        </div>
        <div v-else-if="companyModalData" class="space-y-3 text-sm">
          <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
              <p class="text-xs text-slate-500 dark:text-slate-400">اسم الشركة</p>
              <p class="font-bold">{{ companyModalData.company.name }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
              <p class="text-xs text-slate-500 dark:text-slate-400">حالة التشغيل</p>
              <p class="font-bold">{{ formatCompanyOperationalStatus(companyModalData.company.company_status) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
              <p class="text-xs text-slate-500 dark:text-slate-400">الباقة</p>
              <p class="font-bold">{{ formatPlanLabel(companyModalData.subscription?.plan) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
              <p class="text-xs text-slate-500 dark:text-slate-400">حالة الاشتراك</p>
              <p class="font-bold">{{ formatSubscriptionStatus(companyModalData.subscription?.status) }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white"
              :disabled="companyActionLoading || !canPlatform('platform.companies.operational')"
              @click="updateCompanyOperational(true, 'active')"
            >
              تفعيل الشركة
            </button>
            <button
              type="button"
              class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white"
              :disabled="companyActionLoading || !canPlatform('platform.companies.operational')"
              @click="updateCompanyOperational(true, 'suspended')"
            >
              تعليق الشركة
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs dark:border-slate-700"
              :disabled="companyActionLoading || !canPlatform('platform.companies.operational')"
              @click="updateCompanyOperational(false, 'inactive')"
            >
              إيقاف التشغيل
            </button>
            <button
              type="button"
              class="rounded-lg border border-violet-300 px-3 py-1.5 text-xs text-violet-700 dark:border-violet-700 dark:text-violet-300"
              @click="openCompanyWorkspace"
            >
              فتح تشغيل الشركة
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs dark:border-slate-700"
              @click="openCompanyProfile"
            >
              ملف الشركة
            </button>
          </div>

          <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <p class="mb-2 text-xs font-bold text-slate-700 dark:text-slate-200">التنقل بنفس سياق الشركة</p>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs dark:border-slate-700" @click="openCompanyScoped('/customers')">العملاء</button>
              <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs dark:border-slate-700" @click="openCompanyScoped('/vehicles')">المركبات</button>
              <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs dark:border-slate-700" @click="openCompanyScoped('/work-orders')">أوامر العمل</button>
              <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs dark:border-slate-700" @click="openCompanyScoped('/invoices')">الفواتير</button>
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <p class="mb-2 text-xs font-bold text-slate-700 dark:text-slate-200">إدارة الاشتراك والباقات</p>
            <div class="flex flex-wrap items-center gap-2">
              <select v-model="companyPlanSlug" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-900">
                <option value="">اختر باقة</option>
                <option v-for="p in planCatalog" :key="p.slug" :value="p.slug">{{ p.name_ar || p.name || p.slug }}</option>
              </select>
              <button
                type="button"
                class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white"
                :disabled="companyActionLoading || !companyPlanSlug || !canPlatform('platform.subscription.manage')"
                @click="updateCompanySubscription"
              >
                تحديث الباقة
              </button>
            </div>
          </div>

          <div v-if="companyEntitySnapshot" class="mt-2 grid gap-3">
            <div class="grid gap-2 sm:grid-cols-5">
              <div class="rounded-lg border border-slate-200 p-2 text-center dark:border-slate-700">
                <p class="text-[10px] text-slate-500">المستخدمون</p>
                <p class="font-black">{{ Number(companyEntitySnapshot.counts?.users ?? 0).toLocaleString('ar-SA') }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 p-2 text-center dark:border-slate-700">
                <p class="text-[10px] text-slate-500">العملاء</p>
                <p class="font-black">{{ Number(companyEntitySnapshot.counts?.customers ?? 0).toLocaleString('ar-SA') }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 p-2 text-center dark:border-slate-700">
                <p class="text-[10px] text-slate-500">المركبات</p>
                <p class="font-black">{{ Number(companyEntitySnapshot.counts?.vehicles ?? 0).toLocaleString('ar-SA') }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 p-2 text-center dark:border-slate-700">
                <p class="text-[10px] text-slate-500">الفواتير</p>
                <p class="font-black">{{ Number(companyEntitySnapshot.counts?.invoices ?? 0).toLocaleString('ar-SA') }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 p-2 text-center dark:border-slate-700">
                <p class="text-[10px] text-slate-500">أوامر العمل</p>
                <p class="font-black">{{ Number(companyEntitySnapshot.counts?.work_orders ?? 0).toLocaleString('ar-SA') }}</p>
              </div>
            </div>

            <details class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
              <summary class="cursor-pointer text-xs font-bold">المستخدمون (آخر عناصر)</summary>
              <ul class="mt-2 space-y-1 text-xs">
                <li v-for="u in companyEntitySnapshot.users || []" :key="'u-' + u.id" class="flex justify-between gap-2">
                  <span>{{ u.name }}</span>
                  <span class="text-slate-500">{{ u.email || '—' }}</span>
                </li>
              </ul>
            </details>

            <details class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
              <summary class="cursor-pointer text-xs font-bold">العملاء والمركبات (آخر عناصر)</summary>
              <ul class="mt-2 space-y-1 text-xs">
                <li v-for="c in companyEntitySnapshot.customers || []" :key="'c-' + c.id" class="flex justify-between gap-2">
                  <span>{{ c.name }}</span>
                  <span class="text-slate-500">{{ c.phone || c.email || '—' }}</span>
                </li>
              </ul>
              <div class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-700">
                <ul class="space-y-1 text-xs">
                  <li v-for="v in companyEntitySnapshot.vehicles || []" :key="'v-' + v.id" class="flex justify-between gap-2">
                    <span>{{ v.plate_number || '—' }} · {{ v.make || '' }} {{ v.model || '' }}</span>
                    <span class="text-slate-500">{{ v.year || '—' }}</span>
                  </li>
                </ul>
              </div>
            </details>

            <details class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
              <summary class="cursor-pointer text-xs font-bold">الفواتير وأوامر العمل (آخر عناصر)</summary>
              <ul class="mt-2 space-y-1 text-xs">
                <li v-for="i in companyEntitySnapshot.invoices || []" :key="'i-' + i.id" class="flex justify-between gap-2">
                  <span>{{ i.invoice_number || ('#' + i.id) }}</span>
                  <span class="text-slate-500">{{ i.status || '—' }} · {{ i.total_amount || '—' }}</span>
                </li>
              </ul>
              <div class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-700">
                <ul class="space-y-1 text-xs">
                  <li v-for="w in companyEntitySnapshot.work_orders || []" :key="'w-' + w.id" class="flex justify-between gap-2">
                    <span>{{ w.order_number || ('#' + w.id) }}</span>
                    <span class="text-slate-500">{{ w.status || '—' }}</span>
                  </li>
                </ul>
              </div>
            </details>
          </div>
        </div>
      </div>
    </div>

    <div v-if="controlDrawer.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeControlDrawer">
      <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-base font-black text-slate-900 dark:text-slate-100">{{ controlDrawer.title }}</h3>
          <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="closeControlDrawer">إغلاق</button>
        </div>

        <div v-if="controlDrawer.loading" class="space-y-2">
          <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
          <div class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
        </div>

        <div v-else-if="controlDrawer.type === 'audit'" class="space-y-2 text-xs">
          <div v-for="row in auditRows" :key="row.id" class="rounded-lg border border-slate-200 p-2 dark:border-slate-700">
            <p class="font-bold">{{ row.action }}</p>
            <p class="text-slate-500">{{ row.created_at ? new Date(row.created_at).toLocaleString('ar-SA') : '—' }}</p>
          </div>
          <p v-if="auditRows.length === 0" class="text-slate-500">لا توجد سجلات حالياً.</p>
        </div>

        <div v-else-if="controlDrawer.type === 'cancellations'" class="space-y-2 text-xs">
          <div v-for="row in cancellationRows" :key="row.id" class="rounded-lg border border-slate-200 p-2 dark:border-slate-700">
            <p class="font-bold">{{ row.company?.name || '—' }} · {{ row.work_order?.order_number || '—' }}</p>
            <p class="text-slate-500">{{ row.reason || 'بدون سبب' }}</p>
            <div class="mt-2 flex gap-2">
              <button type="button" class="rounded bg-emerald-600 px-2 py-1 text-white disabled:opacity-50" :disabled="!canPlatform('platform.cancellations.manage')" @click="approveCancellation(row.id)">اعتماد</button>
              <button type="button" class="rounded border border-red-300 px-2 py-1 text-red-700 disabled:opacity-50" :disabled="!canPlatform('platform.cancellations.manage')" @click="rejectCancellation(row.id)">رفض</button>
            </div>
          </div>
          <p v-if="cancellationRows.length === 0" class="text-slate-500">لا توجد طلبات إلغاء حالياً.</p>
        </div>

        <div v-else-if="controlDrawer.type === 'announcement'" class="space-y-3 text-xs">
          <label class="flex items-center gap-2"><input v-model="announcementForm.is_enabled" type="checkbox"> تفعيل الشريط</label>
          <input v-model="announcementForm.title" type="text" placeholder="العنوان" class="w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
          <textarea v-model="announcementForm.message" rows="3" placeholder="رسالة الإعلان" class="w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
          <div class="grid gap-2 sm:grid-cols-2">
            <input v-model="announcementForm.link_text" type="text" placeholder="نص الرابط" class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <input v-model="announcementForm.link_url" type="text" placeholder="الرابط" class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
          </div>
          <div class="flex justify-end">
            <button type="button" class="rounded bg-violet-600 px-3 py-1.5 font-bold text-white disabled:opacity-50" :disabled="!canPlatform('platform.announcement.manage')" @click="saveAnnouncement">حفظ الإعدادات</button>
          </div>
        </div>

        <div v-else-if="controlDrawer.type === 'registration'" class="space-y-2 text-xs">
          <div v-for="row in registrationRows" :key="row.id" class="rounded-lg border border-slate-200 p-2 dark:border-slate-700">
            <p class="font-bold">{{ row.phone || '—' }} · {{ row.account_type || '—' }}</p>
            <p class="text-slate-500">{{ row.status || '—' }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <button type="button" class="rounded bg-emerald-600 px-2 py-1 text-white disabled:opacity-50" :disabled="!canPlatform('platform.registration.manage')" @click="approveRegistration(row.id)">اعتماد</button>
              <button type="button" class="rounded border border-red-300 px-2 py-1 text-red-700 disabled:opacity-50" :disabled="!canPlatform('platform.registration.manage')" @click="rejectRegistration(row.id)">رفض</button>
              <button type="button" class="rounded border border-amber-300 px-2 py-1 text-amber-700 disabled:opacity-50" :disabled="!canPlatform('platform.registration.manage')" @click="suspendRegistration(row.id)">تعليق</button>
            </div>
          </div>
          <p v-if="registrationRows.length === 0" class="text-slate-500">لا توجد طلبات تسجيل حالياً.</p>
        </div>

        <div v-else-if="controlDrawer.type === 'plans'" class="space-y-3 text-xs">
          <div v-if="plansError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
            {{ plansError }}
          </div>
          <div class="rounded-lg border border-violet-200 bg-violet-50/60 p-3 dark:border-violet-900/40 dark:bg-violet-950/20">
            <p class="mb-2 font-bold text-violet-900 dark:text-violet-200">إضافة باقة جديدة</p>
            <div class="grid gap-2 sm:grid-cols-2">
              <input v-model="newPlanForm.slug" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="slug مثل: growth-plus">
              <input v-model="newPlanForm.name_ar" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="الاسم العربي">
              <input v-model="newPlanForm.name" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="الاسم الإنجليزي">
              <input v-model.number="newPlanForm.sort_order" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="ترتيب العرض">
              <input v-model.number="newPlanForm.price_monthly" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="السعر الشهري">
              <input v-model.number="newPlanForm.price_yearly" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="السعر السنوي">
              <input v-model.number="newPlanForm.max_users" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="حد المستخدمين">
              <input v-model.number="newPlanForm.max_branches" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="حد الفروع">
            </div>
            <div class="mt-2 flex justify-end">
              <button type="button" class="rounded bg-violet-600 px-2.5 py-1 text-white disabled:opacity-50" :disabled="!canPlatform('platform.subscription.manage')" @click="createPlanCatalogRow">إنشاء الباقة</button>
            </div>
          </div>
          <div v-for="plan in planCatalog" :key="plan.slug" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <div class="grid gap-2 sm:grid-cols-2">
              <input v-model="plan.name_ar" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="اسم عربي">
              <input v-model="plan.name" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="اسم إنجليزي">
              <input v-model.number="plan.price_monthly" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="السعر الشهري">
              <input v-model.number="plan.price_yearly" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="السعر السنوي">
              <input v-model.number="plan.max_users" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="حد المستخدمين">
              <input v-model.number="plan.max_branches" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="حد الفروع">
            </div>
            <div class="mt-3 rounded border border-slate-200 p-2 dark:border-slate-700">
              <div class="mb-2 flex items-center justify-between">
                <p class="font-bold">خصائص الباقة (رئيسية/فرعية + رسوم)</p>
                <button type="button" class="rounded border border-slate-300 px-2 py-1 dark:border-slate-700" @click="addFeatureToPlan(plan)">إضافة خاصية</button>
              </div>
              <div v-if="!plan.feature_catalog || plan.feature_catalog.length === 0" class="text-slate-500">لا توجد خصائص معرفة.</div>
              <div v-else class="space-y-2">
                <div v-for="(feature, idx) in plan.feature_catalog" :key="`${plan.slug}-${idx}`" class="grid gap-2 sm:grid-cols-6">
                  <input v-model="feature.key" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="مفتاح الخاصية">
                  <input v-model="feature.name_ar" type="text" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="اسم عربي">
                  <select v-model="feature.type" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900">
                    <option value="main">رئيسية</option>
                    <option value="sub">فرعية</option>
                  </select>
                  <input v-model.number="feature.price_monthly" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="رسوم شهرية">
                  <input v-model.number="feature.price_yearly" type="number" class="rounded border border-slate-300 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900" placeholder="رسوم سنوية">
                  <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1"><input v-model="feature.is_enabled" type="checkbox"> مفعلة</label>
                    <button type="button" class="rounded border border-red-300 px-2 py-1 text-red-700" @click="removeFeatureFromPlan(plan, idx)">حذف</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-2 flex justify-end">
              <button type="button" class="rounded bg-violet-600 px-2.5 py-1 text-white disabled:opacity-50" :disabled="!canPlatform('platform.subscription.manage')" @click="savePlanCatalogRow(plan)">حفظ</button>
            </div>
          </div>
          <div v-if="planCatalog.length === 0" class="rounded-lg border border-slate-200 p-3 text-slate-600 dark:border-slate-700 dark:text-slate-300">
            <p>لا توجد باقات متاحة.</p>
            <button type="button" class="mt-2 rounded bg-violet-600 px-3 py-1.5 text-white disabled:opacity-50" :disabled="!canPlatform('platform.subscription.manage')" @click="seedDefaultPlans">تهيئة الباقات الافتراضية</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useLocale } from '@/composables/useLocale'
import type { PlatformAdminOverviewPayload } from '@/types/platformAdminOverview'
import PlatformTopbar from '@/components/platform-command-center/PlatformTopbar.vue'
import PlatformSidebarNav from '@/components/platform-command-center/PlatformSidebarNav.vue'
import PlatformStatusOverview from '@/components/platform-command-center/PlatformStatusOverview.vue'
import PlatformInterventionCards from '@/components/platform-command-center/PlatformInterventionCards.vue'
import PlatformIntelligencePanel from '@/components/platform-command-center/PlatformIntelligencePanel.vue'
import PlatformRevenueChart from '@/components/platform-command-center/PlatformRevenueChart.vue'
import PlatformPlanDistribution from '@/components/platform-command-center/PlatformPlanDistribution.vue'
import PlatformCompaniesTable from '@/components/platform-command-center/PlatformCompaniesTable.vue'
import PlatformQuickSupportCard from '@/components/platform-command-center/PlatformQuickSupportCard.vue'
import PlatformControlModules from '@/components/platform-command-center/PlatformControlModules.vue'

type CompanyRow = {
  id: number
  name: string
  plan_slug: string | null
  plan_name: string | null
  users_count: number
  monthly_revenue: number
  subscription_status: string | null
  company_status: string | null
  created_at: string | null
}

const auth = useAuthStore()
const router = useRouter()
const { lang } = useLocale()
const loading = ref(false)
const error = ref('')
const globalSearch = ref('')
const globalSearchLoading = ref(false)
const globalSearchError = ref('')
const globalSearchResults = ref<{ companies: any[]; users: any[]; customers: any[]; invoices: any[]; work_orders: any[] }>({
  companies: [],
  users: [],
  customers: [],
  invoices: [],
  work_orders: [],
})
const activeSection = ref('overview')
const overview = ref<PlatformAdminOverviewPayload | null>(null)
const companies = ref<CompanyRow[]>([])
const companyModalOpen = ref(false)
const companyModalLoading = ref(false)
const companyModalError = ref('')
const companyModalData = ref<any | null>(null)
const companyEntitySnapshot = ref<any | null>(null)
const companyActionLoading = ref(false)
const currentCompanyId = ref<number | null>(null)
const companyPlanSlug = ref('')
type PlanFeatureCatalogItem = {
  key: string
  name?: string
  name_ar?: string
  type: 'main' | 'sub'
  price_monthly?: number
  price_yearly?: number
  is_enabled?: boolean
}

type PlanCatalogRow = {
  slug: string
  name?: string
  name_ar?: string
  price_monthly?: number
  price_yearly?: number
  max_users?: number
  max_branches?: number
  sort_order?: number
  features?: string[]
  feature_catalog?: PlanFeatureCatalogItem[]
}

const planCatalog = ref<PlanCatalogRow[]>([])
const newPlanForm = reactive<PlanCatalogRow>({
  slug: '',
  name: '',
  name_ar: '',
  price_monthly: 0,
  price_yearly: 0,
  max_users: 5,
  max_branches: 1,
  sort_order: 1,
  feature_catalog: [],
})
const COMPANY_LAST_SECTION_KEY = 'platform_company_last_section_v1'
const DEFAULT_COMPANY_SECTION_PATH = '/customers'
const opsSummary = ref<{ failed_jobs_count?: number | null; redis_ok?: boolean; database_ok?: boolean } | null>(null)
const auditCount = ref<number>(0)
const registrationCount = ref<number>(0)
const cancellationCount = ref<number>(0)
const controlDrawer = reactive<{ open: boolean; type: '' | 'audit' | 'cancellations' | 'announcement' | 'plans' | 'registration'; title: string; loading: boolean }>({
  open: false, type: '', title: '', loading: false,
})
const auditRows = ref<any[]>([])
const cancellationRows = ref<any[]>([])
const registrationRows = ref<any[]>([])
const announcementForm = reactive({
  is_enabled: false,
  title: '',
  message: '',
  link_url: '',
  link_text: '',
  variant: 'promo',
  dismissible: true,
})
const plansError = ref('')

const filters = reactive({
  search: '',
  plan: '',
  subscription: '',
  health: '',
})

const navItems = [
  { id: 'overview', label: 'الوضع العام للمنصة' },
  { id: 'intervention', label: 'يحتاج تدخل الآن' },
  { id: 'intelligence', label: 'ذكاء المنصة والتوصيات' },
  { id: 'analytics', label: 'التحليلات التنفيذية' },
  { id: 'companies', label: 'الشركات والإجراءات المباشرة' },
  { id: 'controls', label: 'تحكم المنصة الشامل' },
  { id: 'support', label: 'الدعم السريع' },
]

const platformPermissionSet = computed(() => new Set((auth.permissions ?? []).map((p) => String(p))))
function canPlatform(permission: string): boolean {
  if (auth.isOwner) return true
  return platformPermissionSet.value.has(permission)
}

const statusCards = computed(() => {
  const o = overview.value
  if (!o) return []
  const health = o.health.trend === 'stable' ? 'ممتاز' : 'يحتاج انتباه'
  return [
    { key: 'platform', label: 'حالة المنصة', value: health, delta: 'مقارنة بآخر فترة', className: 'border-violet-200 dark:border-violet-900/60' },
    { key: 'finance', label: 'الصحة المالية', value: o.kpis.estimated_mrr > 0 ? 'جيدة' : 'متوسطة', delta: `${Math.round(o.kpis.estimated_mrr).toLocaleString('ar-SA')} MRR`, className: 'border-emerald-200 dark:border-emerald-900/60' },
    { key: 'renewal', label: 'معدل التجديد', value: `${Math.max(0, 100 - Math.round((o.kpis.churn_risk_companies / Math.max(1, o.kpis.total_companies)) * 100))}%`, delta: 'من الشركات النشطة', className: 'border-sky-200 dark:border-sky-900/60' },
    { key: 'users', label: 'إجمالي المستخدمين', value: o.kpis.total_users.toLocaleString('ar-SA'), delta: 'عدد المستخدمين النشطين', className: 'border-slate-200 dark:border-slate-800' },
    { key: 'companies', label: 'الشركات النشطة', value: o.kpis.active_companies.toLocaleString('ar-SA'), delta: `من أصل ${o.kpis.total_companies.toLocaleString('ar-SA')}`, className: 'border-slate-200 dark:border-slate-800' },
    { key: 'revenue', label: 'إيرادات الشهر', value: `${Math.round(o.kpis.estimated_mrr).toLocaleString('ar-SA')} ر.س`, delta: 'تقدير تشغيلي', className: 'border-slate-200 dark:border-slate-800' },
  ]
})

const interventionItems = computed(() => {
  const o = overview.value
  if (!o) return []
  const openAlerts = o.alerts.length
  return [
    { key: 'payments', title: 'تأخر في الدفع', count: String(o.kpis.churn_risk_companies), desc: 'شركات ذات مخاطر إلغاء تحتاج متابعة تحصيل.', cta: 'متابعة الآن', severity: 'high' as const },
    { key: 'support', title: 'تنبيهات تشغيلية', count: String(openAlerts), desc: 'تنبيهات عالية ومتوسطة من نبض المنصة.', cta: 'عرض التفاصيل', severity: openAlerts > 0 ? 'medium' as const : 'low' as const },
    { key: 'low-activity', title: 'انخفاض استخدام', count: String(o.kpis.low_activity_companies), desc: 'شركات بنشاط منخفض تستدعي تدخل نجاح العملاء.', cta: 'فتح القائمة', severity: 'medium' as const },
  ]
})

const intelligenceRecommendations = computed(() => {
  const o = overview.value
  if (!o) return []
  const out = o.insights.slice(0, 3).map((i) => ({
    what: localizeInsightText(String(i.text ?? '')),
    why: 'مؤشر مشتق من سلوك الاشتراك والنشاط التشغيلي.',
    impact: 'رفع الاستقرار وتقليل التسرب المحتمل.',
    action: 'اتخاذ إجراء',
  }))
  if (out.length === 0 && o.kpis.churn_risk_companies > 0) {
    out.push({
      what: `يوجد ${o.kpis.churn_risk_companies} شركات مهددة بالإلغاء`,
      why: 'انخفاض نشاط أو حالة اشتراك غير مستقرة.',
      impact: 'تحسين معدل التجديد والتحصيل.',
      action: 'مراجعة الشركات',
    })
  }
  return out
})

function localizeInsightText(text: string): string {
  if (lang.value !== 'ar') return text
  const normalized = text.trim()
  const map: Record<string, string> = {
    'Company growth signal: 3 new companies in 7d (100% of 30d baseline).': 'إشارة نمو الشركات: تم تسجيل 3 شركات جديدة خلال 7 أيام (100% من خط الأساس لـ 30 يوم).',
    'Actionable attention list is available for prioritized platform follow-up.': 'توجد قائمة متابعة تنفيذية متاحة بالأولوية لإجراءات المنصة.',
  }
  if (map[normalized]) return map[normalized]
  // If backend returns English text not yet mapped, keep readable Arabic fallback.
  const hasLatin = /[A-Za-z]/.test(normalized)
  return hasLatin ? 'توصية تشغيلية: راجع تفاصيل التوصية من لوحة المتابعة.' : normalized
}

const revenuePoints = computed(() => {
  const points = overview.value?.trends?.companies_growth ?? []
  if (points.length === 0) return []
  const maxVal = Math.max(1, ...points.map((p) => Number(p.count ?? 0)))
  return points.slice(-8).map((p) => ({
    label: (p.date || '').slice(5),
    height: Math.max(8, Math.round((Number(p.count ?? 0) / maxVal) * 100)),
  }))
})

const planRows = computed(() => {
  const map = overview.value?.distribution?.by_plan ?? {}
  const entries = Object.entries(map)
  const total = entries.reduce((s, [, v]) => s + Number(v), 0)
  return entries.map(([label, count]) => ({
    label,
    count: Number(count),
    percent: total > 0 ? Math.round((Number(count) / total) * 100) : 0,
  }))
})

const uniquePlans = computed(() => Array.from(new Set(companies.value.map((c) => c.plan_name || c.plan_slug || 'غير محدد'))))
const quickSearchCompanies = computed(() => {
  return globalSearchResults.value.companies.slice(0, 8)
})
const controlModules = computed(() => [
  {
    key: 'ops',
    title: 'تشغيل المنصة',
    value: opsSummary.value?.failed_jobs_count != null ? String(opsSummary.value.failed_jobs_count) : '—',
    desc: `قاعدة البيانات: ${opsSummary.value?.database_ok ? 'سليمة' : '—'} · Redis: ${opsSummary.value?.redis_ok ? 'سليم' : '—'}`,
    path: '__ops__',
    disabled: !canPlatform('platform.ops.read'),
    disabledReason: 'لا تملك صلاحية تشغيل المنصة.',
  },
  {
    key: 'audit',
    title: 'تدقيق المنصة',
    value: auditCount.value.toLocaleString('ar-SA'),
    desc: 'سجلات التدقيق المتاحة للمراجعة.',
    path: '__audit__',
    disabled: !canPlatform('platform.audit.read'),
    disabledReason: 'لا تملك صلاحية تدقيق المنصة.',
  },
  {
    key: 'registration',
    title: 'طلبات التسجيل',
    value: registrationCount.value.toLocaleString('ar-SA'),
    desc: 'طلبات تحتاج مراجعة واعتماد.',
    path: '__registration__',
    disabled: !canPlatform('platform.registration.read'),
    disabledReason: 'لا تملك صلاحية عرض طلبات التسجيل.',
  },
  {
    key: 'cancellations',
    title: 'إلغاء أوامر العمل',
    value: cancellationCount.value.toLocaleString('ar-SA'),
    desc: 'طلبات إلغاء بانتظار قرار المنصة.',
    path: '__cancellations__',
    disabled: !canPlatform('platform.cancellations.read'),
    disabledReason: 'لا تملك صلاحية عرض طلبات الإلغاء.',
  },
  {
    key: 'qa',
    title: 'التحقق QA',
    value: 'جودة',
    desc: 'اختبارات السلامة والجودة التشغيلية.',
    path: '/admin/qa',
  },
  {
    key: 'plans',
    title: 'كتالوج الباقات',
    value: planCatalog.value.length.toLocaleString('ar-SA'),
    desc: 'تعديل عالمي لأسعار وأسماء الباقات.',
    path: '__plans__',
    disabled: !canPlatform('platform.companies.read'),
    disabledReason: 'لا تملك صلاحية عرض كتالوج الباقات.',
  },
  {
    key: 'announcement',
    title: 'شريط الإعلانات',
    value: 'إدارة',
    desc: 'تحديث الرسائل العامة للمستخدمين.',
    path: '__announcement__',
    disabled: !canPlatform('platform.announcement.read'),
    disabledReason: 'لا تملك صلاحية عرض الإعلانات.',
  },
])

function computeHealth(c: CompanyRow): 'excellent' | 'good' | 'risk' {
  const sub = String(c.subscription_status ?? '').toLowerCase()
  const status = String(c.company_status ?? '').toLowerCase()
  if (status.includes('suspend') || sub.includes('suspend') || sub.includes('expired')) return 'risk'
  if (sub.includes('trial')) return 'good'
  return 'excellent'
}

function formatPlanLabel(planSlug: string | null | undefined): string {
  if (!planSlug) return '—'
  const slug = String(planSlug)
  const row = planCatalog.value.find((p) => p.slug === slug)
  return row?.name_ar || row?.name || slug
}

function formatCompanyOperationalStatus(status: string | null | undefined): string {
  const key = String(status ?? '').toLowerCase()
  if (!key) return '—'
  if (key === 'active') return 'نشطة'
  if (key === 'inactive') return 'غير نشطة'
  if (key === 'suspended') return 'معلقة'
  return key
}

function formatSubscriptionStatus(status: string | null | undefined): string {
  const key = String(status ?? '').toLowerCase()
  if (!key) return '—'
  if (key === 'active') return 'نشط'
  if (key === 'trial' || key === 'trialing') return 'تجريبي'
  if (key === 'suspended') return 'موقوف'
  if (key === 'expired') return 'منتهي'
  if (key === 'cancelled' || key === 'canceled') return 'ملغي'
  return key
}

const companyRows = computed(() => {
  const q = (filters.search || globalSearch.value).trim().toLowerCase()
  return companies.value
    .filter((c) => {
      const health = computeHealth(c)
      if (q && !String(c.name).toLowerCase().includes(q)) return false
      if (filters.plan && (c.plan_name || c.plan_slug || 'غير محدد') !== filters.plan) return false
      if (filters.subscription && String(c.subscription_status ?? '').toLowerCase() !== filters.subscription) return false
      if (filters.health && health !== filters.health) return false
      return true
    })
    .slice(0, 120)
    .map((c) => {
      const health = computeHealth(c)
      return {
        id: c.id,
        name: c.name,
        plan: c.plan_name || c.plan_slug || 'غير محدد',
        subscription: c.subscription_status || 'غير محدد',
        subscriptionClass:
          c.subscription_status === 'active'
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
            : c.subscription_status === 'trial'
              ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200'
              : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        renewal: c.created_at ? new Date(c.created_at).toLocaleDateString('ar-SA') : '—',
        users: c.users_count.toLocaleString('ar-SA'),
        revenue: `${Math.round(c.monthly_revenue || 0).toLocaleString('ar-SA')} ر.س`,
        health: health === 'excellent' ? 'ممتاز' : health === 'good' ? 'جيد' : 'خطر',
        healthClass:
          health === 'excellent'
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
            : health === 'good'
              ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200'
              : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
      }
    })
})

async function refresh(): Promise<void> {
  if (!auth.isPlatform) return
  loading.value = true
  error.value = ''
  try {
    const [overviewRes, companiesRes, opsRes, auditRes, registrationRes, cancellationRes, plansRes] = await Promise.allSettled([
      apiClient.get('/admin/overview', { skipGlobalErrorToast: true }),
      apiClient.get('/admin/companies', { skipGlobalErrorToast: true }),
      apiClient.get('/platform/ops-summary', { skipGlobalErrorToast: true }),
      apiClient.get('/platform/audit-logs', { params: { per_page: 1 }, skipGlobalErrorToast: true }),
      apiClient.get('/platform/registration-profiles', { params: { per_page: 1 }, skipGlobalErrorToast: true }),
      apiClient.get('/platform/work-order-cancellation-requests', { params: { per_page: 1 }, skipGlobalErrorToast: true }),
      apiClient.get('/platform/plans', { skipGlobalErrorToast: true }),
    ])
    if (overviewRes.status === 'fulfilled') {
      overview.value = overviewRes.value.data?.data ?? null
    }
    if (companiesRes.status === 'fulfilled') {
      companies.value = Array.isArray(companiesRes.value.data?.data) ? companiesRes.value.data.data : []
    }
    if (opsRes.status === 'fulfilled') {
      opsSummary.value = opsRes.value.data?.data ?? null
    }
    if (auditRes.status === 'fulfilled') {
      auditCount.value = Number(auditRes.value.data?.pagination?.total ?? 0)
    }
    if (registrationRes.status === 'fulfilled') {
      registrationCount.value = Number(registrationRes.value.data?.pagination?.total ?? 0)
    }
    if (cancellationRes.status === 'fulfilled') {
      cancellationCount.value = Number(cancellationRes.value.data?.pagination?.total ?? 0)
    }
    if (plansRes.status === 'fulfilled') {
      const body = plansRes.value.data
      planCatalog.value = normalizePlanCatalog(Array.isArray(body?.data) ? body.data : (Array.isArray(body) ? body : []))
    } else {
      planCatalog.value = []
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'تعذر تحميل بيانات مركز التحكم.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void refresh()
})

let globalSearchTimer: ReturnType<typeof setTimeout> | null = null
watch(
  () => globalSearch.value,
  (value) => {
    if (globalSearchTimer) clearTimeout(globalSearchTimer)
    const q = value.trim()
    if (!q) {
      globalSearchResults.value = { companies: [], users: [], customers: [], invoices: [], work_orders: [] }
      globalSearchLoading.value = false
      globalSearchError.value = ''
      return
    }
    globalSearchLoading.value = true
    globalSearchError.value = ''
    globalSearchTimer = setTimeout(() => {
      void runGlobalSearch(q)
    }, 250)
  },
)

function openCompany(id: number): void {
  void openCompanyModal(id)
}

function manageCompany(id: number): void {
  void openCompanyModal(id)
}

function handleIntelligenceAction(index: number): void {
  if (index === 0) {
    jumpToSection('companies')
    filters.subscription = 'suspended'
    return
  }
  if (index === 1) {
    void openAuditDrawer()
    return
  }
  void openCancellationsDrawer()
}

function handleInterventionAction(key: string): void {
  if (key === 'payments') {
    activeSection.value = 'companies'
    filters.subscription = 'suspended'
    return
  }
  if (key === 'support') {
    void openPath('__audit__')
    return
  }
  void openPath('__cancellations__')
}

function openTicket(): void {
  void router.push('/support')
}

function openNotifications(): void {
  void openPath('__audit__')
}

function openHelp(): void {
  void router.push('/about/capabilities')
}

function contactOps(): void {
  void openOpsDrawer()
}

function openPath(path: string): void {
  if (path === '__ops__') {
    void openOpsDrawer()
    return
  }
  if (path === '__audit__') {
    void openAuditDrawer()
    return
  }
  if (path === '__cancellations__') {
    void openCancellationsDrawer()
    return
  }
  if (path === '__announcement__') {
    void openAnnouncementDrawer()
    return
  }
  if (path === '__plans__') {
    void openPlansDrawer()
    return
  }
  if (path === '__registration__') {
    void openRegistrationDrawer()
    return
  }
  void router.push(path)
}

function openControlModule(key: string, path: string): void {
  if (key === 'ops') {
    void openOpsDrawer()
    return
  }
  if (key === 'audit') {
    void openAuditDrawer()
    return
  }
  if (key === 'cancellations') {
    void openCancellationsDrawer()
    return
  }
  if (key === 'announcement') {
    void openAnnouncementDrawer()
    return
  }
  if (key === 'plans') {
    void openPlansDrawer()
    return
  }
  void openPath(path)
}

function jumpToSection(id: string): void {
  activeSection.value = id
  if (typeof window === 'undefined') return
  const el = document.getElementById(id)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

function goBack(): void {
  void router.back()
}

function goHome(): void {
  void router.push('/')
}

async function openCompanyModal(id: number): Promise<void> {
  companyModalOpen.value = true
  companyModalLoading.value = true
  companyModalError.value = ''
  currentCompanyId.value = id
  try {
    const [companyRes, snapshotRes] = await Promise.all([
      apiClient.get(`/platform/companies/${id}`, { skipGlobalErrorToast: true }),
      apiClient.get(`/platform/companies/${id}/entity-snapshot`, { skipGlobalErrorToast: true }),
    ])
    companyModalData.value = companyRes.data?.data ?? null
    companyEntitySnapshot.value = snapshotRes.data?.data ?? null
    companyPlanSlug.value = String(companyRes.data?.data?.subscription?.plan ?? '')
  } catch (e: any) {
    companyModalError.value = e?.response?.data?.message ?? 'تعذر تحميل بيانات الشركة.'
    companyModalData.value = null
    companyEntitySnapshot.value = null
  } finally {
    companyModalLoading.value = false
  }
}

function closeCompanyModal(): void {
  companyModalOpen.value = false
  companyEntitySnapshot.value = null
}

async function updateCompanyOperational(isActive: boolean, status: 'active' | 'inactive' | 'suspended'): Promise<void> {
  if (!currentCompanyId.value) return
  companyActionLoading.value = true
  companyModalError.value = ''
  try {
    await apiClient.patch(`/platform/companies/${currentCompanyId.value}/operational`, {
      is_active: isActive,
      status,
    }, { skipGlobalErrorToast: true })
    await Promise.all([refresh(), openCompanyModal(currentCompanyId.value)])
  } catch (e: any) {
    companyModalError.value = e?.response?.data?.message ?? 'فشل تحديث حالة الشركة.'
  } finally {
    companyActionLoading.value = false
  }
}

async function updateCompanySubscription(): Promise<void> {
  if (!currentCompanyId.value || !companyPlanSlug.value) return
  companyActionLoading.value = true
  companyModalError.value = ''
  try {
    await apiClient.patch(`/platform/companies/${currentCompanyId.value}/subscription`, {
      plan_slug: companyPlanSlug.value,
    }, { skipGlobalErrorToast: true })
    await Promise.all([refresh(), openCompanyModal(currentCompanyId.value)])
  } catch (e: any) {
    companyModalError.value = e?.response?.data?.message ?? 'فشل تحديث الاشتراك.'
  } finally {
    companyActionLoading.value = false
  }
}

function openCompanyWorkspace(): void {
  if (!currentCompanyId.value) return
  const path = getCompanyLastSection(currentCompanyId.value)
  void router.push({ path, query: { company_id: String(currentCompanyId.value) } })
}

function openCompanyProfile(): void {
  if (!currentCompanyId.value) return
  void router.push(`/companies/${currentCompanyId.value}`)
}

function openCompanyScoped(path: string): void {
  if (!currentCompanyId.value) return
  setCompanyLastSection(currentCompanyId.value, path)
  void router.push({ path, query: { company_id: String(currentCompanyId.value) } })
}

function getCompanyLastSection(companyId: number): string {
  if (typeof window === 'undefined') return DEFAULT_COMPANY_SECTION_PATH
  try {
    const raw = window.localStorage.getItem(COMPANY_LAST_SECTION_KEY)
    const parsed = raw ? JSON.parse(raw) : {}
    const val = parsed?.[String(companyId)]
    return typeof val === 'string' && val.startsWith('/') ? val : DEFAULT_COMPANY_SECTION_PATH
  } catch {
    return DEFAULT_COMPANY_SECTION_PATH
  }
}

function setCompanyLastSection(companyId: number, path: string): void {
  if (typeof window === 'undefined') return
  if (!path.startsWith('/')) return
  try {
    const raw = window.localStorage.getItem(COMPANY_LAST_SECTION_KEY)
    const parsed = raw ? JSON.parse(raw) : {}
    const next = { ...parsed, [String(companyId)]: path }
    window.localStorage.setItem(COMPANY_LAST_SECTION_KEY, JSON.stringify(next))
  } catch {
    // ignore storage failures to avoid breaking navigation
  }
}

function addCompany(): void {
  if (!canPlatform('platform.registration.read')) return
  void openRegistrationDrawer()
}

function exportCompanies(): void {
  const rows = companyRows.value
  const headers = ['id', 'name', 'plan', 'subscription', 'renewal', 'users', 'revenue', 'health']
  const csv = [headers.join(','), ...rows.map((r) => [r.id, r.name, r.plan, r.subscription, r.renewal, r.users, r.revenue, r.health].map((v) => `"${String(v).replace(/"/g, '""')}"`).join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `platform_companies_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function closeControlDrawer(): void {
  controlDrawer.open = false
  controlDrawer.type = ''
  controlDrawer.title = ''
}

async function openAuditDrawer(): Promise<void> {
  controlDrawer.open = true
  controlDrawer.type = 'audit'
  controlDrawer.title = 'تدقيق المنصة'
  controlDrawer.loading = true
  try {
    const { data } = await apiClient.get('/platform/audit-logs', { params: { per_page: 25 }, skipGlobalErrorToast: true })
    auditRows.value = extractRows(data)
  } finally {
    controlDrawer.loading = false
  }
}

async function openOpsDrawer(): Promise<void> {
  controlDrawer.open = true
  controlDrawer.type = 'audit'
  controlDrawer.title = 'تشغيل المنصة'
  controlDrawer.loading = true
  try {
    const rows: Array<{ id: string; action: string; created_at: string | null }> = []
    rows.push({
      id: 'ops-db',
      action: `حالة قاعدة البيانات: ${opsSummary.value?.database_ok ? 'سليمة' : 'تحتاج فحص'}`,
      created_at: null,
    })
    rows.push({
      id: 'ops-redis',
      action: `حالة Redis: ${opsSummary.value?.redis_ok ? 'سليمة' : 'تحتاج فحص'}`,
      created_at: null,
    })
    rows.push({
      id: 'ops-jobs',
      action: `الوظائف الفاشلة: ${Number(opsSummary.value?.failed_jobs_count ?? 0).toLocaleString('ar-SA')}`,
      created_at: null,
    })
    auditRows.value = rows
  } finally {
    controlDrawer.loading = false
  }
}

async function openCancellationsDrawer(): Promise<void> {
  controlDrawer.open = true
  controlDrawer.type = 'cancellations'
  controlDrawer.title = 'طلبات إلغاء أوامر العمل'
  controlDrawer.loading = true
  try {
    const { data } = await apiClient.get('/platform/work-order-cancellation-requests', { params: { per_page: 25 }, skipGlobalErrorToast: true })
    cancellationRows.value = extractRows(data)
  } finally {
    controlDrawer.loading = false
  }
}

async function approveCancellation(id: number): Promise<void> {
  await apiClient.post(`/platform/work-order-cancellation-requests/${id}/approve`, {}, { skipGlobalErrorToast: true })
  await Promise.all([openCancellationsDrawer(), refresh()])
}

async function rejectCancellation(id: number): Promise<void> {
  await apiClient.post(`/platform/work-order-cancellation-requests/${id}/reject`, { review_notes: 'تم الرفض من مركز التحكم' }, { skipGlobalErrorToast: true })
  await Promise.all([openCancellationsDrawer(), refresh()])
}

async function openAnnouncementDrawer(): Promise<void> {
  controlDrawer.open = true
  controlDrawer.type = 'announcement'
  controlDrawer.title = 'إدارة شريط الإعلانات'
  controlDrawer.loading = true
  try {
    const { data } = await apiClient.get('/platform/announcement-banner/admin', { skipGlobalErrorToast: true })
    Object.assign(announcementForm, data?.data ?? {})
  } finally {
    controlDrawer.loading = false
  }
}

async function saveAnnouncement(): Promise<void> {
  await apiClient.put('/platform/announcement-banner', announcementForm, { skipGlobalErrorToast: true })
  await refresh()
}

async function openPlansDrawer(): Promise<void> {
  controlDrawer.open = true
  controlDrawer.type = 'plans'
  controlDrawer.title = 'إدارة كتالوج الباقات'
  controlDrawer.loading = true
  plansError.value = ''
  try {
    try {
      const { data } = await apiClient.get('/platform/plans', { skipGlobalErrorToast: true })
      const body = data
      planCatalog.value = normalizePlanCatalog(Array.isArray(body?.data) ? body.data : (Array.isArray(body) ? body : []))
    } catch {
      const { data } = await apiClient.get('/plans', { skipGlobalErrorToast: true })
      const body = data
      planCatalog.value = normalizePlanCatalog(Array.isArray(body?.data) ? body.data : (Array.isArray(body) ? body : []))
    }
    if (planCatalog.value.length === 0) {
      plansError.value = 'لم يتم العثور على باقات نشطة في المصدر الحالي.'
    }
  } catch (e: any) {
    planCatalog.value = []
    plansError.value = e?.response?.data?.message ?? 'تعذر تحميل كتالوج الباقات.'
  } finally {
    controlDrawer.loading = false
  }
}

async function savePlanCatalogRow(plan: any): Promise<void> {
  if (!plan?.slug) return
  await apiClient.put(`/platform/plans/${plan.slug}`, {
    name: plan.name,
    name_ar: plan.name_ar,
    price_monthly: plan.price_monthly,
    price_yearly: plan.price_yearly,
    max_users: plan.max_users,
    max_branches: plan.max_branches,
    feature_catalog: normalizeFeatureCatalog(plan.feature_catalog),
  }, { skipGlobalErrorToast: true })
  await refresh()
}

async function createPlanCatalogRow(): Promise<void> {
  if (!newPlanForm.slug || !newPlanForm.name || !newPlanForm.name_ar) {
    plansError.value = 'املأ slug واسم الباقة العربي والإنجليزي.'
    return
  }
  plansError.value = ''
  await apiClient.post('/platform/plans', {
    slug: String(newPlanForm.slug).trim().toLowerCase(),
    name: newPlanForm.name,
    name_ar: newPlanForm.name_ar,
    price_monthly: Number(newPlanForm.price_monthly ?? 0),
    price_yearly: Number(newPlanForm.price_yearly ?? 0),
    max_users: Number(newPlanForm.max_users ?? 5),
    max_branches: Number(newPlanForm.max_branches ?? 1),
    sort_order: Number(newPlanForm.sort_order ?? 1),
    feature_catalog: normalizeFeatureCatalog(newPlanForm.feature_catalog),
  }, { skipGlobalErrorToast: true })
  newPlanForm.slug = ''
  newPlanForm.name = ''
  newPlanForm.name_ar = ''
  newPlanForm.price_monthly = 0
  newPlanForm.price_yearly = 0
  newPlanForm.max_users = 5
  newPlanForm.max_branches = 1
  newPlanForm.sort_order = 1
  newPlanForm.feature_catalog = []
  await openPlansDrawer()
  await refresh()
}

function addFeatureToPlan(plan: PlanCatalogRow): void {
  if (!Array.isArray(plan.feature_catalog)) {
    plan.feature_catalog = []
  }
  plan.feature_catalog.push({
    key: '',
    name: '',
    name_ar: '',
    type: 'sub',
    price_monthly: 0,
    price_yearly: 0,
    is_enabled: true,
  })
}

function removeFeatureFromPlan(plan: PlanCatalogRow, idx: number): void {
  if (!Array.isArray(plan.feature_catalog)) return
  plan.feature_catalog.splice(idx, 1)
}

function normalizeFeatureCatalog(input: unknown): PlanFeatureCatalogItem[] {
  if (!Array.isArray(input)) return []
  return input
    .map((row) => {
      const item = row as Record<string, unknown>
      return {
        key: String(item.key ?? '').trim(),
        name: String(item.name ?? '').trim(),
        name_ar: String(item.name_ar ?? '').trim(),
        type: item.type === 'main' ? 'main' : 'sub',
        price_monthly: Number(item.price_monthly ?? 0),
        price_yearly: Number(item.price_yearly ?? 0),
        is_enabled: item.is_enabled !== false,
      } as PlanFeatureCatalogItem
    })
    .filter((row) => row.key !== '')
}

function normalizePlanCatalog(rows: unknown[]): PlanCatalogRow[] {
  return rows.map((row) => {
    const item = row as Record<string, unknown>
    const featureCatalog = normalizeFeatureCatalog(item.feature_catalog)
    return {
      slug: String(item.slug ?? ''),
      name: String(item.name ?? ''),
      name_ar: String(item.name_ar ?? ''),
      price_monthly: Number(item.price_monthly ?? 0),
      price_yearly: Number(item.price_yearly ?? 0),
      max_users: Number(item.max_users ?? 5),
      max_branches: Number(item.max_branches ?? 1),
      sort_order: Number(item.sort_order ?? 1),
      features: Array.isArray(item.features) ? item.features as string[] : [],
      feature_catalog: featureCatalog,
    }
  })
}

async function seedDefaultPlans(): Promise<void> {
  await apiClient.post('/plans/seed', {}, { skipGlobalErrorToast: true })
  await openPlansDrawer()
  await refresh()
}

async function openRegistrationDrawer(): Promise<void> {
  controlDrawer.open = true
  controlDrawer.type = 'registration'
  controlDrawer.title = 'طلبات التسجيل بالجوال'
  controlDrawer.loading = true
  try {
    const { data } = await apiClient.get('/platform/registration-profiles', { params: { per_page: 25 }, skipGlobalErrorToast: true })
    registrationRows.value = extractRows(data)
  } finally {
    controlDrawer.loading = false
  }
}

async function approveRegistration(id: number): Promise<void> {
  await apiClient.post(`/platform/registration-profiles/${id}/approve`, {}, { skipGlobalErrorToast: true })
  await Promise.all([openRegistrationDrawer(), refresh()])
}

async function rejectRegistration(id: number): Promise<void> {
  await apiClient.post(`/platform/registration-profiles/${id}/reject`, {}, { skipGlobalErrorToast: true })
  await Promise.all([openRegistrationDrawer(), refresh()])
}

async function suspendRegistration(id: number): Promise<void> {
  await apiClient.post(`/platform/registration-profiles/${id}/suspend`, {}, { skipGlobalErrorToast: true })
  await Promise.all([openRegistrationDrawer(), refresh()])
}

function extractRows(payload: any): any[] {
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload)) return payload
  return []
}

async function runGlobalSearch(q: string): Promise<void> {
  try {
    const { data } = await apiClient.get('/platform/search', { params: { q, limit: 8 }, skipGlobalErrorToast: true })
    const out = data?.data ?? {}
    globalSearchResults.value = {
      companies: Array.isArray(out?.companies) ? out.companies : [],
      users: Array.isArray(out?.users) ? out.users : [],
      customers: Array.isArray(out?.customers) ? out.customers : [],
      invoices: Array.isArray(out?.invoices) ? out.invoices : [],
      work_orders: Array.isArray(out?.work_orders) ? out.work_orders : [],
    }
  } catch (e: any) {
    globalSearchResults.value = { companies: [], users: [], customers: [], invoices: [], work_orders: [] }
    globalSearchError.value = e?.response?.data?.message ?? 'تعذر تنفيذ البحث الشامل.'
  } finally {
    globalSearchLoading.value = false
  }
}

function openCompanyScopedGlobal(path: string): void {
  const first = quickSearchCompanies.value[0]
  if (!first) return
  void router.push({ path, query: { company_id: String(first.id) } })
}
</script>
