<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl flex items-center gap-2">
          <DocumentTextIcon class="w-6 h-6 text-primary-600 dark:text-primary-400 shrink-0" />
          عروض الأسعار
        </h2>
        <p class="page-subtitle">إنشاء ومتابعة العروض وحالاتها</p>
      </div>
      <div class="page-toolbar">
        <button type="button" class="btn btn-primary" @click="openCreateModal">
          <PlusIcon class="w-4 h-4" />
          عرض جديد
        </button>
      </div>
    </div>

    <!-- Stats Bar -->
    <div class="flex flex-wrap gap-3">
      <button
        v-for="stat in stats"
        :key="stat.key"
        class="flex items-center gap-2 px-3 py-2 rounded-xl border text-sm font-medium transition-all"
        :class="[
          (stat.key === 'all' ? filterStatus === '' : filterStatus === stat.key)
            ? `${stat.activeBg} ${stat.activeText} border-transparent shadow-sm`
            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'
        ]"
        @click="filterStatus = stat.key === 'all' ? '' : stat.key"
      >
        {{ stat.label }}
        <span
          class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold"
          :class="(stat.key === 'all' ? filterStatus === '' : filterStatus === stat.key) ? stat.badgeBg : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
        >
          {{ statCounts[stat.key] ?? 0 }}
        </span>
      </button>
    </div>

    <!-- Filters Row -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 flex flex-wrap gap-3 items-center">
      <div class="flex items-center gap-2 flex-1 min-w-[180px]">
        <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
        <input
          v-model="searchCustomer"
          placeholder="ابحث عن عميل..."
          class="flex-1 text-sm bg-transparent text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none"
        />
      </div>
      <div class="h-5 w-px bg-gray-200 dark:bg-gray-600 hidden sm:block" />
      <select
        v-model="filterStatus"
        class="text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-400"
      >
        <option value="">كل الحالات</option>
        <option value="draft">مسودة</option>
        <option value="sent">مُرسل</option>
        <option value="accepted">مقبول</option>
        <option value="rejected">مرفوض</option>
        <option value="expired">منتهي الصلاحية</option>
      </select>
      <div class="min-w-[260px]">
        <SmartDatePicker mode="range" :from-value="dateFrom" :to-value="dateTo" @change="onFilterDateRangeChange" />
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div v-if="loading" class="state-loading py-12">جارٍ التحميل...</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 dark:text-gray-400 text-right">
            <tr>
              <th class="px-4 py-3 font-medium">رقم العرض</th>
              <th class="px-4 py-3 font-medium">العميل</th>
              <th class="px-4 py-3 font-medium">التاريخ</th>
              <th class="px-4 py-3 font-medium">الانتهاء</th>
              <th class="px-4 py-3 font-medium">الإجمالي</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
              <th class="px-4 py-3 font-medium">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
            <tr
              v-for="q in filteredQuotes"
              :key="q.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
            >
              <td class="px-4 py-3 font-mono font-semibold text-gray-800 dark:text-gray-100">
                {{ q.quote_number ?? `#${q.id}` }}
              </td>
              <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ q.customer?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ formatDate(q.issue_date) }}</td>
              <td class="px-4 py-3 text-xs" :class="isExpired(q.expiry_date) ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'">
                {{ formatDate(q.expiry_date) }}
              </td>
              <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-100">
                {{ Number(q.total ?? 0).toFixed(2) }} ر.س
              </td>
              <td class="px-4 py-3">
                <span :class="statusChip(q.status)" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ statusLabel(q.status) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1 flex-wrap">
                  <button
                    class="px-2.5 py-1 text-xs rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    @click="viewQuote(q)"
                  >
                    عرض
                  </button>
                  <button
                    v-if="q.status === 'draft'"
                    class="px-2.5 py-1 text-xs rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                    @click="changeStatus(q, 'sent')"
                  >
                    إرسال
                  </button>
                  <button
                    v-if="q.status === 'sent'"
                    class="px-2.5 py-1 text-xs rounded-lg bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors"
                    @click="changeStatus(q, 'accepted')"
                  >
                    قبول
                  </button>
                  <button
                    v-if="q.status === 'sent'"
                    class="px-2.5 py-1 text-xs rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors"
                    @click="changeStatus(q, 'rejected')"
                  >
                    رفض
                  </button>
                  <button
                    class="px-2.5 py-1 text-xs rounded-lg bg-red-50 dark:bg-red-900/20 text-red-500 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                    @click="deleteQuote(q)"
                  >
                    حذف
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredQuotes.length">
              <td colspan="7" class="text-center py-12 text-gray-400 dark:text-gray-500">
                <DocumentTextIcon class="w-10 h-10 mx-auto mb-2 text-gray-200 dark:text-gray-700" />
                <p class="text-sm">لا توجد عروض أسعار</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- View Quote Modal -->
    <div
      v-if="viewModal && selectedQuote"
      class="fixed inset-0 bg-black/40 dark:bg-black/60 z-50 flex items-center justify-center p-4"
      @click.self="viewModal = false"
    >
      <div
        ref="quotePrintRoot"
        class="print-container bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh]"
        dir="rtl"
      >
        <div class="no-print flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 shrink-0">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">تفاصيل عرض السعر</h3>
          <button type="button" aria-label="إغلاق" @click="viewModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>
        <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-gray-400 dark:text-gray-500 text-xs mb-0.5">رقم العرض</p>
              <p class="font-semibold text-gray-800 dark:text-gray-100 font-mono">{{ selectedQuote.quote_number ?? `#${selectedQuote.id}` }}</p>
            </div>
            <div>
              <p class="text-gray-400 dark:text-gray-500 text-xs mb-0.5">العميل</p>
              <p class="font-semibold text-gray-800 dark:text-gray-100">{{ selectedQuote.customer?.name ?? '—' }}</p>
            </div>
            <div>
              <p class="text-gray-400 dark:text-gray-500 text-xs mb-0.5">تاريخ الإصدار</p>
              <p class="text-gray-700 dark:text-gray-300">{{ formatDate(selectedQuote.issue_date) }}</p>
            </div>
            <div>
              <p class="text-gray-400 dark:text-gray-500 text-xs mb-0.5">تاريخ الانتهاء</p>
              <p class="text-gray-700 dark:text-gray-300" :class="isExpired(selectedQuote.expiry_date) ? 'text-red-500' : ''">{{ formatDate(selectedQuote.expiry_date) }}</p>
            </div>
            <div>
              <p class="text-gray-400 dark:text-gray-500 text-xs mb-0.5">الحالة</p>
              <span :class="statusChip(selectedQuote.status)" class="px-2.5 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(selectedQuote.status) }}</span>
            </div>
            <div>
              <p class="text-gray-400 dark:text-gray-500 text-xs mb-0.5">الإجمالي</p>
              <p class="font-bold text-gray-900 dark:text-white">{{ Number(selectedQuote.total ?? 0).toFixed(2) }} ر.س</p>
            </div>
          </div>
          <div v-if="selectedQuote.notes" class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-1">ملاحظات</p>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ selectedQuote.notes }}</p>
          </div>
          <!-- Items -->
          <div v-if="selectedQuote.items?.length" class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400">
                <tr>
                  <th class="px-3 py-2 text-right font-medium">البند</th>
                  <th class="px-3 py-2 text-right font-medium">الكمية</th>
                  <th class="px-3 py-2 text-right font-medium">السعر</th>
                  <th class="px-3 py-2 text-right font-medium">الضريبة</th>
                  <th class="px-3 py-2 text-right font-medium">الإجمالي</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="(item, i) in selectedQuote.items" :key="i">
                  <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ item.name }}</td>
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ item.qty }}</td>
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ Number(item.unit_price).toFixed(2) }}</td>
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ item.tax_rate ?? 15 }}%</td>
                  <td class="px-3 py-2 font-semibold text-gray-800 dark:text-gray-100">{{ itemTotal(item).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="no-print px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex flex-wrap gap-2 justify-end shrink-0 bg-white dark:bg-gray-800 rounded-b-2xl">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            @click="printQuoteDetail"
          >
            <PrinterIcon class="w-4 h-4" />
            طباعة
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            @click="shareOrCopyQuote"
          >
            <ShareIcon class="w-4 h-4" />
            مشاركة / نسخ
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 border border-primary-200 dark:border-primary-800 rounded-xl text-sm text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-950/40"
            @click="downloadQuoteText"
          >
            <ArrowDownTrayIcon class="w-4 h-4" />
            حفظ كنص
          </button>
          <button
            type="button"
            class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            @click="viewModal = false"
          >
            إغلاق
          </button>
        </div>
      </div>
    </div>

    <!-- Create Quote Modal -->
    <div
      v-if="createModal"
      class="fixed inset-0 bg-black/40 dark:bg-black/60 z-50 flex items-center justify-center p-4"
      @click.self="createModal = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] flex flex-col" dir="rtl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">عرض سعر جديد</h3>
          <button @click="createModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>

        <form id="quote-create-form" class="overflow-y-auto flex-1 flex flex-col min-h-0" @submit.prevent="saveQuote">
          <div class="p-6 space-y-5 flex-1 overflow-y-auto">
            <!-- Customer -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">العميل <span class="text-red-500">*</span></label>
              <div class="flex gap-2 items-stretch">
                <select
                  v-model="form.customer_id"
                  required
                  class="flex-1 min-w-0 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-400"
                >
                  <option value="">-- اختر عميلاً --</option>
                  <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <button
                  type="button"
                  class="shrink-0 inline-flex items-center justify-center w-11 rounded-xl border border-primary-300 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-200"
                  title="إضافة عميل سريع"
                  aria-label="إضافة عميل سريع"
                  @click="openQuickCustomer"
                >
                  <UserPlusIcon class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تاريخ الإصدار <span class="text-red-500">*</span></label>
                <SmartDatePicker :model-value="form.issue_date" mode="single" @change="onIssueDateChange" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تاريخ الانتهاء</label>
                <SmartDatePicker :model-value="form.expiry_date" mode="single" @change="onExpiryDateChange" />
              </div>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ملاحظات</label>
              <textarea
                v-model="form.notes"
                rows="2"
                class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-400 resize-none"
                placeholder="ملاحظات اختيارية..."
              />
            </div>

            <!-- Items Table -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">البنود</label>
                <button
                  type="button"
                  class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 font-medium"
                  @click="addItem"
                >
                  <PlusIcon class="w-3.5 h-3.5" />
                  إضافة بند
                </button>
              </div>
              <div class="border border-gray-200 dark:border-gray-600 rounded-xl overflow-hidden">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400">
                    <tr>
                      <th class="px-3 py-2 text-right font-medium">البند</th>
                      <th class="px-3 py-2 text-right font-medium w-16">الكمية</th>
                      <th class="px-3 py-2 text-right font-medium w-24">سعر الوحدة</th>
                      <th class="px-3 py-2 text-right font-medium w-16">الضريبة%</th>
                      <th class="px-3 py-2 text-right font-medium w-20">الإجمالي</th>
                      <th class="px-3 py-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr v-for="(item, i) in form.items" :key="i" class="bg-white dark:bg-gray-800">
                      <td class="px-2 py-1.5">
                        <input
                          v-model="item.name"
                          placeholder="اسم البند"
                          class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-400"
                        />
                      </td>
                      <td class="px-2 py-1.5">
                        <input
                          v-model.number="item.qty"
                          type="number"
                          min="1"
                          class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-400 text-center"
                        />
                      </td>
                      <td class="px-2 py-1.5">
                        <input
                          v-model.number="item.unit_price"
                          type="number"
                          min="0"
                          step="0.01"
                          class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-400"
                        />
                      </td>
                      <td class="px-2 py-1.5">
                        <input
                          v-model.number="item.tax_rate"
                          type="number"
                          min="0"
                          max="100"
                          class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-400 text-center"
                        />
                      </td>
                      <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300 font-medium text-center">
                        {{ itemTotal(item).toFixed(2) }}
                      </td>
                      <td class="px-2 py-1.5 text-center">
                        <button
                          type="button"
                          class="text-red-400 hover:text-red-600 transition-colors"
                          :disabled="form.items.length <= 1"
                          @click="removeItem(i)"
                        >
                          <XMarkIcon class="w-3.5 h-3.5" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Totals -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 space-y-2">
              <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>المجموع الفرعي</span>
                <span>{{ subtotal.toFixed(2) }} ر.س</span>
              </div>
              <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>الضريبة</span>
                <span>{{ taxTotal.toFixed(2) }} ر.س</span>
              </div>
              <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>الخصم</span>
                <div class="flex items-center gap-1">
                  <span class="text-gray-400">−</span>
                  <input
                    v-model.number="form.discount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-20 border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-0.5 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-400 text-center"
                  />
                  <span class="text-xs text-gray-400">ر.س</span>
                </div>
              </div>
              <div class="border-t border-gray-200 dark:border-gray-600 pt-2 flex items-center justify-between font-bold text-gray-900 dark:text-white">
                <span>الإجمالي</span>
                <span>{{ grandTotal.toFixed(2) }} ر.س</span>
              </div>
            </div>

            <!-- Error -->
            <p v-if="formError" class="text-red-600 dark:text-red-400 text-sm bg-red-50 dark:bg-red-900/20 rounded-xl p-3">{{ formError }}</p>
          </div>

          <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex gap-3 justify-end flex-shrink-0 bg-white dark:bg-gray-800">
            <button
              type="button"
              class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              @click="createModal = false"
            >
              إلغاء
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors"
            >
              {{ saving ? 'جارٍ الحفظ...' : 'حفظ العرض' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- عميل سريع (عروض الأسعار) -->
    <Teleport to="body">
      <div
        v-if="quickCustomerOpen"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/45"
        role="dialog"
        aria-modal="true"
        @click.self="quickCustomerOpen = false"
      >
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-600 shadow-xl max-w-md w-full p-5 space-y-4" @click.stop>
          <h4 class="text-sm font-bold text-gray-900 dark:text-slate-100">إضافة عميل سريعة</h4>
          <div class="grid grid-cols-1 gap-3 text-sm">
            <div>
              <label class="block text-xs text-gray-500 mb-1">نوع العميل</label>
              <select v-model="quickCustomer.type" class="w-full px-3 py-2 border rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800">
                <option value="b2c">فرد (B2C)</option>
                <option value="b2b">شركة (B2B)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الاسم <span class="text-red-500">*</span></label>
              <input v-model="quickCustomer.name" class="w-full px-3 py-2 border rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800" placeholder="اسم العميل" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الجوال</label>
              <input v-model="quickCustomer.phone" class="w-full px-3 py-2 border rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800" placeholder="05xxxxxxxx" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">البريد</label>
              <input v-model="quickCustomer.email" type="email" class="w-full px-3 py-2 border rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800" placeholder="اختياري" />
            </div>
          </div>
          <p v-if="quickModalError" class="text-xs text-red-600">{{ quickModalError }}</p>
          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="px-3 py-2 text-sm border rounded-lg border-gray-300" @click="quickCustomerOpen = false">إلغاء</button>
            <button
              type="button"
              class="px-4 py-2 text-sm rounded-lg bg-primary-600 text-white disabled:opacity-50"
              :disabled="quickCustomerSaving || !quickCustomer.name.trim()"
              @click="submitQuickCustomer"
            >
              {{ quickCustomerSaving ? 'جارٍ الحفظ...' : 'حفظ واختيار' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  ArrowDownTrayIcon,
  DocumentTextIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  PrinterIcon,
  ShareIcon,
  UserPlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import apiClient from '@/lib/apiClient'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import { printDocument } from '@/composables/useAppPrint'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'

const { get, post, put, del } = useApi()
const toast = useToast()

const quotePrintRoot = ref<HTMLElement | null>(null)

// ─── State ────────────────────────────────────────────────────────────────────
const quotes    = ref<any[]>([])
const customers = ref<any[]>([])
const loading   = ref(false)
const saving    = ref(false)
const formError = ref('')

const filterStatus   = ref('')
const searchCustomer = ref('')
const dateFrom       = ref('')
const dateTo         = ref('')

const createModal = ref(false)
const viewModal   = ref(false)
const selectedQuote = ref<any>(null)

// ─── Stats definition ─────────────────────────────────────────────────────────
const stats = [
  { key: 'all',      label: 'إجمالي',            activeBg: 'bg-gray-700 dark:bg-gray-600',    activeText: 'text-white', badgeBg: 'bg-gray-600 text-white' },
  { key: 'draft',    label: 'مسودة',             activeBg: 'bg-gray-200 dark:bg-gray-600',    activeText: 'text-gray-800 dark:text-gray-100', badgeBg: 'bg-gray-400 text-white' },
  { key: 'sent',     label: 'مُرسل',             activeBg: 'bg-blue-100 dark:bg-blue-900/40', activeText: 'text-blue-700 dark:text-blue-300', badgeBg: 'bg-blue-500 text-white' },
  { key: 'accepted', label: 'مقبول',             activeBg: 'bg-green-100 dark:bg-green-900/40', activeText: 'text-green-700 dark:text-green-300', badgeBg: 'bg-green-500 text-white' },
  { key: 'expired',  label: 'منتهي الصلاحية',   activeBg: 'bg-orange-100 dark:bg-orange-900/40', activeText: 'text-orange-700 dark:text-orange-300', badgeBg: 'bg-orange-500 text-white' },
]

const statCounts = computed(() => {
  const counts: Record<string, number> = { all: quotes.value.length }
  for (const q of quotes.value) {
    counts[q.status] = (counts[q.status] ?? 0) + 1
  }
  return counts
})

// ─── Form ─────────────────────────────────────────────────────────────────────
interface QuoteItem {
  name: string
  qty: number
  unit_price: number
  tax_rate: number
}

interface QuoteForm {
  customer_id: string | number
  issue_date: string
  expiry_date: string
  notes: string
  discount: number
  items: QuoteItem[]
}

function blankForm(): QuoteForm {
  const today = new Date().toISOString().slice(0, 10)
  const next30 = new Date(Date.now() + 30 * 86400_000).toISOString().slice(0, 10)
  return {
    customer_id: '',
    issue_date: today,
    expiry_date: next30,
    notes: '',
    discount: 0,
    items: [{ name: '', qty: 1, unit_price: 0, tax_rate: 15 }],
  }
}

const form = ref<QuoteForm>(blankForm())

const quickCustomerOpen = ref(false)
const quickCustomerSaving = ref(false)
const quickModalError = ref('')
const quickCustomer = ref({
  type: 'b2c' as 'b2c' | 'b2b',
  name: '',
  phone: '',
  email: '',
})

function openQuickCustomer() {
  quickModalError.value = ''
  quickCustomer.value = { type: 'b2c', name: '', phone: '', email: '' }
  quickCustomerOpen.value = true
}

async function submitQuickCustomer() {
  quickModalError.value = ''
  quickCustomerSaving.value = true
  try {
    const { data } = await apiClient.post(
      '/customers',
      {
        type: quickCustomer.value.type,
        name: quickCustomer.value.name.trim(),
        phone: quickCustomer.value.phone.trim() || undefined,
        email: quickCustomer.value.email.trim() || undefined,
      },
      { skipGlobalErrorToast: true },
    )
    const c = data.data as { id: number; name?: string }
    customers.value.push(c)
    customers.value.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ar'))
    form.value.customer_id = c.id
    quickCustomerOpen.value = false
  } catch (e: unknown) {
    quickModalError.value = summarizeAxiosError(e)
  } finally {
    quickCustomerSaving.value = false
  }
}

// ─── Computed: items totals ───────────────────────────────────────────────────
function itemTotal(item: QuoteItem): number {
  const base = (item.qty ?? 0) * (item.unit_price ?? 0)
  const tax  = base * ((item.tax_rate ?? 0) / 100)
  return base + tax
}

const subtotal = computed(() =>
  form.value.items.reduce((s, i) => s + (i.qty ?? 0) * (i.unit_price ?? 0), 0)
)

const taxTotal = computed(() =>
  form.value.items.reduce((s, i) => {
    const base = (i.qty ?? 0) * (i.unit_price ?? 0)
    return s + base * ((i.tax_rate ?? 0) / 100)
  }, 0)
)

const grandTotal = computed(() =>
  Math.max(0, subtotal.value + taxTotal.value - (form.value.discount ?? 0))
)

// ─── Computed: filtered table rows ───────────────────────────────────────────
const filteredQuotes = computed(() => {
  let rows = quotes.value
  if (filterStatus.value) {
    rows = rows.filter(q => q.status === filterStatus.value)
  }
  if (searchCustomer.value) {
    const q = searchCustomer.value.toLowerCase()
    rows = rows.filter(r => r.customer?.name?.toLowerCase().includes(q))
  }
  if (dateFrom.value) {
    rows = rows.filter(r => r.issue_date >= dateFrom.value)
  }
  if (dateTo.value) {
    rows = rows.filter(r => r.issue_date <= dateTo.value)
  }
  return rows
})

// ─── Helpers ─────────────────────────────────────────────────────────────────
function statusLabel(s: string): string {
  const m: Record<string, string> = {
    draft: 'مسودة', sent: 'مُرسل', accepted: 'مقبول',
    rejected: 'مرفوض', expired: 'منتهي الصلاحية',
  }
  return m[s] ?? s
}

function statusChip(s: string): string {
  const m: Record<string, string> = {
    draft:    'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
    sent:     'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
    accepted: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
    rejected: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
    expired:  'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
  }
  return m[s] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-SA')
}

function isExpired(d: string): boolean {
  if (!d) return false
  return new Date(d) < new Date()
}

// ─── Items management ─────────────────────────────────────────────────────────
function addItem() {
  form.value.items.push({ name: '', qty: 1, unit_price: 0, tax_rate: 15 })
}

function removeItem(i: number) {
  if (form.value.items.length > 1) {
    form.value.items.splice(i, 1)
  }
}

// ─── Modal actions ────────────────────────────────────────────────────────────
function openCreateModal() {
  form.value = blankForm()
  formError.value = ''
  createModal.value = true
}

function onFilterDateRangeChange(val: { from: string; to: string }) {
  dateFrom.value = val.from
  dateTo.value = val.to
}

function onIssueDateChange(val: { from: string; to: string }) {
  form.value.issue_date = val.from || val.to
}

function onExpiryDateChange(val: { from: string; to: string }) {
  form.value.expiry_date = val.from || val.to
}

function buildQuoteShareText(q: any): string {
  const lines: string[] = []
  const num = q.quote_number ?? `#${q.id}`
  lines.push(`عرض سعر: ${num}`)
  lines.push(`العميل: ${q.customer?.name ?? '—'}`)
  lines.push(`الحالة: ${statusLabel(String(q.status ?? ''))}`)
  lines.push(`الإصدار: ${formatDate(q.issue_date)} — الانتهاء: ${formatDate(q.expiry_date)}`)
  lines.push(`الإجمالي: ${Number(q.total ?? 0).toFixed(2)} ر.س`)
  if (q.notes) lines.push(`ملاحظات: ${q.notes}`)
  const items = q.items as QuoteItem[] | undefined
  if (items?.length) {
    lines.push('')
    lines.push('البنود:')
    for (const it of items) {
      lines.push(
        `— ${it.name} | الكمية: ${it.qty} | سعر الوحدة: ${Number(it.unit_price).toFixed(2)} | الإجمالي: ${itemTotal(it).toFixed(2)} ر.س`,
      )
    }
  }
  lines.push('')
  lines.push('— مُصدَر من النظام')
  return lines.join('\n')
}

async function printQuoteDetail() {
  if (!quotePrintRoot.value) return
  try {
    await printDocument({ root: quotePrintRoot.value })
  } catch {
    window.print()
  }
}

async function shareOrCopyQuote() {
  const q = selectedQuote.value
  if (!q) return
  const text = buildQuoteShareText(q)
  if (typeof navigator !== 'undefined' && navigator.share) {
    try {
      await navigator.share({ title: `عرض سعر ${q.quote_number ?? q.id}`, text })
      return
    } catch (e: unknown) {
      if (e && typeof e === 'object' && (e as Error).name === 'AbortError') return
    }
  }
  try {
    await navigator.clipboard.writeText(text)
    toast.success('تم النسخ', 'تفاصيل العرض في الحافظة — يمكنك لصقها في بريد أو واتساب.')
  } catch {
    toast.error('تعذّر النسخ', 'انسخ النص يدوياً من ملف «حفظ كنص».')
  }
}

function downloadQuoteText() {
  const q = selectedQuote.value
  if (!q) return
  const text = buildQuoteShareText(q)
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' })
  const safe = String(q.quote_number ?? `quote-${q.id}`).replace(/[^\w.-]+/g, '_')
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `${safe}.txt`
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(a.href)
  toast.success('تم التنزيل', 'حُفظ الملف كنص يمكن إرساله أو أرشفته.')
}

async function viewQuote(q: any) {
  selectedQuote.value = q
  viewModal.value = true
  try {
    const res = await get(`/quotes/${q.id}`)
    const full = (res as any)?.data?.data ?? (res as any)?.data
    if (full && typeof full === 'object') selectedQuote.value = full
  } catch {
    /* اكتفِ ببيانات القائمة */
  }
}

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadQuotes() {
  loading.value = true
  try {
    const res = await get('/quotes')
    quotes.value = res?.data?.data ?? res?.data ?? res ?? []
  } catch {
    quotes.value = []
  } finally {
    loading.value = false
  }
}

async function loadCustomers() {
  try {
    const res = await get('/customers', { per_page: 500 })
    customers.value = res?.data?.data ?? res?.data ?? res ?? []
  } catch {
    customers.value = []
  }
}

async function saveQuote() {
  if (!form.value.customer_id) {
    formError.value = 'يرجى اختيار عميل'
    return
  }
  if (!form.value.issue_date) {
    formError.value = 'يرجى اختيار تاريخ الإصدار'
    return
  }

  const validItems = form.value.items
    .filter((i) => String(i.name ?? '').trim() !== '')
    .map((i) => ({
      name: String(i.name).trim(),
      quantity: Number(i.qty ?? 1),
      unit_price: Number(i.unit_price ?? 0),
      tax_rate: Number(i.tax_rate ?? 15),
    }))

  if (!validItems.length) {
    formError.value = 'أضف بندًا واحدًا على الأقل مع إدخال اسم البند وسعر الوحدة.'
    return
  }

  saving.value = true
  formError.value = ''
  try {
    const payload = {
      customer_id: form.value.customer_id,
      issue_date: form.value.issue_date,
      expiry_date: form.value.expiry_date,
      notes: form.value.notes,
      discount_amount: form.value.discount,
      total: grandTotal.value,
      items: validItems,
    }
    await post('/quotes', payload)
    await loadQuotes()
    createModal.value = false
  } catch (e: any) {
    const d = e?.response?.data
    const errs = d?.errors
    if (errs && typeof errs === 'object') {
      const firstKey = Object.keys(errs)[0]
      const firstVal = firstKey ? (errs as Record<string, string[]>)[firstKey] : undefined
      const fromErrors = Array.isArray(firstVal) ? firstVal[0] : undefined
      formError.value =
        String(fromErrors || d?.message || '').trim() || 'فشل الحفظ، يرجى المحاولة مجدداً'
    } else {
      formError.value = d?.message ?? 'فشل الحفظ، يرجى المحاولة مجدداً'
    }
  } finally {
    saving.value = false
  }
}

async function changeStatus(q: any, status: string) {
  try {
    await put(`/quotes/${q.id}`, { status })
    q.status = status
  } catch {
    // silent
  }
}

async function deleteQuote(q: any) {
  const ok = await appConfirm({
    title: 'حذف عرض السعر',
    message: `هل أنت متأكد من حذف عرض السعر ${q.quote_number ?? '#' + q.id}؟`,
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await del(`/quotes/${q.id}`)
    quotes.value = quotes.value.filter(r => r.id !== q.id)
  } catch {
    // silent
  }
}

onMounted(() => {
  loadQuotes()
  loadCustomers()
})
</script>
