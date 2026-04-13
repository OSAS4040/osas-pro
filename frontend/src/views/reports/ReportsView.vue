<template>
  <div class="app-shell-page space-y-6" dir="rtl">
    <!-- Toolbar: hidden when printing -->
    <div class="page-head no-print">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">{{ l('التقارير', 'Reports') }}</h1>
        <p class="page-subtitle">
          {{ l('تحليل شامل لأداء الأعمال — تصدير، طباعة، ومشاركة', 'Comprehensive business analytics — export, print, and share') }}
        </p>
        <div class="flex flex-wrap gap-2 mt-3">
          <RouterLink
            v-if="showBiToolbarLink"
            to="/business-intelligence"
            class="inline-flex items-center gap-1 rounded-lg border border-indigo-200/80 dark:border-indigo-800/60 bg-indigo-50/80 dark:bg-indigo-950/40 px-2.5 py-1 text-[11px] font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-100/90 dark:hover:bg-indigo-900/35"
          >
            {{ l('ذكاء الأعمال', 'Business Intelligence') }}
          </RouterLink>
          <RouterLink
            v-if="showHeatmapToolbarLink"
            to="/bays/heatmap"
            class="inline-flex items-center gap-1 rounded-lg border border-orange-200/80 dark:border-orange-900/50 bg-orange-50/80 dark:bg-orange-950/35 px-2.5 py-1 text-[11px] font-medium text-orange-900 dark:text-orange-200 hover:bg-orange-100/80 dark:hover:bg-orange-900/30"
          >
            {{ l('الخريطة الحرارية', 'Heatmap') }}
          </RouterLink>
          <RouterLink
            v-if="showGlobalOperationsFeedLink"
            to="/operations/global-feed"
            class="inline-flex items-center gap-1 rounded-lg border border-slate-200/80 dark:border-slate-600 bg-slate-50/90 dark:bg-slate-800/60 px-2.5 py-1 text-[11px] font-medium text-slate-800 dark:text-slate-100 hover:bg-slate-100/90 dark:hover:bg-slate-700/50"
          >
            {{ l('مركز العمليات', 'Operations command center') }}
          </RouterLink>
          <RouterLink
            to="/"
            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50/80 dark:bg-slate-800/60 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700/50"
          >
            {{ l('لوحة التحكم', 'Dashboard') }}
          </RouterLink>
        </div>
      </div>
      <div class="page-toolbar">
        <button
          type="button"
          :title="l('مشاركة الرابط مع نفس نطاق التواريخ', 'Share link with same date range')"
          class="px-3 py-2 text-sm rounded-lg border border-violet-300 text-violet-700 dark:border-violet-700 dark:text-violet-300 hover:bg-violet-50 dark:hover:bg-violet-950/40"
          @click="shareReport"
        >
          {{ l('مشاركة', 'Share') }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:text-white hover:bg-gray-50 dark:hover:bg-slate-700"
          @click="printReport"
        >
          {{ l('طباعة', 'Print') }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:text-white hover:bg-gray-50 dark:hover:bg-slate-700"
          @click="exportJSON"
        >
          JSON
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-indigo-300 text-indigo-700 dark:border-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
          @click="exportPNG"
        >
          PNG
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700"
          @click="exportCSV"
        >
          CSV
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm border border-emerald-500 text-emerald-700 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
          @click="exportExcel"
        >
          Excel
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm border border-red-400 text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/30"
          @click="exportPDF"
        >
          PDF
        </button>
        <button type="button" class="btn btn-primary" :disabled="loading" @click="loadAll">
          <span v-if="loading">{{ l('جاري...', 'Loading...') }}</span>
          <span v-else>{{ l('تحديث', 'Refresh') }}</span>
        </button>
      </div>
    </div>

    <div id="reports-print-root" class="print-container space-y-6">
      <p class="hidden print:block text-center text-sm text-gray-600 mb-2">
        {{ l('تقارير الأداء', 'Performance reports') }} — {{ from }} — {{ to }}
      </p>

      <!-- Presets + Date Filter -->
      <div class="card p-4 space-y-3">
        <p class="text-xs font-medium text-gray-600 dark:text-slate-400 no-print">{{ l('فترة سريعة', 'Quick period') }}</p>
        <div class="no-print flex flex-wrap gap-2">
          <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-primary-50 text-primary-800 border border-primary-200 dark:bg-primary-950/50 dark:text-primary-200 dark:border-primary-800" @click="applyPreset('month')">{{ l('هذا الشهر', 'This month') }}</button>
          <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-600" @click="applyPreset('90')">{{ l('آخر 90 يوماً', 'Last 90 days') }}</button>
          <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-600" @click="applyPreset('year')">{{ l('هذه السنة', 'This year') }}</button>
          <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-600" @click="applyPreset('12m')">{{ l('آخر 12 شهراً', 'Last 12 months') }}</button>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
          <div class="min-w-[260px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">{{ l('نطاق التاريخ', 'Date range') }}</label>
            <SmartDatePicker
              mode="range"
              :from-value="from"
              :to-value="to"
              @change="onDateRangeChange"
            />
          </div>
          <div class="min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">{{ l('الفرع', 'Branch') }}</label>
            <select v-model="branchId" class="field-sm">
              <option value="">{{ l('كل الفروع', 'All branches') }}</option>
              <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
          </div>
          <div class="min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">{{ l('المورد', 'Supplier') }}</label>
            <select v-model="supplierId" class="field-sm">
              <option value="">{{ l('كل الموردين', 'All suppliers') }}</option>
              <option v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
            </select>
          </div>
          <button type="button" class="btn btn-primary text-sm" @click="applyDates">{{ l('تطبيق', 'Apply') }}</button>
        </div>
        <p class="text-[11px] text-gray-500 dark:text-slate-500 no-print leading-relaxed">
          {{ l('تُحسب الفواتير حسب ', 'Invoices are calculated by ') }}<strong>{{ l('تاريخ الإصدار', 'issue date') }}</strong>{{ l(' ضمن الفترة (بما فيها يوم «إلى» كاملاً). إذا ظهرت أصفار، جرّب «آخر 12 شهراً» أو وسّع النطاق ليشمل بيانات المحاكاة.', ' within the selected range (including the full "to" day). If values are zero, try "Last 12 months" or expand the range to include demo data.') }}
        </p>
      </div>

      <!-- Smart summary (context-aware) -->
      <div v-if="smartInsights.length" class="no-print space-y-3">
        <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">{{ l('ملخص ذكي للفترة', 'Smart period summary') }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
          <div
            v-for="(ins, idx) in smartInsights"
            :key="idx"
            class="rounded-xl border p-4 flex gap-3 items-start shadow-sm"
            :class="{
              'border-red-200 bg-red-50/90 dark:bg-red-950/25 dark:border-red-900/50': ins.tone === 'danger',
              'border-amber-200 bg-amber-50/90 dark:bg-amber-950/25 dark:border-amber-900/40': ins.tone === 'warn',
              'border-emerald-200 bg-emerald-50/90 dark:bg-emerald-950/20 dark:border-emerald-900/40': ins.tone === 'ok',
              'border-sky-200 bg-sky-50/90 dark:bg-sky-950/20 dark:border-sky-900/40': ins.tone === 'info',
            }"
          >
            <span class="text-2xl flex-shrink-0 leading-none">{{ ins.icon }}</span>
            <div class="min-w-0">
              <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ ins.title }}</p>
              <p class="text-xs text-gray-600 dark:text-slate-400 mt-1 leading-relaxed">{{ ins.body }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200 dark:border-slate-700 no-print">
        <nav class="flex gap-1 overflow-x-auto">
          <button
            v-for="tab in visibleTabs"
            :key="tab.key"
            type="button"
            class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition whitespace-nowrap"
            :class="
              activeTab === tab.key
                ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400'
                : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300'
            "
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- KPI Tab -->
      <div v-if="activeTab === 'kpi'">
        <div v-if="kpiLoading" class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <div v-else-if="kpi" class="space-y-6">
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <KpiCard :title="l('إجمالي المبيعات', 'Total sales')" :value="fmt(kpiDisplay.total_sales)" icon="💰" color="blue" />
            <KpiCard :title="l('عدد الفواتير', 'Invoices count')" :value="String(kpiDisplay.invoice_count || 0)" icon="📄" color="indigo" />
            <KpiCard :title="l('التحصيل', 'Collections')" :value="fmt(kpiDisplay.total_paid)" icon="✅" color="green" />
            <KpiCard :title="l('الضريبة', 'Tax')" :value="fmt(kpiDisplay.total_vat)" icon="🏛" color="purple" />
          </div>
          <div v-if="revenueChartData.labels.length" class="card p-4 no-print">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ l('اتجاه الإيراد اليومي', 'Daily revenue trend') }}</h3>
            <div class="h-56">
              <Line :data="revenueChartData" :options="chartOptions" />
            </div>
          </div>
          <div v-if="(kpi?.daily_revenue ?? []).length" class="table-shell p-4 overflow-x-auto">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ l('جدول الإيراد اليومي', 'Daily revenue table') }}</h3>
            <table class="data-table min-w-[320px]">
              <thead>
                <tr class="text-right border-b dark:border-slate-700">
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('اليوم', 'Day') }}</th>
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الإيراد', 'Revenue') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in kpi.daily_revenue"
                  :key="row.day"
                  class="border-b dark:border-slate-700/50"
                >
                  <td class="py-1.5 font-mono text-xs">{{ row.day }}</td>
                  <td class="py-1.5 font-medium">{{ fmt(row.total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <KpiCard :title="l('إجمالي المبيعات', 'Total sales')" :value="fmt(kpiDisplay.total_sales)" icon="💰" color="blue" />
            <KpiCard :title="l('عدد الفواتير', 'Invoices count')" :value="String(kpiDisplay.invoice_count || 0)" icon="📄" color="indigo" />
            <KpiCard :title="l('أوامر العمل', 'Work orders')" :value="String(kpiDisplay.work_order_count || 0)" icon="🔧" color="orange" />
            <KpiCard :title="l('متوسط الفاتورة', 'Average invoice')" :value="fmt(kpiDisplay.avg_invoice_value)" icon="📊" color="green" />
            <KpiCard :title="l('إجمالي الضريبة', 'Total tax')" :value="fmt(kpiDisplay.total_vat)" icon="🏛" color="purple" />
            <KpiCard :title="l('المدفوعات المستلمة', 'Received payments')" :value="fmt(kpiDisplay.total_paid)" icon="✅" color="green" />
            <KpiCard :title="l('المبالغ المستحقة (مفتوحة)', 'Open due amount')" :value="fmt(kpiDisplay.total_due)" icon="⚠️" color="red" />
            <KpiCard :title="l('عملاء جدد', 'New customers')" :value="String(kpiDisplay.new_customers || 0)" icon="👥" color="teal" />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-slate-600 p-4 bg-gray-50/80 dark:bg-slate-800/50">
              <p class="text-xs text-gray-500 dark:text-slate-400">{{ l('معدل التحصيل (المدفوعات ÷ إيراد الفترة)', 'Collection rate (payments ÷ period revenue)') }}</p>
              <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ kpiDisplay.collection_rate }}٪</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-slate-600 p-4 bg-gray-50/80 dark:bg-slate-800/50">
              <p class="text-xs text-gray-500 dark:text-slate-400">{{ l('إكمال أوامر العمل (مكتمل ÷ إجمالي منشأ في الفترة)', 'Work order completion (completed ÷ total created in period)') }}</p>
              <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ kpiDisplay.wo_completion_rate }}٪</p>
            </div>
          </div>
          <div v-if="activeKpiDictionary.length" class="table-shell p-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">{{ l('قاموس المؤشرات (KPI Dictionary)', 'KPI dictionary') }}</h3>
            <table class="data-table">
              <thead>
                <tr class="text-right border-b dark:border-slate-700">
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المؤشر', 'Metric') }}</th>
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الوصف', 'Description') }}</th>
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الصيغة', 'Formula') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in activeKpiDictionary" :key="row.key" class="border-b dark:border-slate-700/50">
                  <td class="py-2 font-mono text-xs">{{ row.key }}</td>
                  <td class="py-2">{{ row.label }}</td>
                  <td class="py-2 text-xs text-gray-600 dark:text-slate-400 font-mono">{{ row.formula }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-center py-12 text-gray-400">{{ l('لا توجد بيانات', 'No data available') }}</div>
      </div>

      <!-- Sales Tab -->
      <div v-if="activeTab === 'sales'" class="space-y-4">
        <div v-if="salesLoading" class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <div v-if="sales.byBranch && sales.byBranch.length" class="table-shell p-4 space-y-4">
            <div class="no-print h-52 max-w-2xl mx-auto">
              <Bar :data="branchBarData" :options="branchBarOptions" />
            </div>
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('المبيعات حسب الفرع', 'Sales by branch') }}</h3>
            <div class="space-y-3 mb-4">
              <div v-for="b in sales.byBranch" :key="b.branch_id" class="flex items-center gap-3">
                <div class="w-24 text-xs text-gray-600 dark:text-slate-400 text-right truncate">{{ b.branch?.name ?? l('رئيسي', 'Main') }}</div>
                <div class="flex-1 h-5 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-primary-500 rounded-full transition-all duration-700"
                    :style="{ width: maxSales ? (Number(b.total_sales) / maxSales) * 100 + '%' : '0%' }"
                  />
                </div>
                <div class="w-28 text-xs font-semibold text-gray-700 dark:text-slate-300 text-left">{{ fmt(b.total_sales) }}</div>
              </div>
            </div>
            <table class="data-table">
              <thead>
                <tr class="text-right border-b dark:border-slate-700">
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الفرع', 'Branch') }}</th>
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الفواتير', 'Invoices') }}</th>
                  <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المبيعات', 'Sales') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in sales.byBranch" :key="b.branch_id" class="border-b dark:border-slate-700/50">
                  <td class="py-2">{{ b.branch?.name ?? l('رئيسي', 'Main') }}</td>
                  <td class="py-2">{{ b.invoice_count }}</td>
                  <td class="py-2 font-medium text-primary-600">{{ fmt(b.total_sales) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="sales.summary" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <KpiCard :title="l('إجمالي المبيعات', 'Total sales')" :value="fmt(sales.summary.total_sales)" icon="💰" color="blue" />
            <KpiCard :title="l('إجمالي الضريبة', 'Total tax')" :value="fmt(sales.summary.total_tax)" icon="🏛" color="purple" />
            <KpiCard :title="l('الخصومات', 'Discounts')" :value="fmt(sales.summary.total_discount)" icon="🏷" color="orange" />
            <KpiCard :title="l('عدد الفواتير', 'Invoices count')" :value="String(sales.summary.invoice_count ?? sales.summary.count ?? 0)" icon="📄" color="indigo" />
          </div>
        </template>
      </div>

      <!-- Operations -->
      <div v-if="activeTab === 'operations'" class="table-shell p-4">
        <div v-if="opsLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('تقارير الإدارة التشغيلية', 'Operations management reports') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <KpiCard :title="l('إجمالي أوامر العمل', 'Total work orders')" :value="String(operations.summary?.work_orders_total ?? 0)" icon="🛠️" color="blue" />
            <KpiCard :title="l('معدل الإكمال', 'Completion rate')" :value="`${operations.summary?.work_orders_completion_rate ?? 0}%`" icon="✅" color="green" />
            <KpiCard :title="l('مهام مفتوحة', 'Open tasks')" :value="String(operations.summary?.tasks_open ?? 0)" icon="📌" color="indigo" />
            <KpiCard :title="l('مهام متأخرة', 'Overdue tasks')" :value="String(operations.summary?.tasks_overdue ?? 0)" icon="⏰" color="red" />
          </div>
          <table v-if="operations.workload?.length" class="data-table">
            <thead>
              <tr>
                <th>{{ l('الموظف', 'Employee') }}</th>
                <th>{{ l('المهام المفتوحة', 'Open tasks') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in operations.workload" :key="`${w.employee_id ?? 'unassigned'}-${w.employee_name}`">
                <td>{{ w.employee_name }}</td>
                <td>{{ w.open_tasks }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-center text-gray-400 py-6">{{ l('لا توجد بيانات تشغيلية كافية', 'Not enough operational data') }}</p>
        </template>
      </div>

      <!-- Employees -->
      <div v-if="activeTab === 'employees'" class="table-shell p-4">
        <div v-if="empLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('تقارير الموظفين والموارد البشرية', 'Employees and HR reports') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <KpiCard :title="l('إجمالي الموظفين', 'Total employees')" :value="String(employeesReport.summary?.employees_total ?? 0)" icon="👥" color="indigo" />
            <KpiCard :title="l('النشطون', 'Active')" :value="String(employeesReport.summary?.employees_active ?? 0)" icon="🟢" color="green" />
            <KpiCard :title="l('تعيينات جديدة', 'New hires')" :value="String(employeesReport.summary?.new_hires ?? 0)" icon="🆕" color="blue" />
            <KpiCard :title="l('مهام متأخرة', 'Overdue tasks')" :value="String(employeesReport.summary?.overdue_tasks ?? 0)" icon="⚠️" color="red" />
          </div>
          <table v-if="employeesReport.tasks_by_assignee?.length" class="data-table">
            <thead>
              <tr>
                <th>{{ l('الموظف', 'Employee') }}</th>
                <th>{{ l('عدد المهام', 'Task count') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in employeesReport.tasks_by_assignee" :key="`${r.employee_id ?? 'na'}-${r.employee_name}`">
                <td>{{ r.employee_name }}</td>
                <td>{{ r.task_count }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-center text-gray-400 py-6">{{ l('لا توجد بيانات موظفين ضمن النطاق', 'No employee data in this range') }}</p>
        </template>
      </div>

      <!-- Intelligence -->
      <div v-if="activeTab === 'intelligence'" class="table-shell p-4">
        <div v-if="intelLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('ملخص ذكاء الأعمال', 'Business intelligence summary') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <KpiCard :title="l('المبيعات', 'Sales')" :value="fmt(intelligenceDigest.metrics?.sales ?? 0)" icon="💹" color="blue" />
            <KpiCard :title="l('تغير المبيعات', 'Sales change')" :value="`${intelligenceDigest.metrics?.sales_delta_pct ?? 0}%`" icon="📉" color="orange" />
            <KpiCard :title="l('معدل التحصيل', 'Collection rate')" :value="`${intelligenceDigest.metrics?.collection_rate ?? 0}%`" icon="💳" color="green" />
            <KpiCard :title="l('توقع الفترة القادمة', 'Next period forecast')" :value="fmt(intelligenceDigest.forecast?.next_period_sales ?? 0)" icon="🔮" color="indigo" />
          </div>
          <div class="space-y-3 mb-4">
            <div v-for="(a, idx) in (intelligenceDigest.anomalies ?? [])" :key="`anomaly-${idx}`" class="rounded-lg border px-3 py-2"
                 :class="a.severity === 'high' ? 'border-red-200 bg-red-50 dark:bg-red-950/20 dark:border-red-900/50' : 'border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-900/50'"
            >
              <p class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ a.type }}</p>
              <p class="text-xs text-gray-600 dark:text-slate-400 mt-1">{{ a.message }}</p>
            </div>
          </div>
          <div class="rounded-lg border border-primary-200 bg-primary-50/80 dark:bg-primary-950/20 dark:border-primary-800/50 p-4">
            <p class="text-sm font-semibold text-primary-800 dark:text-primary-300 mb-2">{{ l('التوصيات التنفيذية', 'Action recommendations') }}</p>
            <ul class="space-y-1.5 text-sm text-gray-700 dark:text-slate-300">
              <li v-for="(rec, idx) in (intelligenceDigest.recommendations ?? [])" :key="`rec-${idx}`">- {{ rec }}</li>
              <li v-if="!(intelligenceDigest.recommendations ?? []).length" class="text-gray-500">- {{ l('لا توجد توصيات حالياً.', 'No recommendations at the moment.') }}</li>
            </ul>
          </div>
          <div v-if="intelligenceDigest.modern_features" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
            <KpiCard :title="l('معاملات مفتوحة', 'Open communications')" :value="String(intelligenceDigest.modern_features?.communications?.open ?? 0)" icon="📨" color="indigo" />
            <KpiCard :title="l('بانتظار توقيع', 'Pending signature')" :value="String(intelligenceDigest.modern_features?.communications?.signature_pending ?? 0)" icon="✍️" color="orange" />
            <KpiCard :title="l('مهام ذكية متأخرة', 'Overdue smart tasks')" :value="String(intelligenceDigest.modern_features?.smart_tasks?.overdue_open ?? 0)" icon="⏰" color="red" />
            <KpiCard :title="l('متوسط دورة الإغلاق', 'Avg closure cycle')" :value="`${intelligenceDigest.modern_features?.smart_tasks?.avg_cycle_hours ?? 0} ${l('س', 'h')}`" icon="🧠" color="green" />
          </div>
        </template>
      </div>

      <!-- Modern Ops -->
      <div v-if="activeTab === 'modern_ops'" class="table-shell p-4">
        <div v-if="modernLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('التقارير الشاملة المضافة حديثًا', 'Newly added comprehensive reports') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <KpiCard :title="l('إجمالي المعاملات', 'Total communications')" :value="String(communicationsReport.summary?.total ?? 0)" icon="📨" color="blue" />
            <KpiCard :title="l('المعاملات المفتوحة', 'Open communications')" :value="String(communicationsReport.summary?.open ?? 0)" icon="📬" color="indigo" />
            <KpiCard :title="l('بانتظار توقيع', 'Pending signature')" :value="String(communicationsReport.summary?.signature_pending ?? 0)" icon="✍️" color="orange" />
            <KpiCard :title="l('المهام الذكية المتأخرة', 'Overdue smart tasks')" :value="String(smartTasksReport.summary?.overdue_open ?? 0)" icon="⏰" color="red" />
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div class="card p-4">
              <h4 class="text-sm font-semibold mb-3">{{ l('الاتصالات الإدارية حسب الحالة', 'Administrative communications by status') }}</h4>
              <table v-if="(communicationsReport.by_state ?? []).length" class="data-table">
                <thead><tr><th>{{ l('الحالة', 'Status') }}</th><th>{{ l('العدد', 'Count') }}</th></tr></thead>
                <tbody>
                  <tr v-for="r in (communicationsReport.by_state ?? [])" :key="r.state">
                    <td>{{ r.state }}</td>
                    <td>{{ r.count }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-center text-gray-400 py-4">{{ l('لا توجد معاملات بعد', 'No records yet') }}</p>
            </div>

            <div class="card p-4">
              <h4 class="text-sm font-semibold mb-3">{{ l('المهام الذكية حسب الحالة', 'Smart tasks by status') }}</h4>
              <table v-if="(smartTasksReport.by_status ?? []).length" class="data-table">
                <thead><tr><th>{{ l('الحالة', 'Status') }}</th><th>{{ l('العدد', 'Count') }}</th></tr></thead>
                <tbody>
                  <tr v-for="r in (smartTasksReport.by_status ?? [])" :key="r.status">
                    <td>{{ r.status }}</td>
                    <td>{{ r.count }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-center text-gray-400 py-4">{{ l('لا توجد مهام ضمن النطاق', 'No tasks within range') }}</p>
            </div>
          </div>
        </template>
      </div>

      <!-- By Customer -->
      <div v-if="activeTab === 'by_customer'" class="table-shell p-4">
        <div v-if="custLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('مبيعات حسب العميل', 'Sales by customer') }}</h3>
          <div class="space-y-2 mb-4">
            <div v-for="r in byCustomer.slice(0, 8)" :key="r.customer_id" class="flex items-center gap-3">
              <div class="w-32 text-xs truncate text-gray-600 dark:text-slate-400">{{ r.customer?.name ?? l('غير محدد', 'Unspecified') }}</div>
              <div class="flex-1 h-4 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div
                  class="h-full bg-emerald-500 rounded-full"
                  :style="{ width: maxCustomerSales ? (Number(r.total_sales) / maxCustomerSales) * 100 + '%' : '0%' }"
                />
              </div>
              <div class="w-24 text-xs font-semibold text-left">{{ fmt(r.total_sales) }}</div>
            </div>
          </div>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('العميل', 'Customer') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المبيعات', 'Sales') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الفواتير', 'Invoices') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in byCustomer" :key="r.customer_id" class="border-b dark:border-slate-700/50">
                <td class="py-2">{{ r.customer?.name ?? l('غير محدد', 'Unspecified') }}</td>
                <td class="py-2 font-medium text-primary-600">{{ fmt(r.total_sales) }}</td>
                <td class="py-2">{{ r.invoice_count }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <!-- By Product -->
      <div v-if="activeTab === 'by_product'" class="table-shell p-4">
        <div v-if="prodLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('مبيعات حسب المنتج / الخدمة', 'Sales by product / service') }}</h3>
          <div class="space-y-2 mb-4">
            <div v-for="(r, idx) in byProduct.slice(0, 8)" :key="(r.product_name || '') + idx" class="flex items-center gap-3">
              <div class="w-32 text-xs truncate text-gray-600 dark:text-slate-400">{{ r.product_name }}</div>
              <div class="flex-1 h-4 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div
                  class="h-full bg-amber-500 rounded-full"
                  :style="{ width: maxProductSales ? (Number(productRevenue(r)) / maxProductSales) * 100 + '%' : '0%' }"
                />
              </div>
              <div class="w-24 text-xs font-semibold text-left">{{ fmt(productRevenue(r)) }}</div>
            </div>
          </div>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المنتج/الخدمة', 'Product/Service') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الكمية', 'Quantity') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الإيراد', 'Revenue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in byProduct" :key="(r.product_name || '') + '-' + idx" class="border-b dark:border-slate-700/50">
                <td class="py-2">{{ r.product_name }}</td>
                <td class="py-2">{{ productQty(r) }}</td>
                <td class="py-2 font-medium text-primary-600">{{ fmt(productRevenue(r)) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <!-- Overdue -->
      <div v-if="activeTab === 'cashflow'" class="table-shell p-4">
        <div v-if="cashflowLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('تقرير التدفق النقدي', 'Cashflow report') }}</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <KpiCard :title="l('إجمالي الداخل', 'Total incoming')" :value="fmt(cashflow.summary?.incoming_total)" icon="⬇️" color="green" />
            <KpiCard :title="l('إجمالي الخارج', 'Total outgoing')" :value="fmt(cashflow.summary?.outgoing_total)" icon="⬆️" color="red" />
            <KpiCard :title="l('الصافي', 'Net')" :value="fmt(cashflow.summary?.net_total)" icon="💹" color="indigo" />
          </div>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('اليوم', 'Day') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('داخل', 'Incoming') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('خارج', 'Outgoing') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('صافي', 'Net') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in (cashflow.daily ?? [])" :key="r.day" class="border-b dark:border-slate-700/50">
                <td class="py-2 font-mono text-xs">{{ r.day }}</td>
                <td class="py-2 text-green-700">{{ fmt(r.incoming) }}</td>
                <td class="py-2 text-red-700">{{ fmt(r.outgoing) }}</td>
                <td class="py-2 font-semibold">{{ fmt(r.net) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <div v-if="activeTab === 'purchases'" class="table-shell p-4">
        <div v-if="purchasesLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('تقرير المشتريات', 'Purchases report') }}</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <KpiCard :title="l('عدد أوامر الشراء', 'Purchase orders count')" :value="String(purchases.summary?.count ?? 0)" icon="🧾" color="indigo" />
            <KpiCard :title="l('إجمالي المشتريات', 'Total purchases')" :value="fmt(purchases.summary?.total)" icon="📦" color="blue" />
          </div>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المورد', 'Supplier') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الطلبات', 'Orders') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الإجمالي', 'Total') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in (purchases.by_supplier ?? [])" :key="`${r.supplier_id ?? 'na'}-${idx}`" class="border-b dark:border-slate-700/50">
                <td class="py-2">{{ r.supplier_name }}</td>
                <td class="py-2">{{ r.orders_count }}</td>
                <td class="py-2 font-semibold text-primary-600">{{ fmt(r.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <div v-if="activeTab === 'aging'" class="table-shell p-4">
        <div v-if="agingLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('أعمار الذمم المدينة', 'Receivables aging') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <KpiCard :title="l('حالي', 'Current')" :value="fmt(aging.buckets?.current)" icon="🟢" color="green" />
            <KpiCard :title="l('1 - 30 يوم', '1 - 30 days')" :value="fmt(aging.buckets?.['1_30'])" icon="🟡" color="orange" />
            <KpiCard :title="l('31 - 60 يوم', '31 - 60 days')" :value="fmt(aging.buckets?.['31_60'])" icon="🟠" color="orange" />
            <KpiCard :title="l('61 - 90 يوم', '61 - 90 days')" :value="fmt(aging.buckets?.['61_90'])" icon="🔴" color="red" />
            <KpiCard :title="l('+90 يوم', '+90 days')" :value="fmt(aging.buckets?.['90_plus'])" icon="🚨" color="red" />
          </div>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الفاتورة', 'Invoice') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('العميل', 'Customer') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المبلغ', 'Amount') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('أيام التأخر', 'Overdue days') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in (aging.lines ?? [])" :key="`${r.invoice_number ?? 'inv'}-${idx}`" class="border-b dark:border-slate-700/50">
                <td class="py-2 font-mono text-xs">{{ r.invoice_number }}</td>
                <td class="py-2">{{ r.customer_name }}</td>
                <td class="py-2 font-semibold text-red-700">{{ fmt(r.due_amount) }}</td>
                <td class="py-2">{{ r.days_overdue }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <!-- Overdue -->
      <div v-if="activeTab === 'overdue'" class="table-shell p-4">
        <div v-if="overdueLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('الفواتير المتأخرة', 'Overdue invoices') }}</h3>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الفاتورة', 'Invoice') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('العميل', 'Customer') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المبلغ', 'Amount') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('التأخر', 'Overdue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in overdue" :key="r.id" class="border-b dark:border-slate-700/50">
                <td class="py-2 font-mono text-xs">{{ r.invoice_number }}</td>
                <td class="py-2">{{ r.customer?.name ?? '-' }}</td>
                <td class="py-2 font-medium text-red-600">{{ fmt(r.due_amount) }}</td>
                <td class="py-2">
                  <span
                    :class="[
                      'px-2 py-0.5 rounded-full text-xs',
                      Number(r.days_overdue) > 30 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700',
                    ]"
                  >
                    {{ r.days_overdue }} يوم
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!overdue.length" class="text-center text-gray-400 py-6">{{ l('لا توجد فواتير متأخرة', 'No overdue invoices') }}</p>
        </template>
      </div>

      <!-- Inventory -->
      <div v-if="activeTab === 'inventory'" class="table-shell p-4">
        <div v-if="invLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('تقرير المخزون', 'Inventory report') }}</h3>
          <table class="data-table">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المنتج', 'Product') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الفرع', 'Branch') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('المتاح', 'Available') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('نقطة الطلب', 'Reorder point') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الحالة', 'Status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in inventory" :key="r.id ?? idx" class="border-b dark:border-slate-700/50">
                <td class="py-2">{{ r.product_name ?? r.product?.name ?? r.product_id }}</td>
                <td class="py-2">{{ r.branch_name ?? '—' }}</td>
                <td class="py-2">{{ r.available_quantity ?? r.quantity }}</td>
                <td class="py-2">{{ r.reorder_point ?? '—' }}</td>
                <td class="py-2">
                  <span
                    :class="[
                      'px-2 py-0.5 rounded-full text-xs',
                      Number(r.available_quantity ?? r.quantity) <= Number(r.reorder_point ?? 0)
                        ? 'bg-red-100 text-red-700'
                        : 'bg-green-100 text-green-700',
                    ]"
                  >
                    {{
                      Number(r.available_quantity ?? r.quantity) <= Number(r.reorder_point ?? 0) ? 'منخفض' : 'جيد'
                    }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!inventory.length" class="text-center text-gray-400 py-6">{{ l('لا توجد بيانات', 'No data available') }}</p>
        </template>
      </div>

      <!-- VAT -->
      <div v-if="activeTab === 'vat'" class="card p-4">
        <div v-if="vatLoading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <template v-else>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">{{ l('تقرير ضريبة القيمة المضافة', 'VAT report') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <KpiCard :title="l('إجمالي الضريبة المحصلة', 'Total collected tax')" :value="fmt(vat.total_tax)" icon="🏛" color="purple" />
            <KpiCard :title="l('صافي المبيعات', 'Net sales')" :value="fmt(vat.net_sales)" icon="💰" color="blue" />
            <KpiCard :title="l('إجمالي المبيعات شامل', 'Gross sales total')" :value="fmt(vat.gross_sales)" icon="📊" color="indigo" />
          </div>
          <table v-if="vat.by_rate && vat.by_rate.length" class="w-full text-sm">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('نسبة الضريبة', 'Tax rate') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الوعاء', 'Tax base') }}</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">{{ l('الضريبة', 'Tax') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in vat.by_rate" :key="String(r.tax_rate)" class="border-b dark:border-slate-700/50">
                <td class="py-2">{{ r.tax_rate }}%</td>
                <td class="py-2">{{ fmt(r.taxable_amount) }}</td>
                <td class="py-2 font-medium text-purple-600">{{ fmt(r.tax_amount) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-xs text-gray-500 dark:text-slate-400">{{ l('التفصيل حسب نسبة الضريبة غير متوفر بعد — يُعرض الإجمالي أعلاه.', 'Breakdown by tax rate is not available yet — total is shown above.') }}</p>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler,
} from 'chart.js'
import { Line, Bar } from 'vue-chartjs'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { featureFlags } from '@/config/featureFlags'
import { canAccessStaffBusinessIntelligence, tenantSectionOpen } from '@/config/staffFeatureGate'
import KpiCard from '@/components/KpiCard.vue'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { useLocale } from '@/composables/useLocale'
import { printDocument, ensurePrintFontsReady } from '@/composables/useAppPrint'
import { PDF_EXPORT_FAIL_AR } from '@/constants/pdfExportMessages'

ChartJS.register(Title, Tooltip, Legend, LineElement, BarElement, LinearScale, CategoryScale, PointElement, Filler)

const toast = useToast()
const route = useRoute()
const router = useRouter()
const api = useApi()
const auth = useAuthStore()
const biz = useBusinessProfileStore()
const locale = useLocale()

const showBiToolbarLink = computed(() => {
  void biz.loaded
  void biz.effectiveFeatureMatrix
  return canAccessStaffBusinessIntelligence({
    buildFlagOn: featureFlags.intelligenceCommandCenter,
    isOwner: auth.isOwner,
    isEnabled: (k) => biz.isEnabled(k),
  })
})
const showHeatmapToolbarLink = computed(() => {
  void biz.loaded
  void biz.effectiveFeatureMatrix
  return tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'operations')
})
const showGlobalOperationsFeedLink = computed(
  () => auth.hasPermission('reports.view') && auth.hasPermission('reports.operations.view'),
)
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)
const REPORTS_FILTERS_KEY = 'reports_filters_v1'
let filterDebounceTimer: ReturnType<typeof setTimeout> | null = null

const to = ref(new Date().toISOString().split('T')[0])
const from = ref('')
{
  const d = new Date()
  d.setFullYear(d.getFullYear() - 1)
  from.value = d.toISOString().split('T')[0]
}

const activeTab = ref('kpi')
const branchId = ref('')
const supplierId = ref('')
const branches = ref<any[]>([])
const suppliers = ref<any[]>([])

const tabs = computed(() => [
  { key: 'kpi', label: l('المؤشرات الرئيسية', 'Key metrics') },
  { key: 'sales', label: l('المبيعات', 'Sales') },
  { key: 'operations', label: l('تشغيلي', 'Operations'), permission: 'reports.operations.view' },
  { key: 'modern_ops', label: l('اتصالات + مهام ذكية', 'Communications + Smart Tasks'), permission: 'reports.operations.view' },
  { key: 'employees', label: l('الموظفين', 'Employees'), permission: 'reports.employees.view' },
  { key: 'intelligence', label: l('ذكاء الأعمال', 'Business intelligence'), permission: 'reports.intelligence.view' },
  { key: 'by_customer', label: l('حسب العميل', 'By customer') },
  { key: 'by_product', label: l('حسب المنتج', 'By product') },
  { key: 'cashflow', label: l('التدفق النقدي', 'Cashflow'), permission: 'reports.financial.view' },
  { key: 'purchases', label: l('المشتريات', 'Purchases'), permission: 'reports.financial.view' },
  { key: 'aging', label: l('أعمار الذمم', 'Receivables aging'), permission: 'reports.financial.view' },
  { key: 'vat', label: l('الضريبة', 'VAT') },
  { key: 'overdue', label: l('المتأخرات', 'Overdue') },
  { key: 'inventory', label: l('المخزون', 'Inventory') },
])
const visibleTabs = computed(() =>
  tabs.value.filter((t: any) => {
    if (!t.permission) return true
    return auth.hasPermission(t.permission)
  }),
)

const loading = ref(false)
const kpiLoading = ref(false)
const salesLoading = ref(false)
const custLoading = ref(false)
const prodLoading = ref(false)
const overdueLoading = ref(false)
const invLoading = ref(false)
const vatLoading = ref(false)
const cashflowLoading = ref(false)
const purchasesLoading = ref(false)
const agingLoading = ref(false)
const opsLoading = ref(false)
const empLoading = ref(false)
const intelLoading = ref(false)
const modernLoading = ref(false)

const kpi = ref<Record<string, any> | null>(null)
const sales = ref<Record<string, any>>({})
const byCustomer = ref<any[]>([])
const byProduct = ref<any[]>([])
const overdue = ref<any[]>([])
const inventory = ref<any[]>([])
const vat = ref<Record<string, any>>({})
const cashflow = ref<Record<string, any>>({})
const purchases = ref<Record<string, any>>({})
const aging = ref<Record<string, any>>({})
const operations = ref<Record<string, any>>({})
const employeesReport = ref<Record<string, any>>({})
const intelligenceDigest = ref<Record<string, any>>({})
const kpiDictionary = ref<Record<string, any>>({})
const communicationsReport = ref<Record<string, any>>({})
const smartTasksReport = ref<Record<string, any>>({})

/** يمنع إعادة تحميل مزدوجة عند تحديث الـ URL من الواجهة (router.replace) */
const syncingRouteFromUi = ref(false)

const kpiDisplay = computed(() => {
  const k = kpi.value ?? {}
  return {
    total_sales: k.total_sales ?? k.total_revenue ?? 0,
    invoice_count: k.invoice_count ?? 0,
    work_order_count: k.work_order_count ?? k.wo_total ?? 0,
    avg_invoice_value: k.avg_invoice_value ?? 0,
    total_vat: k.total_vat ?? 0,
    total_paid: k.total_paid ?? k.total_collected ?? 0,
    total_due: k.total_due ?? 0,
    new_customers: k.new_customers ?? 0,
    collection_rate: k.collection_rate ?? 0,
    wo_completion_rate: k.wo_completion_rate ?? 0,
  }
})

type InsightTone = 'info' | 'warn' | 'danger' | 'ok'

const smartInsights = computed((): { icon: string; title: string; body: string; tone: InsightTone }[] => {
  const out: { icon: string; title: string; body: string; tone: InsightTone }[] = []
  const k = kpiDisplay.value
  if (kpi.value) {
    if (Number(k.collection_rate) < 55 && Number(k.total_sales) > 0) {
      out.push({
        icon: '⚠️',
        title: l('تحصيل أقل من المستهدف', 'Collections below target'),
        body: l(
          `معدل التحصيل ${k.collection_rate}% مقارنةً بالإيراد — راجع تبويب «المتأخرات» وخطط المطالبة.`,
          `Collection rate is ${k.collection_rate}% compared to revenue — review the Overdue tab and follow-up plan.`,
        ),
        tone: 'warn',
      })
    }
    if (Number(k.new_customers) > 0) {
      out.push({
        icon: '👥',
        title: l('نمو في قاعدة العملاء', 'Customer base growth'),
        body: l(
          `تم تسجيل ${k.new_customers} عميلاً جديداً في الفترة — فرصة لمتابعة ولاء أعلى.`,
          `${k.new_customers} new customers were registered in this period — opportunity for better retention.`,
        ),
        tone: 'ok',
      })
    }
    if (Number(k.work_order_count) > 0 && Number(k.wo_completion_rate) >= 65) {
      out.push({
        icon: '🔧',
        title: l('أداء جيد في أوامر العمل', 'Strong work-order performance'),
        body: l(
          `معدل الإكمال ${k.wo_completion_rate}% على ${k.work_order_count} أمراً في الفترة.`,
          `Completion rate is ${k.wo_completion_rate}% across ${k.work_order_count} work orders in this period.`,
        ),
        tone: 'ok',
      })
    }
    if (Number(k.total_due) > 0 && Number(k.collection_rate) >= 75) {
      out.push({
        icon: '💰',
        title: l('ذمم مفتوحة تحت السيطرة', 'Open receivables under control'),
        body: l(
          `المستحقات لا تزال موجودة لكن التحصيل قوي (${k.collection_rate}%) — تابع الفواتير المعلقة.`,
          `Receivables still exist, but collections are strong (${k.collection_rate}%) — keep tracking pending invoices.`,
        ),
        tone: 'info',
      })
    }
  }
  if (overdue.value.length > 3) {
    out.push({
      icon: '📉',
      title: l('ضغط على التحصيل', 'Collections pressure'),
      body: l(
        `${overdue.value.length} فاتورة متأخرة في النطاق — أولوية للمكالمات والتذكير.`,
        `${overdue.value.length} overdue invoices in this range — prioritize reminders and follow-up calls.`,
      ),
      tone: 'danger',
    })
  }
  const lowStock = inventory.value.filter(
    (r: any) => Number(r.available_quantity ?? r.quantity) <= Number(r.reorder_point ?? 0),
  ).length
  if (lowStock > 0) {
    out.push({
      icon: '📦',
      title: l('مخزون يحتاج تعبئة', 'Inventory needs replenishment'),
      body: l(
        `${lowStock} صنف عند أو تحت نقطة إعادة الطلب — راجع تبويب المخزون.`,
        `${lowStock} item(s) are at or below reorder point — review the Inventory tab.`,
      ),
      tone: 'warn',
    })
  }
  if (byCustomer.value.length > 8) {
    out.push({
      icon: '📊',
      title: l('تنوع العملاء', 'Customer diversity'),
      body: l(
        'لديك أكثر من 8 عملاء نشطين في التقرير — فكّر في برامج ولاء للأعلى إنفاقاً.',
        'You have more than 8 active customers in this report — consider loyalty offers for top spenders.',
      ),
      tone: 'info',
    })
  }
  return out.slice(0, 6)
})

const revenueChartData = computed(() => {
  const series = (kpi.value?.daily_revenue ?? []) as { day: string; total: number }[]
  return {
    labels: series.map((s) => s.day),
    datasets: [
      {
        label: l('إيراد يومي (ر.س.)', 'Daily revenue (SAR)'),
        data: series.map((s) => s.total),
        borderColor: 'rgb(59, 130, 246)',
        backgroundColor: 'rgba(59, 130, 246, 0.12)',
        fill: true,
        tension: 0.35,
      },
    ],
  }
})

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true, position: 'bottom' as const },
  },
  scales: {
    x: { ticks: { maxRotation: 45, minRotation: 0 } },
    y: { beginAtZero: true },
  },
}))

const branchBarData = computed(() => {
  const rows = (sales.value.byBranch ?? []) as any[]
  return {
    labels: rows.map((b) => b.branch?.name ?? l('رئيسي', 'Main')),
    datasets: [
      {
        label: l('المبيعات (ر.س.)', 'Sales (SAR)'),
        data: rows.map((b) => Number(b.total_sales) || 0),
        backgroundColor: 'rgba(99, 102, 241, 0.55)',
        borderColor: 'rgb(79, 70, 229)',
        borderWidth: 1,
      },
    ],
  }
})

const branchBarOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { maxRotation: 45 } },
    y: { beginAtZero: true },
  },
}))

const maxSales = computed(() =>
  Math.max(1, ...((sales.value.byBranch ?? []).map((b: any) => Number(b.total_sales) || 0))),
)
const maxCustomerSales = computed(() => Math.max(1, ...byCustomer.value.map((r: any) => Number(r.total_sales) || 0)))
const maxProductSales = computed(() => Math.max(1, ...byProduct.value.map((r: any) => Number(productRevenue(r)) || 0)))
const activeKpiDictionary = computed(() => {
  const map: Record<string, any[]> = {
    kpi: kpiDictionary.value.financial ?? [],
    operations: kpiDictionary.value.operational ?? [],
    employees: kpiDictionary.value.employees ?? [],
    intelligence: kpiDictionary.value.intelligence ?? [],
  }
  return map[activeTab.value] ?? (kpiDictionary.value.financial ?? [])
})

function productRevenue(r: any): number {
  return Number(r.total_revenue ?? r.total_sales ?? 0)
}

function productQty(r: any): number | string {
  return r.total_quantity ?? r.total_qty ?? '—'
}

const fmt = (v: any) => {
  const n = parseFloat(v) || 0
  return n.toLocaleString(locale.lang.value === 'ar' ? 'ar-SA' : 'en-US', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 })
}

const params = () => ({
  from: from.value,
  to: to.value,
  branch_id: branchId.value || undefined,
  supplier_id: supplierId.value || undefined,
})

function applyPreset(p: string) {
  const end = new Date()
  const toStr = end.toISOString().split('T')[0]
  if (p === 'month') {
    const s = new Date(end.getFullYear(), end.getMonth(), 1)
    from.value = s.toISOString().split('T')[0]
  } else if (p === '90') {
    const s = new Date()
    s.setDate(s.getDate() - 90)
    from.value = s.toISOString().split('T')[0]
  } else if (p === 'year') {
    from.value = `${end.getFullYear()}-01-01`
  } else if (p === '12m') {
    const s = new Date()
    s.setFullYear(s.getFullYear() - 1)
    from.value = s.toISOString().split('T')[0]
  }
  to.value = toStr
  pushQueryToUrl()
  loadAll()
}

function applyDates() {
  pushQueryToUrl()
  loadAll()
}

function onDateRangeChange(val: { from: string; to: string }) {
  from.value = val.from
  to.value = val.to
  scheduleAutoApply()
}

function scheduleAutoApply() {
  if (filterDebounceTimer) clearTimeout(filterDebounceTimer)
  filterDebounceTimer = setTimeout(() => {
    applyDates()
  }, 350)
}

function persistReportFilters() {
  try {
    localStorage.setItem(
      REPORTS_FILTERS_KEY,
      JSON.stringify({
        from: from.value,
        to: to.value,
        branch_id: branchId.value,
        supplier_id: supplierId.value,
        tab: activeTab.value,
      }),
    )
  } catch {
    // no-op
  }
}

function restoreReportFilters() {
  try {
    const raw = localStorage.getItem(REPORTS_FILTERS_KEY)
    if (!raw) return
    const parsed = JSON.parse(raw) as {
      from?: string
      to?: string
      branch_id?: string
      supplier_id?: string
      tab?: string
    }
    if (typeof parsed.from === 'string' && parsed.from) from.value = parsed.from
    if (typeof parsed.to === 'string' && parsed.to) to.value = parsed.to
    if (typeof parsed.branch_id === 'string') branchId.value = parsed.branch_id
    if (typeof parsed.supplier_id === 'string') supplierId.value = parsed.supplier_id
    if (typeof parsed.tab === 'string' && parsed.tab) activeTab.value = parsed.tab
  } catch {
    // no-op
  }
}

function pushQueryToUrl() {
  syncingRouteFromUi.value = true
  router
    .replace({
      query: {
        from: from.value,
        to: to.value,
        tab: activeTab.value,
        branch_id: branchId.value || undefined,
        supplier_id: supplierId.value || undefined,
      },
    })
    .catch(() => {})
    .finally(() => {
      setTimeout(() => {
        syncingRouteFromUi.value = false
      }, 120)
    })
}

async function loadAll() {
  loading.value = true
  await Promise.allSettled([
    loadKpi(),
    loadSales(),
    loadOperations(),
    loadEmployees(),
    loadIntelligenceDigest(),
    loadModernReports(),
    loadCustomer(),
    loadProduct(),
    loadOverdue(),
    loadInventory(),
    loadVat(),
    loadCashflow(),
    loadPurchases(),
    loadAging(),
    loadKpiDictionary(),
  ])
  loading.value = false
}

async function loadKpi() {
  kpiLoading.value = true
  try {
    const r = await api.get('/reports/kpi', params())
    kpi.value = r.data ?? null
  } catch {
    kpi.value = null
  } finally {
    kpiLoading.value = false
  }
}

async function loadSales() {
  salesLoading.value = true
  try {
    const r = await api.get('/reports/sales', params())
    sales.value = r.data ?? {}
  } catch {
    sales.value = {}
  } finally {
    salesLoading.value = false
  }
}

async function loadCustomer() {
  custLoading.value = true
  try {
    const r = await api.get('/reports/sales-by-customer', params())
    byCustomer.value = Array.isArray(r.data) ? r.data : []
  } catch {
    byCustomer.value = []
  } finally {
    custLoading.value = false
  }
}

async function loadProduct() {
  prodLoading.value = true
  try {
    const r = await api.get('/reports/sales-by-product', params())
    byProduct.value = Array.isArray(r.data) ? r.data : []
  } catch {
    byProduct.value = []
  } finally {
    prodLoading.value = false
  }
}

async function loadOverdue() {
  overdueLoading.value = true
  try {
    const r = await api.get('/reports/overdue-receivables', params())
    const raw = r.data
    const rows = raw?.data ?? raw ?? []
    overdue.value = Array.isArray(rows) ? rows : []
  } catch {
    overdue.value = []
  } finally {
    overdueLoading.value = false
  }
}

async function loadInventory() {
  invLoading.value = true
  try {
    const r = await api.get('/reports/inventory', params())
    const pag = r.data
    const rows = Array.isArray(pag?.data) ? pag.data : Array.isArray(pag) ? pag : []
    inventory.value = rows
  } catch {
    inventory.value = []
  } finally {
    invLoading.value = false
  }
}

async function loadVat() {
  vatLoading.value = true
  try {
    const r = await api.get('/reports/vat', params())
    vat.value = r.data ?? {}
  } catch {
    vat.value = {}
  } finally {
    vatLoading.value = false
  }
}

async function loadCashflow() {
  cashflowLoading.value = true
  try {
    const r = await api.get('/reports/cash-flow', params())
    cashflow.value = r.data ?? {}
  } catch {
    cashflow.value = {}
  } finally {
    cashflowLoading.value = false
  }
}

async function loadPurchases() {
  purchasesLoading.value = true
  try {
    const r = await api.get('/reports/purchases', params())
    purchases.value = r.data ?? {}
  } catch {
    purchases.value = {}
  } finally {
    purchasesLoading.value = false
  }
}

async function loadAging() {
  agingLoading.value = true
  try {
    const r = await api.get('/reports/receivables-aging', params())
    aging.value = r.data ?? {}
  } catch {
    aging.value = {}
  } finally {
    agingLoading.value = false
  }
}

async function loadOperations() {
  opsLoading.value = true
  try {
    const r = await api.get('/reports/operations', params())
    operations.value = r.data ?? {}
  } catch {
    operations.value = {}
  } finally {
    opsLoading.value = false
  }
}

async function loadEmployees() {
  empLoading.value = true
  try {
    const r = await api.get('/reports/employees', params())
    employeesReport.value = r.data ?? {}
  } catch {
    employeesReport.value = {}
  } finally {
    empLoading.value = false
  }
}

async function loadIntelligenceDigest() {
  intelLoading.value = true
  try {
    const r = await api.get('/reports/intelligence-digest', params())
    intelligenceDigest.value = r.data ?? {}
  } catch {
    intelligenceDigest.value = {}
  } finally {
    intelLoading.value = false
  }
}

async function loadModernReports() {
  modernLoading.value = true
  try {
    const [comm, tasks] = await Promise.all([
      api.get('/reports/communications', params()),
      api.get('/reports/smart-tasks', params()),
    ])
    communicationsReport.value = comm.data ?? {}
    smartTasksReport.value = tasks.data ?? {}
  } catch {
    communicationsReport.value = {}
    smartTasksReport.value = {}
  } finally {
    modernLoading.value = false
  }
}

async function loadKpiDictionary() {
  try {
    const r = await api.get('/reports/kpi-dictionary', params())
    kpiDictionary.value = r.data ?? {}
  } catch {
    kpiDictionary.value = {}
  }
}

function tabRows(): any[] {
  const map: Record<string, any[]> = {
    kpi: kpi.value ? [kpiDisplay.value] : [],
    sales: sales.value.byBranch ?? [],
    operations: operations.value.workload ?? [],
    employees: employeesReport.value.tasks_by_assignee ?? [],
    modern_ops: [
      ...(communicationsReport.value.by_state ?? []),
      ...(smartTasksReport.value.by_status ?? []),
    ],
    intelligence: intelligenceDigest.value.anomalies ?? [],
    by_customer: byCustomer.value,
    by_product: byProduct.value,
    cashflow: cashflow.value?.daily ?? [],
    purchases: purchases.value?.by_supplier ?? [],
    aging: aging.value?.lines ?? [],
    overdue: overdue.value,
    inventory: inventory.value,
    vat: vat.value?.by_rate?.length ? vat.value.by_rate : vat.value ? [vat.value] : [],
  }
  return map[activeTab.value] ?? []
}

function exportCSV() {
  const rows = tabRows()
  if (!rows.length) {
    toast.warning('تنبيه', 'لا توجد بيانات للتصدير في هذا التبويب')
    return
  }
  const keys = Object.keys(rows[0])
  const csv = [keys.join(','), ...rows.map((r: any) => keys.map((k) => `"${String(r[k] ?? '').replace(/"/g, '""')}"`).join(','))].join('\n')
  const a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }))
  a.download = `report_${activeTab.value}_${from.value}.csv`
  a.click()
}

