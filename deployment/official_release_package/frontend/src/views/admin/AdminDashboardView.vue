<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 transition-colors duration-200 dark:bg-gray-950 dark:text-white" dir="rtl">
    <!-- Admin Header -->
    <div class="bg-gradient-to-l from-slate-100 via-purple-100 to-slate-100 border-b border-purple-200/70 dark:from-slate-900 dark:via-purple-950 dark:to-slate-900 dark:border-purple-900/50 px-6 py-4">
      <div class="max-w-screen-2xl mx-auto flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
          <RouterLink
            to="/"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300/90 bg-white/90 px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-white dark:border-white/15 dark:bg-white/10 dark:text-slate-100 dark:hover:bg-white/15"
          >
            <ArrowRightIcon class="h-4 w-4 opacity-80" />
            العودة للتطبيق
          </RouterLink>
          <div class="w-10 h-10 rounded-xl bg-purple-600/20 border border-purple-500/35 flex items-center justify-center dark:bg-purple-600/30 dark:border-purple-500/40">
            <CpuChipIcon class="w-6 h-6 text-purple-700 dark:text-purple-400" />
          </div>
          <div>
            <h1 class="section-title font-black text-slate-900 dark:text-white">أسس برو — إدارة المنصة</h1>
            <p class="text-xs leading-relaxed text-purple-700 dark:text-purple-400">لوحة التحكم المركزية للمنصة</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
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
          <div class="text-right">
            <div class="text-xs text-slate-500 dark:text-gray-400">آخر تحديث</div>
            <div class="text-sm font-mono text-emerald-700 dark:text-green-400">{{ lastRefresh }}</div>
          </div>
          <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-200/80 transition-all hover:bg-slate-300 dark:bg-white/10 dark:hover:bg-white/20" @click="refresh">
            <ArrowPathIcon class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-screen-2xl mx-auto px-6 py-6">
      <div
        v-if="platformBanner"
        class="mb-4 rounded-xl border px-4 py-3 text-sm leading-relaxed"
        :class="platformMode ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-600/30 dark:bg-emerald-950/30 dark:text-emerald-100' : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-600/40 dark:bg-amber-950/40 dark:text-amber-100'"
      >
        {{ platformBanner }}
      </div>
      <div class="mb-4 text-xs text-slate-600 leading-relaxed dark:text-gray-400">
        <RouterLink to="/about/taxonomy" class="font-medium text-purple-700 underline underline-offset-2 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300">
          مسرد المنصة والمستأجر والعميل النهائي
        </RouterLink>
        <span class="mx-1.5">—</span>
        <span>تعريفات ثابتة لتوحيد المفاهيم (صفحة مساعدة داخل تطبيق فريق العمل).</span>
      </div>
      <!-- Tabs -->
      <div class="mb-6 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-slate-200/80 p-1 dark:border-gray-800 dark:bg-gray-900">
        <button v-for="t in adminTabs" :key="t.id" :class="activeTab === t.id ? 'bg-purple-600 text-white' : 'text-slate-600 hover:bg-white hover:text-slate-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap"
                @click="activeTab = t.id"
        >
          <component :is="t.icon" class="w-4 h-4" />
          {{ t.label }}
        </button>
      </div>

      <!-- ══ TAB: OVERVIEW ══ -->
      <div v-if="activeTab === 'overview'">
        <!-- Platform KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
          <AdminKpi label="إجمالي المشتركين" :value="overview.total_companies" color="purple" />
          <AdminKpi label="نشطون (شركات مفعّلة)" :value="overview.active_today" color="green" />
          <AdminKpi label="الإيراد الشهري (تقديري)" :value="formatCurrency(overview.mrr)" color="emerald" />
          <AdminKpi label="الإيراد السنوي (تقديري)" :value="formatCurrency(overview.arr)" color="teal" />
          <AdminKpi label="التجربة المجانية" :value="overview.trial_count" color="yellow" />
          <AdminKpi label="معدل التحويل" :value="`${overview.conversion_rate}%`" color="blue" />
        </div>
        <p class="mb-4 text-[11px] text-gray-500">
          الأرقام أعلاه مُشتقة من قائمة المشتركين الحالية وأسعار الباقات — دون مقارنة زمنية ولا «نمو» حقيقي حتى يتوفر تتبع منصة.
        </p>

        <!-- Revenue Chart Placeholder + Recent Activity -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
          <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="font-bold text-slate-900 dark:text-white">الإيراد الشهري (آخر 12 شهر)</h3>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-gray-500">
                  عرض تقديري: توزيع متساوٍ من إجمالي MRR الحالي — ليس محاسبة منصة ولا تاريخاً فعلياً.
                </p>
              </div>
              <div class="flex gap-1">
                <button v-for="r in ['3m','6m','12m']" :key="r"
                        :class="revenueRange === r ? 'bg-purple-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                        class="px-3 py-1 rounded-lg text-xs transition-all"
                        @click="revenueRange = r"
                >
                  {{ r }}
                </button>
              </div>
            </div>
            <!-- Simple Bar Chart -->
            <div class="flex items-end gap-2 h-32">
              <div v-for="(m, i) in revenueData" :key="i" class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full rounded-t-sm bg-purple-500/60 hover:bg-purple-400 transition-all cursor-pointer relative group"
                     :style="{ height: `${(m.value / maxRevenue) * 100}%` }"
                >
                  <div class="absolute -top-8 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-xs text-white opacity-0 shadow-md group-hover:opacity-100 dark:bg-gray-800">
                    {{ formatCurrency(m.value) }}
                  </div>
                </div>
                <span class="text-xs text-slate-500 dark:text-gray-500">{{ m.label }}</span>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5">
            <h3 class="mb-4 font-bold text-slate-900 dark:text-white">توزيع الباقات</h3>
            <div class="space-y-3">
              <div v-for="p in planDistribution" :key="p.name">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-slate-600 dark:text-gray-400">{{ p.name }}</span>
                  <span class="font-medium text-slate-900 dark:text-white">{{ p.count }} ({{ p.pct }}%)</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-gray-800">
                  <div class="h-full rounded-full transition-all" :class="p.color" :style="{ width: p.pct + '%' }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Signups -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-hidden">
          <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-gray-800">
            <h3 class="font-bold text-slate-900 dark:text-white">آخر المشتركين</h3>
            <button class="text-sm text-purple-400 hover:text-purple-300" @click="activeTab = 'tenants'">عرض الكل</button>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-gray-800/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-400">الشركة</th>
                <th class="px-4 py-3 text-right text-gray-400">الباقة</th>
                <th class="px-4 py-3 text-right text-gray-400">الإيراد</th>
                <th class="px-4 py-3 text-right text-gray-400">الحالة</th>
                <th class="px-4 py-3 text-right text-gray-400">الانضمام</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-for="c in recentCompanies" :key="c.id" class="hover:bg-slate-100 dark:hover:bg-gray-800/30 cursor-pointer" @click="viewTenant(c)">
                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ c.name }}</td>
                <td class="px-4 py-3"><PlanBadge :plan="c.plan_slug" /></td>
                <td class="px-4 py-3 text-emerald-400">{{ formatCurrency(c.monthly_revenue || 0) }}</td>
                <td class="px-4 py-3"><StatusDot :active="c.is_active" /></td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ formatDate(c.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ TAB: TENANTS ══ -->
      <div v-if="activeTab === 'tenants'">
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
              <tr v-for="c in filteredCompanies" :key="c.id" class="hover:bg-slate-100 dark:hover:bg-gray-800/30">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900 dark:text-white">{{ c.name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ c.slug }}</div>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-gray-300">{{ c.owner_name || '—' }}</td>
                <td class="px-4 py-3"><PlanBadge :plan="c.plan_slug" /></td>
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
      </div>

      <!-- ══ TAB: OPS (platform) ══ -->
      <div v-if="activeTab === 'ops' && platformMode">
        <p class="text-sm text-gray-400 mb-4 leading-relaxed">
          ملخص تشغيلي للمشغّل: طوابير الفشل، اتصال Redis وقاعدة البيانات. للتحقق الكامل من سلامة البيانات استخدم
          <RouterLink to="/admin/qa" class="text-purple-400 underline underline-offset-2">التحقق من النظام (QA)</RouterLink>
          أو الأمر <span class="font-mono text-xs text-slate-300" dir="ltr">php artisan integrity:verify</span> على الخادم.
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
      </div>

      <!-- ══ TAB: AUDIT (platform) ══ -->
      <div v-if="activeTab === 'audit' && platformMode">
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
      </div>

      <!-- ══ TAB: FINANCE (platform) ══ -->
      <div v-if="activeTab === 'finance' && platformMode">
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
      </div>

      <!-- ══ TAB: CANCELLATIONS (platform) ══ -->
      <div v-if="activeTab === 'cancellations' && platformMode">
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
      </div>

      <!-- ══ TAB: PLANS ══ -->
      <div v-if="activeTab === 'plans'">
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
      </div>

      <!-- ══ TAB: REVENUE ══ -->
      <div v-if="activeTab === 'revenue'">
        <div class="grid md:grid-cols-3 gap-4 mb-6">
          <RevenueCard title="MRR (الإيراد الشهري المتكرر)" :value="overview.mrr" />
          <RevenueCard title="ARR (الإيراد السنوي المتكرر)" :value="overview.arr" />
          <RevenueCard title="ARPU (متوسط الإيراد/مشترك)" :value="overview.arpu" />
        </div>
        <p class="mb-4 text-[11px] text-gray-500">القيم محسوبة من البيانات المعروضة فقط — ليست تقريراً مالياً رسمياً للمنصة.</p>

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5 mb-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-900 dark:text-white">تفصيل الإيرادات حسب الباقة</h3>
            <button class="flex items-center gap-2 px-4 py-2 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white rounded-lg text-sm transition-all" @click="exportRevenue">
              <ArrowDownTrayIcon class="w-4 h-4" /> تصدير
            </button>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-gray-400 border-b border-slate-200 dark:border-gray-800">
                <th class="py-2 text-right">الباقة</th>
                <th class="py-2 text-right">المشتركون</th>
                <th class="py-2 text-right">السعر</th>
                <th class="py-2 text-right">الإيراد الشهري</th>
                <th class="py-2 text-right">النسبة</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
              <tr v-for="p in revenueByPlan" :key="p.name">
                <td class="py-3"><PlanBadge :plan="p.slug" /></td>
                <td class="py-3 text-slate-700 dark:text-gray-300">{{ p.count }}</td>
                <td class="py-3 text-slate-700 dark:text-gray-300">{{ formatCurrency(p.price) }}</td>
                <td class="py-3 text-emerald-400 font-bold">{{ formatCurrency(p.revenue) }}</td>
                <td class="py-3">
                  <div class="flex items-center gap-2">
                    <div class="h-1.5 flex-1 rounded-full bg-slate-200 dark:bg-gray-800">
                      <div class="h-full bg-purple-500 rounded-full" :style="{ width: p.pct + '%' }"></div>
                    </div>
                    <span class="text-xs text-gray-400">{{ p.pct }}%</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ TAB: شريط إعلان المنصة (لمشغّلي المنصة) ══ -->
      <div v-if="activeTab === 'banner'">
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
      </div>

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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import {
  CpuChipIcon, ArrowPathIcon, CheckIcon, XMarkIcon,
  BuildingOffice2Icon, CurrencyDollarIcon,
  ChartBarIcon, Cog8ToothIcon, ArrowDownTrayIcon,
  MegaphoneIcon, BanknotesIcon, ExclamationTriangleIcon,
  WrenchScrewdriverIcon, ClipboardDocumentListIcon,
  SunIcon, MoonIcon, ArrowRightIcon,
} from '@heroicons/vue/24/outline'
import { useDarkMode } from '@/composables/useDarkMode'
import { formatAuditAction, formatAuditUserId } from '@/utils/governanceAuditLabels'
import { companyFinancialModelLabel, companyFinancialModelStatusLabel } from '@/utils/companyFinancialLabels'
import { workOrderStatusLabel, cancellationRequestStatusLabel } from '@/utils/workOrderStatusLabels'

const toast = useToast()
const darkMode = useDarkMode()
const activeTab   = ref('overview')
const refreshing  = ref(false)
const lastRefresh = ref(new Date().toLocaleTimeString('ar-SA'))
const tenantSearch = ref('')
const tenantPlanFilter = ref('')
const tenantStatusFilter = ref('')
const revenueRange = ref('12m')
const editingPlan = ref<any>(null)
/** قراءة المشتركين من API عندما يكون البريد ضمن SAAS_PLATFORM_ADMIN_EMAILS */
const platformMode = ref(false)
const platformBanner = ref('')

const baseAdminTabs = [
  { id: 'overview', label: 'النظرة الشاملة',  icon: ChartBarIcon },
  { id: 'tenants',  label: 'المشتركون',        icon: BuildingOffice2Icon },
  { id: 'plans',    label: 'الباقات والميزات', icon: Cog8ToothIcon },
  { id: 'revenue',  label: 'الإيرادات',        icon: CurrencyDollarIcon },
]

const adminTabs = computed(() => {
  if (!platformMode.value) {
    return baseAdminTabs
  }
  return [
    ...baseAdminTabs,
    { id: 'ops', label: 'تشغيل المنصة', icon: WrenchScrewdriverIcon },
    { id: 'audit', label: 'تدقيق المنصة', icon: ClipboardDocumentListIcon },
    { id: 'finance', label: 'النموذج المالي', icon: BanknotesIcon },
    { id: 'cancellations', label: 'إلغاء أوامر العمل', icon: ExclamationTriangleIcon },
    { id: 'banner', label: 'شريط الإعلان', icon: MegaphoneIcon },
  ]
})

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

const overview = ref({ total_companies: 0, active_today: 0, mrr: 0, arr: 0, trial_count: 0, conversion_rate: 0, arpu: 0 })
const companies = ref<any[]>([])
const plans = ref<any[]>([])

const planPriceMap: Record<string, number> = { trial: 0, basic: 299, professional: 799, enterprise: 2499 }

const allFeatures: Record<string, boolean> = {
  pos: true, invoices: true, work_orders: true, fleet: true,
  reports: true, api_access: true, zatca: true, booking: true,
}

async function refresh() {
  refreshing.value = true
  await fetchData()
  lastRefresh.value = new Date().toLocaleTimeString('ar-SA')
  refreshing.value = false
}

async function fetchData() {
  try {
    const plansRes = await apiClient.get('/plans')
    plans.value = plansRes.data.data ?? []

    let comp: any[] = []
    try {
      const companiesRes = await apiClient.get('/admin/companies')
      comp = companiesRes.data.data ?? []
      platformMode.value = true
      platformBanner.value =
        'وضع مشغّل منصة: قائمة الشركات من الخادم. الإيرادات المعروضة تقدير من أسعار الباقات وليست دفتر منصة.'
    } catch (e: any) {
      platformMode.value = false
      comp = []
      if (e.response?.status === 403) {
        platformBanner.value =
          'لا يمكن عرض المشتركين من الخادم: أضف بريدك إلى SAAS_PLATFORM_ADMIN_EMAILS في backend/.env ثم أعد التشغيل. لوحة الإدارة للعرض فقط دون بيانات حية.'
      } else {
        platformBanner.value = 'تعذر تحميل قائمة المشتركين. تحقق من الشبكة أو سجلات الخادم.'
      }
    }

    companies.value = comp

    const mrr = comp.reduce((s: number, c: any) => s + (Number(c.monthly_revenue) || planPriceMap[c.plan_slug] || 0), 0)
    const totalCompanies = comp.length
    const activeCompanies = comp.filter((c: any) => c.is_active !== false).length

    overview.value = {
      total_companies: totalCompanies,
      active_today: activeCompanies,
      mrr,
      arr: mrr * 12,
      trial_count: comp.filter((c: any) => c.plan_slug === 'trial').length,
      conversion_rate:
        totalCompanies > 0
          ? Math.round((comp.filter((c: any) => c.plan_slug && c.plan_slug !== 'trial').length / totalCompanies) * 100)
          : 0,
      arpu: totalCompanies > 0 ? Math.round(mrr / totalCompanies) : 0,
    }
  } catch (e) {
    console.error(e)
  }
}

async function loadPlatformBannerAdmin(): Promise<void> {
  if (!platformMode.value) return
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

watch(activeTab, (t) => {
  if (t === 'banner') void loadPlatformBannerAdmin()
  if (t === 'cancellations') void loadCancellationRequests()
  if (t === 'ops' && platformMode.value) void loadOpsSummary()
  if (t === 'audit' && platformMode.value) void loadAuditLogs()
})

async function loadOpsSummary(): Promise<void> {
  if (!platformMode.value) return
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
  if (!platformMode.value) return
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
  if (!platformMode.value) return
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
  if (!platformMode.value) return
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
  const total = Math.max(companies.value.length, 1)
  const slugs = [...new Set(companies.value.map(c => c.plan_slug || '—'))]
  if (slugs.length === 0) {
    return []
  }
  return slugs.map((slug) => {
    const count = companies.value.filter(c => (c.plan_slug || '—') === slug).length
    return {
      name: slug,
      count,
      pct: Math.round((count / total) * 100),
      color: 'bg-purple-500',
    }
  })
})

const revenueByPlan = computed(() => {
  const total = Math.max(overview.value.mrr || 0, 1)
  return plans.value.map((p) => {
    const slug = p.slug
    const count = companies.value.filter(c => c.plan_slug === slug).length
    const revenue = companies.value.reduce(
      (s, c) => s + (c.plan_slug === slug ? Number(c.monthly_revenue) || 0 : 0),
      0,
    )
    return { ...p, count, revenue, pct: Math.round((revenue / total) * 100) }
  })
})

const revenueData = computed(() => {
  const months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']
  const mrr = overview.value.mrr || 0
  const slice = revenueRange.value === '3m' ? 3 : revenueRange.value === '6m' ? 6 : 12
  const per = slice > 0 ? mrr / slice : 0
  return months.slice(0, slice).map((m) => ({
    label: m.substring(0, 3),
    value: per,
  }))
})

const maxRevenue = computed(() => Math.max(...revenueData.value.map(r => r.value), 1))

function viewTenant(_c: any) { activeTab.value = 'tenants' }

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

function exportRevenue() {
  const rows = revenueByPlan.value.map(p => `${p.name},${p.count},${p.revenue}`)
  const csv = '\uFEFFالباقة,المشتركون,الإيراد\n' + rows.join('\n')
  const blob = new Blob([csv], { type: 'text/csv' }); const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = 'revenue.csv'; a.click()
}

const featureLabels: Record<string, string> = {
  pos: 'نقطة البيع', invoices: 'الفواتير', work_orders: 'أوامر العمل',
  fleet: 'إدارة الأسطول', reports: 'التقارير', api_access: 'وصول API',
  zatca: 'ZATCA المرحلة 2', booking: 'نظام الحجوزات',
}
const featureLabel = (f: string) => featureLabels[f] || f
const formatCurrency = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'

// Inline components
const PlanBadge = {
  props: ['plan'],
  template: `<span :class="{'bg-yellow-500/20 text-yellow-400':plan==='trial','bg-blue-500/20 text-blue-400':plan==='basic','bg-purple-500/20 text-purple-400':plan==='professional','bg-emerald-500/20 text-emerald-400':plan==='enterprise'}" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ {'trial':'تجريبي','basic':'أساسي','professional':'احترافي','enterprise':'مؤسسي'}[plan]||plan }}</span>`
}
const StatusDot = {
  props: ['active'],
  template: `<span :class="active ? 'text-emerald-400' : 'text-red-400'" class="text-xs font-medium">{{ active ? '● نشط' : '● موقوف' }}</span>`
}
const AdminKpi = {
  props: ['label', 'value', 'color', 'trend'],
  template: `<div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><div class="mb-1 text-xs text-gray-500">{{ label }}</div><div class="text-xl font-black text-slate-900 dark:text-white">{{ value }}</div><div v-if="trend" :class="String(trend).startsWith('+') ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="mt-1 text-xs">{{ trend }}</div></div>`,
}
const RevenueCard = {
  props: ['title', 'value', 'trend'],
  template: `<div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none p-5"><div class="text-xs text-gray-500 mb-1">{{ title }}</div><div class="text-2xl font-black text-emerald-400">{{ new Intl.NumberFormat('ar-SA',{style:'currency',currency:'SAR',maximumFractionDigits:0}).format(value||0) }}</div><div v-if="trend" class="text-xs text-gray-500 mt-1">{{ trend }}</div></div>`,
}

onMounted(fetchData)
</script>
