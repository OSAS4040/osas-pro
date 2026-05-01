<template>
  <div class="app-shell-page print-container space-y-4" dir="rtl">
    <div class="page-head no-print">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">تقارير العميل</h2>
        <p class="page-subtitle">تقارير مخصصة لبيانات العميل وقراراته المالية والتشغيلية</p>
      </div>
      <div class="page-toolbar flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" @click="copyShareLink">نسخ رابط المشاركة</button>
        <button type="button" class="btn btn-secondary" @click="exportJSON">JSON</button>
        <button type="button" class="btn btn-secondary" @click="exportCSV">CSV</button>
        <button type="button" class="btn btn-secondary" @click="exportExcel">Excel</button>
        <button type="button" class="btn btn-secondary" @click="exportPDF">PDF</button>
        <button type="button" class="btn btn-primary" :disabled="loading" @click="load">
          {{ loading ? 'جارٍ التحديث...' : 'تحديث' }}
        </button>
      </div>
    </div>

    <div class="card no-print p-4 space-y-3">
      <p class="text-xs font-semibold text-gray-500">فترة سريعة</p>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-violet-50 text-violet-700 border border-violet-200" @click="applyPreset(30)">آخر 30 يوم</button>
        <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 border border-gray-200" @click="applyPreset(90)">آخر 90 يوم</button>
        <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 border border-gray-200" @click="applyPreset(365)">آخر 12 شهر</button>
      </div>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <input v-model="filters.from" type="date" class="field-sm">
        <input v-model="filters.to" type="date" class="field-sm">
        <select v-model.number="filters.orgUnitId" class="field-sm">
          <option :value="0">كل الوحدات التنظيمية</option>
          <option v-for="o in orgUnitOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
        </select>
        <button type="button" class="btn btn-secondary" @click="resetFilters">إعادة تعيين</button>
      </div>
      <div v-if="activeTab === 'invoices'" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <input v-model.trim="filters.search" type="text" placeholder="بحث برقم الفاتورة أو المعرف" class="field-sm">
        <select v-model="filters.status" class="field-sm">
          <option value="all">كل حالات السداد</option>
          <option value="paid">مدفوعة</option>
          <option value="partial">مدفوعة جزئياً</option>
          <option value="unpaid">غير مدفوعة</option>
          <option value="overdue">متأخرة</option>
        </select>
        <input v-model.trim="filters.minAmount" type="number" min="0" step="0.01" placeholder="من مبلغ (ر.س.)" class="field-sm">
        <input v-model.trim="filters.maxAmount" type="number" min="0" step="0.01" placeholder="إلى مبلغ (ر.س.)" class="field-sm">
        <select v-model.number="filters.vehicleId" class="field-sm">
          <option :value="0">كل المركبات</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number || ('#' + v.id) }} — {{ v.make }} {{ v.model }}</option>
        </select>
      </div>
      <div v-if="isOperationalReportTab" class="grid gap-3 md:grid-cols-3">
        <select v-model.number="filters.serviceId" class="field-sm">
          <option :value="0">كل الخدمات</option>
          <option v-for="s in serviceOptions" :key="s.id" :value="s.id">{{ s.name_ar || s.name }}</option>
        </select>
        <select v-model.number="filters.productId" class="field-sm">
          <option :value="0">كل المنتجات</option>
          <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.name_ar || p.name }} ({{ p.sku || '—' }})</option>
        </select>
      </div>
      <div v-if="activeTab === 'org_structure'" class="flex flex-wrap items-center gap-3 text-xs">
        <span class="font-semibold text-gray-600">عرض مستويات الهيكل:</span>
        <label class="inline-flex items-center gap-1.5 cursor-pointer">
          <input v-model="filters.orgTypeSector" type="checkbox" class="rounded border-gray-300">
          قطاع / إدارة
        </label>
        <label class="inline-flex items-center gap-1.5 cursor-pointer">
          <input v-model="filters.orgTypeDepartment" type="checkbox" class="rounded border-gray-300">
          قسم
        </label>
        <label class="inline-flex items-center gap-1.5 cursor-pointer">
          <input v-model="filters.orgTypeDivision" type="checkbox" class="rounded border-gray-300">
          وحدة / شعبة
        </label>
      </div>
      <p v-if="activeTab === 'org_structure'" class="text-xs text-gray-500">
        يعرض أرقاماً <strong>مرحّلة من الوحدات الفرعية إلى الأعلى</strong>. اختيار «وحدة تنظيمية» أعلاه يحدّ النطاق إلى فرعها والأطفال فقط (على مستوى الشركة).
      </p>
      <p v-if="isOperationalReportTab" class="text-xs text-gray-500">
        تقارير الخدمات والمنتجات والأوامر المنفذة تُحسب لأوامر العمل بحالة مكتمل/مسلّم ضمن الفترة المحددة.
      </p>
      <p v-if="activeTab === 'invoices'" class="text-xs text-gray-500">
        الفواتير والملخص المالي يعتمدان على <strong>تاريخ الإصدار</strong> ضمن الفترة مع التصفية من الخادم.
      </p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="card p-3">
        <p class="text-xs text-gray-500">عدد الفواتير (الفترة)</p>
        <p class="text-xl font-bold">{{ kpi.invoiceCount }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">إجمالي إيراد الفوترة</p>
        <p class="text-xl font-bold">{{ fmtMoney(kpi.totalInvoiced) }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">إجمالي المدفوع</p>
        <p class="text-xl font-bold text-green-700">{{ fmtMoney(kpi.totalPaid) }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">إجمالي المستحق</p>
        <p class="text-xl font-bold text-amber-700">{{ fmtMoney(kpi.totalDue) }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">فواتير متأخرة</p>
        <p class="text-xl font-bold text-red-700">{{ kpi.overdueCount }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">مدفوعة جزئياً</p>
        <p class="text-xl font-bold text-blue-700">{{ kpi.partialCount }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">أوامر نشطة (Pipeline)</p>
        <p class="text-xl font-bold text-violet-700">{{ kpi.activeWorkOrders }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">أوامر مكتملة في الفترة</p>
        <p class="text-xl font-bold text-teal-700">{{ kpi.completedInPeriod }}</p>
        <p class="text-[11px] text-gray-500 mt-1">مجموع المبالغ: {{ fmtMoney(kpi.completedAmount) }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">مركبات مسجّلة</p>
        <p class="text-xl font-bold text-slate-700">{{ kpi.vehiclesRegistered }}</p>
      </div>
    </div>

    <div v-if="pipelineRows.length" class="card p-4 no-print">
      <p class="text-sm font-semibold text-gray-900 mb-2">أوامر العمل المفتوحة في الفترة — التوزيع حسب الحالة</p>
      <p class="text-xs text-gray-500 mb-3">أوامر تم إنشاؤها بين تاريخ البداية والنهاية (حسب الفلتر الحالي).</p>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>الحالة</th>
              <th>العدد</th>
              <th>مجموع actual_total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in pipelineRows" :key="idx">
              <td>{{ workOrderStatusLabel(row.status) }}</td>
              <td>{{ row.count }}</td>
              <td>{{ fmtMoney(Number(row.total_actual ?? 0)) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">ذكاء أعمال العميل</p>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
            تحليل الاتجاهات، أشهر الذروة، ومؤشرات الالتزام بالسداد مخصص لقرارات العميل.
          </p>
        </div>
        <RouterLink to="/customer/business-intelligence" class="btn btn-primary">
          فتح لوحة ذكاء الأعمال
        </RouterLink>
      </div>
    </div>

    <div class="border-b border-gray-200 dark:border-slate-700">
      <nav class="flex gap-1 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition whitespace-nowrap"
          :class="activeTab === tab.key ? 'border-b-2 border-primary-500 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <div v-if="loading" class="state-loading">جارٍ التحميل...</div>

    <div v-else-if="activeTab === 'invoices'" class="table-shell">
      <div class="panel-head">
        <span class="panel-title">تفاصيل الفواتير</span>
        <span class="panel-muted">{{ invoiceReportMeta?.total ?? 0 }} عنصر</span>
      </div>
      <div class="no-print mb-2 flex items-center justify-between text-xs text-gray-500">
        <span>عرض {{ invoicePageStart }} - {{ invoicePageEnd }} من {{ invoiceReportMeta?.total ?? 0 }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="invoicePage <= 1" @click="invoicePage -= 1">السابق</button>
          <span>صفحة {{ invoicePage }} / {{ invoiceTotalPages }}</span>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="invoicePage >= invoiceTotalPages" @click="invoicePage += 1">التالي</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>رقم الفاتورة</th>
            <th>تاريخ الإصدار</th>
            <th>المركبة</th>
            <th>الإجمالي</th>
            <th>المستحق</th>
            <th>حالة السداد</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in invoiceReportRows" :key="inv.id">
            <td class="font-medium text-primary-700">{{ inv.invoice_number || ('#' + inv.id) }}</td>
            <td>{{ fmtDate(String(inv.issued_at ?? '')) }}</td>
            <td class="text-sm">{{ inv.vehicle?.plate_number || '—' }}</td>
            <td class="font-medium">{{ fmtMoney(Number(inv.total ?? 0)) }}</td>
            <td>{{ fmtMoney(Number(inv.due_amount ?? 0)) }}</td>
            <td>
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadgeClass(invPaymentBucket(inv))">{{ statusLabel(invPaymentBucket(inv)) }}</span>
            </td>
          </tr>
          <tr v-if="!invoiceReportRows.length">
            <td colspan="6" class="table-empty">
              <p class="table-empty-title">لا توجد فواتير مطابقة</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="activeTab === 'by_service'" class="table-shell">
      <div class="panel-head">
        <span class="panel-title">الخدمات (من أوامر منفذة)</span>
        <span class="panel-muted">{{ reportMeta?.total ?? 0 }} مجموعة</span>
      </div>
      <p v-if="reportSummary && 'total_amount' in reportSummary" class="text-xs text-gray-600 px-2 py-1">
        إجمالي الكمية: {{ reportSummary.total_quantity }} — إجمالي المبلغ: {{ fmtMoney(Number(reportSummary.total_amount ?? 0)) }}
      </p>
      <div class="no-print mb-2 flex items-center justify-between text-xs text-gray-500">
        <span>صفحة {{ reportMeta?.current_page ?? 1 }} / {{ reportMeta?.last_page ?? 1 }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="(reportMeta?.current_page ?? 1) <= 1" @click="reportPage -= 1">السابق</button>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="(reportMeta?.current_page ?? 1) >= (reportMeta?.last_page ?? 1)" @click="reportPage += 1">التالي</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>الخدمة</th>
            <th>الكود</th>
            <th>الكمية</th>
            <th>المبلغ</th>
            <th>عدد البنود</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in reportServiceRows" :key="row.id">
            <td class="font-medium">{{ row.name_ar || row.name }}</td>
            <td class="font-mono text-xs">{{ row.code || '—' }}</td>
            <td>{{ row.total_quantity }}</td>
            <td>{{ fmtMoney(Number(row.total_amount ?? 0)) }}</td>
            <td>{{ row.lines_count }}</td>
          </tr>
          <tr v-if="!reportServiceRows.length">
            <td colspan="5" class="table-empty">
              <p class="table-empty-title">لا توجد بيانات ضمن الفترة</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="activeTab === 'by_product'" class="table-shell">
      <div class="panel-head">
        <span class="panel-title">المنتجات (من أوامر منفذة)</span>
        <span class="panel-muted">{{ reportMeta?.total ?? 0 }} مجموعة</span>
      </div>
      <p v-if="reportSummary && 'total_amount' in reportSummary" class="text-xs text-gray-600 px-2 py-1">
        إجمالي الكمية: {{ reportSummary.total_quantity }} — إجمالي المبلغ: {{ fmtMoney(Number(reportSummary.total_amount ?? 0)) }}
      </p>
      <div class="no-print mb-2 flex items-center justify-between text-xs text-gray-500">
        <span>صفحة {{ reportMeta?.current_page ?? 1 }} / {{ reportMeta?.last_page ?? 1 }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="(reportMeta?.current_page ?? 1) <= 1" @click="reportPage -= 1">السابق</button>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="(reportMeta?.current_page ?? 1) >= (reportMeta?.last_page ?? 1)" @click="reportPage += 1">التالي</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>المنتج</th>
            <th>SKU</th>
            <th>الكمية</th>
            <th>المبلغ</th>
            <th>عدد البنود</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in reportProductRows" :key="row.id">
            <td class="font-medium">{{ row.name_ar || row.name }}</td>
            <td class="font-mono text-xs">{{ row.sku || '—' }}</td>
            <td>{{ row.total_quantity }}</td>
            <td>{{ fmtMoney(Number(row.total_amount ?? 0)) }}</td>
            <td>{{ row.lines_count }}</td>
          </tr>
          <tr v-if="!reportProductRows.length">
            <td colspan="5" class="table-empty">
              <p class="table-empty-title">لا توجد بيانات ضمن الفترة</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="activeTab === 'work_orders_completed'" class="table-shell">
      <div class="panel-head">
        <span class="panel-title">أوامر العمل المنفذة</span>
        <span class="panel-muted">{{ reportMeta?.total ?? 0 }} أمر</span>
      </div>
      <p v-if="reportSummary && 'total_actual' in reportSummary" class="text-xs text-gray-600 px-2 py-1">
        إجمالي المبالغ (actual_total) للفلتر: {{ fmtMoney(Number(reportSummary.total_actual ?? 0)) }}
      </p>
      <div class="no-print mb-2 flex items-center justify-between text-xs text-gray-500">
        <span>صفحة {{ reportMeta?.current_page ?? 1 }} / {{ reportMeta?.last_page ?? 1 }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="(reportMeta?.current_page ?? 1) <= 1" @click="reportPage -= 1">السابق</button>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="(reportMeta?.current_page ?? 1) >= (reportMeta?.last_page ?? 1)" @click="reportPage += 1">التالي</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>رقم الأمر</th>
            <th>تاريخ الإكمال/التسليم</th>
            <th>المركبة</th>
            <th>الحالة</th>
            <th>الإجمالي</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="wo in reportCompletedRows" :key="wo.id">
            <td class="font-medium">{{ wo.order_number || ('#' + wo.id) }}</td>
            <td>{{ fmtDate(String(wo.completed_at || wo.delivered_at || '')) }}</td>
            <td>{{ wo.vehicle?.plate_number || '—' }}</td>
            <td>
              <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{{ workOrderStatusLabel(wo.status) }}</span>
            </td>
            <td>{{ wo.actual_total != null ? fmtMoney(Number(wo.actual_total)) : '—' }}</td>
          </tr>
          <tr v-if="!reportCompletedRows.length">
            <td colspan="5" class="table-empty">
              <p class="table-empty-title">لا توجد أوامر منفذة ضمن الفترة</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="activeTab === 'org_structure'" class="table-shell">
      <div class="panel-head">
        <span class="panel-title">الهيكل التنظيمي — إجماليات مرحّلة</span>
        <span class="panel-muted">{{ orgBreakdownRows.length }} وحدة</span>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>المستوى</th>
              <th>الاسم</th>
              <th>الكود</th>
              <th>فواتير</th>
              <th>إجمالي الفوترة</th>
              <th>مدفوع</th>
              <th>مستحق</th>
              <th>أوامر مكتملة</th>
              <th>مبلغ مكتمل</th>
              <th>أوامر نشطة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in orgBreakdownRows" :key="row.org_unit_id">
              <td><span class="text-xs font-medium text-primary-700">{{ row.type_label_ar }}</span></td>
              <td class="font-medium">{{ row.name_ar || row.name }}</td>
              <td class="font-mono text-xs">{{ row.code || '—' }}</td>
              <td>{{ row.invoice_count }}</td>
              <td>{{ fmtMoney(Number(row.invoice_total ?? 0)) }}</td>
              <td>{{ fmtMoney(Number(row.invoice_paid ?? 0)) }}</td>
              <td>{{ fmtMoney(Number(row.invoice_due ?? 0)) }}</td>
              <td>{{ row.work_orders_completed_count }}</td>
              <td>{{ fmtMoney(Number(row.work_orders_completed_amount ?? 0)) }}</td>
              <td>{{ row.work_orders_open_count }}</td>
            </tr>
            <tr v-if="orgBreakdownUnassigned">
              <td colspan="3" class="font-medium text-amber-800">{{ orgBreakdownUnassigned.type_label_ar }}</td>
              <td>{{ orgBreakdownUnassigned.invoice_count }}</td>
              <td>{{ fmtMoney(Number(orgBreakdownUnassigned.invoice_total ?? 0)) }}</td>
              <td>{{ fmtMoney(Number(orgBreakdownUnassigned.invoice_paid ?? 0)) }}</td>
              <td>{{ fmtMoney(Number(orgBreakdownUnassigned.invoice_due ?? 0)) }}</td>
              <td>{{ orgBreakdownUnassigned.work_orders_completed_count }}</td>
              <td>{{ fmtMoney(Number(orgBreakdownUnassigned.work_orders_completed_amount ?? 0)) }}</td>
              <td>{{ orgBreakdownUnassigned.work_orders_open_count }}</td>
            </tr>
            <tr v-if="!orgBreakdownRows.length && !orgBreakdownUnassigned">
              <td colspan="10" class="table-empty">
                <p class="table-empty-title">لا توجد وحدات أو بيانات ضمن الفلتر</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="activeTab === 'vehicles'" class="table-shell">
      <div class="panel-head">
        <span class="panel-title">المركبات</span>
        <span class="panel-muted">{{ vehicles.length }} عنصر</span>
      </div>
      <div class="no-print mb-2 flex items-center justify-between text-xs text-gray-500">
        <span>عرض {{ pageStart }} - {{ pageEnd }} من {{ activeCount }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage <= 1" @click="currentPage -= 1">السابق</button>
          <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="currentPage += 1">التالي</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>رقم اللوحة</th>
            <th>الماركة/الموديل</th>
            <th>السنة</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in paginatedVehicles" :key="v.id">
            <td class="font-mono">{{ v.plate_number || '—' }}</td>
            <td>{{ v.make }} {{ v.model }}</td>
            <td>{{ v.year || '—' }}</td>
            <td>
              <span class="px-2 py-0.5 rounded-full text-xs" :class="v.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                {{ v.is_active ? 'نشطة' : 'غير نشطة' }}
              </span>
            </td>
          </tr>
          <tr v-if="!vehicles.length">
            <td colspan="4" class="table-empty">
              <p class="table-empty-title">لا توجد مركبات</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { workOrderStatusLabel } from '@/utils/workOrderStatusLabels'
import { printDocument } from '@/composables/useAppPrint'
import { useToast } from '@/composables/useToast'

type TabKey = 'invoices' | 'by_service' | 'by_product' | 'work_orders_completed' | 'org_structure' | 'vehicles'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const toast = useToast()
const activeTab = ref<TabKey>('invoices')
const pageSize = 20
const currentPage = ref(1)
const invoicePage = ref(1)
const reportPage = ref(1)
const summaryPayload = ref<Record<string, any> | null>(null)
const invoiceReportRows = ref<any[]>([])
const invoiceReportMeta = ref<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null)
const vehicles = ref<any[]>([])

const reportServiceRows = ref<any[]>([])
const reportProductRows = ref<any[]>([])
const reportCompletedRows = ref<any[]>([])
const orgBreakdownRows = ref<any[]>([])
const orgBreakdownUnassigned = ref<Record<string, unknown> | null>(null)
const reportMeta = ref<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null)
const reportSummary = ref<Record<string, unknown>>({})

const serviceOptions = ref<{ id: number; name: string; name_ar?: string | null; code?: string | null }[]>([])
const productOptions = ref<{ id: number; name: string; name_ar?: string | null; sku?: string | null }[]>([])
const orgUnitOptions = ref<{ id: number; label: string }[]>([])

const tabs = [
  { key: 'invoices' as const, label: 'الفواتير' },
  { key: 'by_service' as const, label: 'الخدمات' },
  { key: 'by_product' as const, label: 'المنتجات' },
  { key: 'work_orders_completed' as const, label: 'أوامر منفذة' },
  { key: 'org_structure' as const, label: 'الهيكل التنظيمي' },
  { key: 'vehicles' as const, label: 'المركبات' },
]

const filters = reactive({
  search: '',
  status: 'all',
  from: '',
  to: '',
  orgUnitId: 0,
  serviceId: 0,
  productId: 0,
  minAmount: '',
  maxAmount: '',
  vehicleId: 0,
  orgTypeSector: true,
  orgTypeDepartment: true,
  orgTypeDivision: true,
})

const isOperationalReportTab = computed(() =>
  ['by_service', 'by_product', 'work_orders_completed'].includes(activeTab.value),
)

/** دلو السداد للعرض — متسق مع منطق الخادم */
function invPaymentBucket(inv: any): string {
  const st = String(inv.status ?? '').toLowerCase()
  if (st === 'partial_paid') return 'partial'
  const due = Number(inv.due_amount ?? 0)
  if (due <= 0 || st === 'paid') return 'paid'
  const dueAt = inv.due_at ? new Date(String(inv.due_at)) : null
  if (dueAt && !Number.isNaN(dueAt.getTime()) && dueAt.getTime() < Date.now()) return 'overdue'
  return 'unpaid'
}
function statusLabel(s: string): string {
  if (s === 'paid') return 'مدفوعة'
  if (s === 'partial') return 'مدفوعة جزئياً'
  if (s === 'overdue') return 'متأخرة'
  return 'غير مدفوعة'
}
function statusBadgeClass(s: string): string {
  if (s === 'paid') return 'bg-green-100 text-green-700'
  if (s === 'partial') return 'bg-blue-100 text-blue-800'
  if (s === 'overdue') return 'bg-red-100 text-red-700'
  return 'bg-amber-100 text-amber-700'
}
function fmtMoney(v: number): string {
  return v.toLocaleString('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 2 })
}
function fmtDate(d: string): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-SA')
}
function extractList(payload: any): any[] {
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}
const paginatedVehicles = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return vehicles.value.slice(start, start + pageSize)
})

const pipelineRows = computed(() => {
  const rows = summaryPayload.value?.work_orders?.opened_in_period_by_status
  return Array.isArray(rows) ? rows : []
})

const invoiceTotalPages = computed(() => Math.max(1, invoiceReportMeta.value?.last_page ?? 1))
const invoicePageStart = computed(() => {
  const total = invoiceReportMeta.value?.total ?? 0
  if (!total) return 0
  const per = invoiceReportMeta.value?.per_page ?? 25
  const cur = invoiceReportMeta.value?.current_page ?? 1
  return (cur - 1) * per + 1
})
const invoicePageEnd = computed(() => {
  const total = invoiceReportMeta.value?.total ?? 0
  if (!total) return 0
  const per = invoiceReportMeta.value?.per_page ?? 25
  const cur = invoiceReportMeta.value?.current_page ?? 1
  return Math.min(cur * per, total)
})

const activeCount = computed(() => {
  if (activeTab.value === 'vehicles') return vehicles.value.length
  if (['by_service', 'by_product', 'work_orders_completed'].includes(activeTab.value)) {
    return reportMeta.value?.total ?? 0
  }
  return 0
})
const totalPages = computed(() => {
  if (activeTab.value === 'vehicles') return Math.max(1, Math.ceil(vehicles.value.length / pageSize))
  return Math.max(1, reportMeta.value?.last_page ?? 1)
})
const pageStart = computed(() => {
  if (['by_service', 'by_product', 'work_orders_completed'].includes(activeTab.value)) {
    const total = reportMeta.value?.total ?? 0
    if (!total) return 0
    const per = reportMeta.value?.per_page ?? 25
    const cur = reportMeta.value?.current_page ?? 1
    return (cur - 1) * per + 1
  }
  return activeCount.value ? ((currentPage.value - 1) * pageSize) + 1 : 0
})
const pageEnd = computed(() => {
  if (['by_service', 'by_product', 'work_orders_completed'].includes(activeTab.value)) {
    const total = reportMeta.value?.total ?? 0
    if (!total) return 0
    const per = reportMeta.value?.per_page ?? 25
    const cur = reportMeta.value?.current_page ?? 1
    return Math.min(cur * per, total)
  }
  return Math.min(currentPage.value * pageSize, activeCount.value)
})

const kpi = computed(() => {
  const inv = summaryPayload.value?.invoices
  const wo = summaryPayload.value?.work_orders
  if (!inv || !wo) {
    return {
      invoiceCount: 0,
      totalInvoiced: 0,
      totalPaid: 0,
      totalDue: 0,
      overdueCount: 0,
      partialCount: 0,
      activeWorkOrders: 0,
      completedInPeriod: 0,
      completedAmount: 0,
      vehiclesRegistered: 0,
    }
  }
  return {
    invoiceCount: Number(inv.count ?? 0),
    totalInvoiced: Number(inv.total_invoiced ?? 0),
    totalPaid: Number(inv.total_paid ?? 0),
    totalDue: Number(inv.total_due ?? 0),
    overdueCount: Number(inv.overdue_count ?? 0),
    partialCount: Number(inv.partial_count ?? 0),
    activeWorkOrders: Number(wo.active_open ?? 0),
    completedInPeriod: Number(wo.completed_in_period ?? 0),
    completedAmount: Number(wo.completed_amount_in_period ?? 0),
    vehiclesRegistered: Number(summaryPayload.value?.vehicles_registered ?? 0),
  }
})

function applyPreset(days: number): void {
  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - days)
  filters.to = to.toISOString().split('T')[0]
  filters.from = from.toISOString().split('T')[0]
}
function resetFilters(): void {
  filters.search = ''
  filters.status = 'all'
  filters.from = ''
  filters.to = ''
  filters.orgUnitId = 0
  filters.serviceId = 0
  filters.productId = 0
  filters.minAmount = ''
  filters.maxAmount = ''
  filters.vehicleId = 0
  filters.orgTypeSector = true
  filters.orgTypeDepartment = true
  filters.orgTypeDivision = true
}

function reportParams(): Record<string, string | number> {
  const p: Record<string, string | number> = {
    from: filters.from,
    to: filters.to,
    page: reportPage.value,
    per_page: 25,
  }
  if (filters.orgUnitId > 0) p.org_unit_id = filters.orgUnitId
  if (filters.serviceId > 0) p.service_id = filters.serviceId
  if (filters.productId > 0) p.product_id = filters.productId
  return p
}

/** عند اختيار الأنواع الثلاثة أو عدم اختيار أي نوع: لا نرسل الفلتر (الخادم يعرض الجميع). */
function selectedOrgUnitTypesParam(): string | undefined {
  const parts: string[] = []
  if (filters.orgTypeSector) parts.push('sector')
  if (filters.orgTypeDepartment) parts.push('department')
  if (filters.orgTypeDivision) parts.push('division')
  if (parts.length === 3 || parts.length === 0) return undefined
  return parts.join(',')
}

async function loadOrgBreakdown(): Promise<void> {
  if (activeTab.value !== 'org_structure') return
  if (!filters.from || !filters.to) {
    orgBreakdownRows.value = []
    orgBreakdownUnassigned.value = null
    return
  }
  const params: Record<string, string | number> = {
    from: filters.from,
    to: filters.to,
  }
  if (filters.orgUnitId > 0) params.org_unit_id = filters.orgUnitId
  const types = selectedOrgUnitTypesParam()
  if (types) params.org_unit_types = types
  try {
    const { data } = await apiClient.get('/customer-portal/reports/org-unit-breakdown', { params })
    orgBreakdownRows.value = data?.data?.rows ?? []
    orgBreakdownUnassigned.value = data?.data?.unassigned ?? null
  } catch {
    orgBreakdownRows.value = []
    orgBreakdownUnassigned.value = null
    toast.warning('تعذر تحميل تقرير الهيكل', 'تحقق من الاتصال أو الصلاحيات.')
  }
}

function flattenOrgUnits(nodes: any[], depth = 0): { id: number; label: string }[] {
  const out: { id: number; label: string }[] = []
  for (const node of nodes ?? []) {
    const id = Number(node?.id ?? 0)
    if (!id) continue
    const name = String(node?.name_ar || node?.name || `#${id}`)
    const prefix = depth > 0 ? `${'— '.repeat(depth)}` : ''
    out.push({ id, label: `${prefix}${name}` })
    if (Array.isArray(node?.children) && node.children.length) {
      out.push(...flattenOrgUnits(node.children, depth + 1))
    }
  }
  return out
}

async function loadOrgUnits(): Promise<void> {
  try {
    const { data } = await apiClient.get('/customer-portal/org-units/tree', { params: { active_only: false } })
    orgUnitOptions.value = flattenOrgUnits(Array.isArray(data?.data) ? data.data : [])
  } catch {
    orgUnitOptions.value = []
  }
}

async function loadSummary(): Promise<void> {
  if (!filters.from || !filters.to) return
  try {
    const params: Record<string, string | number> = { from: filters.from, to: filters.to }
    if (filters.orgUnitId > 0) params.org_unit_id = filters.orgUnitId
    const { data } = await apiClient.get('/customer-portal/reports/summary', { params })
    summaryPayload.value = data?.data ?? null
  } catch {
    summaryPayload.value = null
  }
}

async function loadInvoiceReport(): Promise<void> {
  if (activeTab.value !== 'invoices') return
  if (!filters.from || !filters.to) {
    invoiceReportRows.value = []
    invoiceReportMeta.value = null
    return
  }
  try {
    const params: Record<string, string | number> = {
      from: filters.from,
      to: filters.to,
      page: invoicePage.value,
      per_page: 25,
    }
    if (filters.orgUnitId > 0) params.org_unit_id = filters.orgUnitId
    if (filters.search.trim()) params.search = filters.search.trim()
    if (filters.status !== 'all') params.payment_status = filters.status
    const min = parseFloat(String(filters.minAmount).replace(',', '.'))
    if (!Number.isNaN(min) && min >= 0) params.min_amount = min
    const max = parseFloat(String(filters.maxAmount).replace(',', '.'))
    if (!Number.isNaN(max) && max >= 0) params.max_amount = max
    if (filters.vehicleId > 0) params.vehicle_id = filters.vehicleId

    const { data } = await apiClient.get('/customer-portal/reports/invoices', { params })
    invoiceReportRows.value = data?.data?.rows ?? []
    invoiceReportMeta.value = data?.meta ?? null
  } catch {
    invoiceReportRows.value = []
    invoiceReportMeta.value = null
    toast.warning('تعذر تحميل الفواتير', 'تحقق من الاتصال أو الصلاحيات.')
  }
}

async function loadFilterOptions(): Promise<void> {
  if (!filters.from || !filters.to) return
  try {
    const params: Record<string, string> = { from: filters.from, to: filters.to }
    if (filters.orgUnitId > 0) params.org_unit_id = String(filters.orgUnitId)
    const { data } = await apiClient.get('/customer-portal/reports/filter-options', { params })
    serviceOptions.value = data?.data?.services ?? []
    productOptions.value = data?.data?.products ?? []
  } catch {
    serviceOptions.value = []
    productOptions.value = []
  }
}

async function loadOperationalReport(): Promise<void> {
  if (!isOperationalReportTab.value) return
  if (!filters.from || !filters.to) {
    reportServiceRows.value = []
    reportProductRows.value = []
    reportCompletedRows.value = []
    reportMeta.value = null
    reportSummary.value = {}
    return
  }
  const params = reportParams()
  try {
    if (activeTab.value === 'by_service') {
      const { data } = await apiClient.get('/customer-portal/reports/work-order-items-by-service', { params })
      reportServiceRows.value = data?.data?.rows ?? []
      reportSummary.value = data?.data?.summary ?? {}
      reportMeta.value = data?.meta ?? null
    } else if (activeTab.value === 'by_product') {
      const { data } = await apiClient.get('/customer-portal/reports/work-order-items-by-product', { params })
      reportProductRows.value = data?.data?.rows ?? []
      reportSummary.value = data?.data?.summary ?? {}
      reportMeta.value = data?.meta ?? null
    } else if (activeTab.value === 'work_orders_completed') {
      const { data } = await apiClient.get('/customer-portal/reports/work-orders-completed', { params })
      reportCompletedRows.value = data?.data?.rows ?? []
      reportSummary.value = data?.data?.summary ?? {}
      reportMeta.value = data?.meta ?? null
    }
  } catch {
    reportServiceRows.value = []
    reportProductRows.value = []
    reportCompletedRows.value = []
    reportMeta.value = null
    reportSummary.value = {}
    toast.warning('تعذر تحميل التقرير', 'تحقق من الاتصال أو صلاحية الحساب.')
  }
}

function applyRouteQuery(): void {
  const q = route.query
  const tab = String(q.tab ?? '')
  if (['invoices', 'by_service', 'by_product', 'work_orders_completed', 'org_structure', 'vehicles'].includes(tab)) {
    activeTab.value = tab as TabKey
  }
  if (typeof q.from === 'string' && q.from) filters.from = q.from
  if (typeof q.to === 'string' && q.to) filters.to = q.to
  if (q.service_id != null && q.service_id !== '') filters.serviceId = Number(q.service_id) || 0
  if (q.product_id != null && q.product_id !== '') filters.productId = Number(q.product_id) || 0
  if (q.org_unit_id != null && q.org_unit_id !== '') filters.orgUnitId = Number(q.org_unit_id) || 0
  if (typeof q.min_amount === 'string' && q.min_amount) filters.minAmount = q.min_amount
  if (typeof q.max_amount === 'string' && q.max_amount) filters.maxAmount = q.max_amount
  if (q.vehicle_id != null && q.vehicle_id !== '') filters.vehicleId = Number(q.vehicle_id) || 0
  if (typeof q.search === 'string' && q.search) filters.search = q.search
  if (typeof q.payment_status === 'string' && q.payment_status) filters.status = q.payment_status
  if (typeof q.org_unit_types === 'string' && q.org_unit_types.trim()) {
    const set = new Set(
      q.org_unit_types
        .split(',')
        .map((s) => s.trim().toLowerCase())
        .filter(Boolean),
    )
    filters.orgTypeSector = set.has('sector')
    filters.orgTypeDepartment = set.has('department')
    filters.orgTypeDivision = set.has('division')
    if (!filters.orgTypeSector && !filters.orgTypeDepartment && !filters.orgTypeDivision) {
      filters.orgTypeSector = true
      filters.orgTypeDepartment = true
      filters.orgTypeDivision = true
    }
  }
}

async function copyShareLink(): Promise<void> {
  const q: Record<string, string> = {
    tab: activeTab.value,
    from: filters.from,
    to: filters.to,
  }
  if (filters.search.trim()) q.search = filters.search.trim()
  if (filters.status !== 'all') q.payment_status = filters.status
  if (filters.orgUnitId > 0) q.org_unit_id = String(filters.orgUnitId)
  if (filters.serviceId > 0) q.service_id = String(filters.serviceId)
  if (filters.productId > 0) q.product_id = String(filters.productId)
  if (filters.minAmount !== '') q.min_amount = String(filters.minAmount)
  if (filters.maxAmount !== '') q.max_amount = String(filters.maxAmount)
  if (filters.vehicleId > 0) q.vehicle_id = String(filters.vehicleId)
  const orgTypes = selectedOrgUnitTypesParam()
  if (orgTypes) q.org_unit_types = orgTypes
  const url = router.resolve({ name: 'customer.reports', query: q }).href
  const full = `${window.location.origin}${url}`
  try {
    await navigator.clipboard.writeText(full)
    toast.success('تم النسخ', 'رابط المشاركة في الحافظة.')
  } catch {
    toast.warning('لم يتم النسخ', full)
  }
}

watch(
  [
    () => filters.search,
    () => filters.status,
    () => filters.minAmount,
    () => filters.maxAmount,
    () => filters.vehicleId,
    () => filters.from,
    () => filters.to,
    () => filters.orgUnitId,
    activeTab,
  ],
  () => {
    currentPage.value = 1
    invoicePage.value = 1
    reportPage.value = 1
    if (activeTab.value === 'invoices') void loadInvoiceReport()
  },
)
watch(invoicePage, () => {
  if (activeTab.value === 'invoices') void loadInvoiceReport()
})
watch([reportPage, () => filters.orgUnitId, () => filters.serviceId, () => filters.productId], () => {
  if (!isOperationalReportTab.value) return
  void loadOperationalReport()
})
watch(activeTab, () => {
  reportPage.value = 1
  invoicePage.value = 1
  if (activeTab.value === 'invoices') void loadInvoiceReport()
  else if (isOperationalReportTab.value) void loadOperationalReport()
  else if (activeTab.value === 'org_structure') void loadOrgBreakdown()
})
watch([() => filters.from, () => filters.to, () => filters.orgUnitId], () => {
  void loadSummary()
  void loadFilterOptions()
  void loadInvoiceReport()
  if (isOperationalReportTab.value) void loadOperationalReport()
  void loadOrgBreakdown()
})
watch(
  [() => filters.orgTypeSector, () => filters.orgTypeDepartment, () => filters.orgTypeDivision],
  () => {
    if (activeTab.value === 'org_structure') void loadOrgBreakdown()
  },
)
watch(totalPages, (next) => {
  if (currentPage.value > next) currentPage.value = next
})
watch(invoiceTotalPages, (next) => {
  if (invoicePage.value > next) invoicePage.value = next
})

function activeRows(): any[] {
  if (activeTab.value === 'invoices') return invoiceReportRows.value
  if (activeTab.value === 'by_service') return reportServiceRows.value
  if (activeTab.value === 'by_product') return reportProductRows.value
  if (activeTab.value === 'work_orders_completed') return reportCompletedRows.value
  if (activeTab.value === 'org_structure') {
    const rows = [...orgBreakdownRows.value]
    if (orgBreakdownUnassigned.value) rows.push(orgBreakdownUnassigned.value)
    return rows
  }
  return vehicles.value
}
function exportCSV(): void {
  const rows = activeRows()
  if (!rows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد بيانات للتصدير.')
    return
  }
  const keys = Object.keys(rows[0] ?? {})
  const csv = [keys.join(','), ...rows.map((r) => keys.map((k) => `"${String(r[k] ?? '').replace(/"/g, '""')}"`).join(','))].join('\n')
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }))
  const a = document.createElement('a')
  a.href = url
  a.download = `customer_report_${activeTab.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
  toast.success('تم التصدير', 'تم تنزيل ملف CSV بنجاح.')
}
async function exportExcel(): Promise<void> {
  const rows = activeRows()
  if (!rows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد بيانات للتصدير.')
    return
  }
  const { downloadExcelFromRows } = await import('@/utils/exportExcel')
  await downloadExcelFromRows(rows, 'تقرير العميل', `customer_report_${activeTab.value}.xlsx`)
  toast.success('تم التصدير', 'تم تنزيل ملف Excel بنجاح.')
}
function exportJSON(): void {
  const rows = activeRows()
  if (!rows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد بيانات للتصدير.')
    return
  }
  const blob = new Blob([JSON.stringify({ tab: activeTab.value, filters: { ...filters }, summary: summaryPayload.value, rows }, null, 2)], { type: 'application/json;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `customer_report_${activeTab.value}.json`
  a.click()
  URL.revokeObjectURL(url)
  toast.success('تم التصدير', 'تم تنزيل ملف JSON بنجاح.')
}
async function exportPDF(): Promise<void> {
  await printDocument({ rootSelector: '.print-container' })
}

async function load(): Promise<void> {
  loading.value = true
  try {
    applyRouteQuery()
    const vehRes = await Promise.allSettled([apiClient.get('/vehicles', { params: { per_page: 500 } })])
    vehicles.value = vehRes[0].status === 'fulfilled' ? extractList(vehRes[0].value.data) : []
    await loadOrgUnits()
    await Promise.all([loadSummary(), loadFilterOptions(), loadInvoiceReport(), loadOperationalReport(), loadOrgBreakdown()])
  } finally {
    currentPage.value = 1
    invoicePage.value = 1
    loading.value = false
  }
}

onMounted(() => {
  applyPreset(30)
  applyRouteQuery()
  load()
})
</script>