async function exportExcel() {
  try {
    const rows = tabRows()
    if (!rows.length) {
      toast.warning('تنبيه', 'لا توجد بيانات للتصدير')
      return
    }
    const { downloadExcelFromRows } = await import('@/utils/exportExcel')
    await downloadExcelFromRows(rows, 'تقرير', `report_${activeTab.value}_${from.value}.xlsx`)
  } catch {
    toast.error('خطأ', 'تعذّر تصدير Excel')
  }
}

async function exportPDF() {
  let captureNode: HTMLElement | null = null
  try {
    const source = document.getElementById('reports-print-root')
    if (!source) {
      toast.error('تصدير PDF', PDF_EXPORT_FAIL_AR)
      return
    }

    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    // Use rendered DOM snapshot to preserve Arabic text.
    captureNode = source.cloneNode(true) as HTMLElement
    captureNode.style.display = 'block'
    captureNode.style.position = 'fixed'
    captureNode.style.left = '-12000px'
    captureNode.style.top = '0'
    captureNode.style.zIndex = '-1'
    captureNode.style.width = `${source.getBoundingClientRect().width || 1024}px`
    document.body.appendChild(captureNode)

    await ensurePrintFontsReady()

    const canvas = await html2canvas(captureNode, {
      scale: 2,
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#ffffff',
      logging: false,
    })

    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const pageW = pdf.internal.pageSize.getWidth()
    const pageH = pdf.internal.pageSize.getHeight()
    const imgW = pageW
    const imgH = (canvas.height * imgW) / canvas.width
    const imgData = canvas.toDataURL('image/png')

    let remaining = imgH
    let y = 0
    pdf.addImage(imgData, 'PNG', 0, y, imgW, imgH)
    remaining -= pageH
    while (remaining > 0) {
      y = remaining - imgH
      pdf.addPage()
      pdf.addImage(imgData, 'PNG', 0, y, imgW, imgH)
      remaining -= pageH
    }

    pdf.save(`report_${activeTab.value}_${from.value}.pdf`)
    toast.success('تم التصدير', 'تم تنزيل ملف PDF.')
  } catch {
    toast.error('تصدير PDF', PDF_EXPORT_FAIL_AR)
  } finally {
    if (captureNode && captureNode.parentNode) {
      captureNode.parentNode.removeChild(captureNode)
    }
  }
}

function exportJSON() {
  const payload = {
    generated_at: new Date().toISOString(),
    from: from.value,
    to: to.value,
    tab: activeTab.value,
    kpi: kpi.value,
    sales: sales.value,
    operations: operations.value,
    employees: employeesReport.value,
    intelligence: intelligenceDigest.value,
    by_customer: byCustomer.value,
    by_product: byProduct.value,
    overdue: overdue.value,
    inventory: inventory.value,
    vat: vat.value,
  }
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `reports_snapshot_${from.value}_${to.value}.json`
  a.click()
  toast.success('تم', 'تم تصدير ملف JSON.')
}

async function exportPNG() {
  try {
    const el = document.getElementById('reports-print-root')
    if (!el) return
    await ensurePrintFontsReady()
    const { default: html2canvas } = await import('html2canvas')
    const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    canvas.toBlob((blob) => {
      if (!blob) return
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `reports_${activeTab.value}_${from.value}.png`
      a.click()
      toast.success('تم', 'تم تصدير صورة PNG.')
    })
  } catch {
    toast.error('خطأ', 'تعذّر إنشاء PNG')
  }
}

async function printReport() {
  await printDocument({ rootSelector: '#reports-print-root' })
}

function shareReport() {
  const url = `${window.location.origin}/reports?from=${encodeURIComponent(from.value)}&to=${encodeURIComponent(to.value)}&tab=${encodeURIComponent(activeTab.value)}`
  if (navigator.share) {
    navigator
      .share({ title: l('تقارير المنصة', 'Platform reports'), text: l('فتح التقارير', 'Open reports'), url })
      .catch(() => copyUrl(url))
  } else {
    copyUrl(url)
  }
}

function copyUrl(url: string) {
  navigator.clipboard.writeText(url).then(
    () => toast.success('تم النسخ', 'رابط التقرير مع الفترة والتبويب'),
    () => toast.error('تعذّر النسخ', url.slice(0, 80)),
  )
}

function applyQueryFromRoute(): boolean {
  const q = route.query
  let changed = false
  if (typeof q.from === 'string' && q.from && q.from !== from.value) {
    from.value = q.from
    changed = true
  }
  if (typeof q.to === 'string' && q.to && q.to !== to.value) {
    to.value = q.to
    changed = true
  }
  if (typeof q.tab === 'string' && q.tab && visibleTabs.value.some((t: any) => t.key === q.tab) && q.tab !== activeTab.value) {
    activeTab.value = q.tab
    changed = true
  }
  if (typeof q.branch_id === 'string' && q.branch_id !== branchId.value) {
    branchId.value = q.branch_id
    changed = true
  }
  if (typeof q.supplier_id === 'string' && q.supplier_id !== supplierId.value) {
    supplierId.value = q.supplier_id
    changed = true
  }
  return changed
}

onMounted(() => {
  if (auth.isStaff && auth.user?.company_id) {
    biz.load().catch(() => {})
  }
  if (!visibleTabs.value.some((t: any) => t.key === activeTab.value)) {
    activeTab.value = visibleTabs.value[0]?.key ?? 'kpi'
  }
  restoreReportFilters()
  applyQueryFromRoute()
  loadFilterOptions()
  loadAll()
})

async function loadFilterOptions() {
  try {
    const [b, s] = await Promise.all([
      api.get('/branches', { per_page: 200 }),
      api.get('/suppliers', { per_page: 200 }),
    ])
    const bRows = b.data?.data?.data ?? b.data?.data ?? []
    const sRows = s.data?.data?.data ?? s.data?.data ?? []
    branches.value = Array.isArray(bRows) ? bRows : []
    suppliers.value = Array.isArray(sRows) ? sRows : []
  } catch {
    branches.value = []
    suppliers.value = []
  }
}

watch(activeTab, () => {
  pushQueryToUrl()
  persistReportFilters()
})

watch(visibleTabs, (tabsNow) => {
  if (!tabsNow.some((t: any) => t.key === activeTab.value)) {
    activeTab.value = tabsNow[0]?.key ?? 'kpi'
  }
})

watch(
  () => [route.query.from, route.query.to, route.query.tab, route.query.branch_id, route.query.supplier_id],
  () => {
    if (syncingRouteFromUi.value) return
    if (applyQueryFromRoute()) loadAll()
  },
)

watch([from, to, branchId, supplierId], () => {
  persistReportFilters()
})

watch([branchId, supplierId], () => {
  scheduleAutoApply()
})
</script>
