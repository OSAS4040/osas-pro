<template>
  <div class="space-y-6">
    <NavigationSourceHint class="no-print" />
    <div class="no-print flex items-center justify-between">
      <div>
        <RouterLink to="/invoices" class="text-sm text-primary-600 hover:underline">← الفواتير</RouterLink>
        <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100 mt-1 font-mono">{{ invoice?.invoice_number ?? '…' }}</h2>
      </div>
      <div class="flex flex-col items-end gap-1">
        <div class="flex items-center gap-2">
          <span v-if="invoice" :class="invoiceStatusClass(invoice.status)" class="text-xs px-3 py-1 rounded-full font-medium">
            {{ invoiceStatusLabel(invoice.status) }}
          </span>
          <button v-if="invoice" class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors" @click="printInvoice">
            <PrinterIcon class="w-4 h-4" />
            <span class="hidden sm:inline">طباعة</span>
          </button>
          <button
            v-if="invoice"
            type="button"
            class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm bg-primary-600 text-white hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-60"
            :disabled="pdfExporting"
            @click="exportPDF"
          >
            <span
              v-if="pdfExporting"
              class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
              aria-hidden="true"
            />
            <ArrowDownTrayIcon v-else class="w-4 h-4" />
            <span class="hidden sm:inline">{{ pdfExporting ? 'جاري التصدير…' : 'PDF' }}</span>
            <span class="sm:hidden">{{ pdfExporting ? '…' : 'PDF' }}</span>
          </button>
          <button
            v-if="invoice && canRecordPayment"
            type="button"
            class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-emerald-600 text-white hover:bg-emerald-700 rounded-lg transition-colors"
            @click="openPayModal"
          >
            دفع الفاتورة
          </button>
        </div>
        <p v-if="invoice" class="text-[10px] text-gray-500 dark:text-slate-400 max-w-[18rem] text-right leading-snug">
          يُنشأ ملف PDF من الخادم. إن احتجت نسخة مطابقة لما تراه على الشاشة، استخدم «طباعة» ثم «حفظ كـ PDF».
        </p>
      </div>
    </div>

    <div v-if="loading && !invoice" class="no-print space-y-4 animate-pulse">
      <div class="h-24 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700" />
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="h-40 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700" />
      </div>
      <div class="h-48 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700" />
    </div>

    <template v-else-if="invoice">
      <!-- Financial snapshot -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 shadow-sm">
          <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">الحالة</p>
          <p class="mt-1 text-sm font-bold" :class="Number(invoice.due_amount) <= 0.0001 && invoice.status !== 'cancelled' ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-900 dark:text-slate-100'">
            {{ invoiceStatusLabel(invoice.status) }}
          </p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 shadow-sm">
          <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">الإجمالي</p>
          <p class="mt-1 text-lg font-bold text-primary-600 tabular-nums">{{ Number(invoice.total).toFixed(2) }} <span class="text-xs font-normal text-gray-500">ر.س</span></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 shadow-sm">
          <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">المدفوع</p>
          <p class="mt-1 text-lg font-bold text-emerald-600 tabular-nums">{{ Number(invoice.paid_amount).toFixed(2) }} <span class="text-xs font-normal text-gray-500">ر.س</span></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 shadow-sm ring-1 ring-amber-200/60 dark:ring-amber-900/40"
             :class="Number(invoice.due_amount) > 0 ? 'bg-amber-50/50 dark:bg-amber-950/20' : ''"
        >
          <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">المتبقي</p>
          <p class="mt-1 text-lg font-bold tabular-nums" :class="Number(invoice.due_amount) > 0 ? 'text-amber-800 dark:text-amber-200' : 'text-gray-400'">
            {{ Number(invoice.due_amount).toFixed(2) }} <span class="text-xs font-normal text-gray-500">ر.س</span>
          </p>
        </div>
      </div>

      <!-- Company Header (print-style) -->
      <div v-if="company" class="bg-white rounded-xl border border-gray-200 p-5 print:border-none print:rounded-none">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-4">
            <img v-if="company.logo_url" :src="company.logo_url" alt="logo" class="w-16 h-16 object-contain rounded-lg border border-gray-100 p-1" />
            <div v-else class="w-16 h-16 bg-primary-50 rounded-lg flex items-center justify-center">
              <BuildingOfficeIcon class="w-8 h-8 text-primary-400" />
            </div>
            <div>
              <p class="font-bold text-gray-900 text-lg">{{ company.name_ar || company.name }}</p>
              <p v-if="company.name_ar && company.name" class="text-xs text-gray-400">{{ company.name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ company.address }}</p>
            </div>
          </div>
          <div class="text-left text-xs text-gray-500 space-y-0.5">
            <p v-if="company.tax_number">الرقم الضريبي: <span class="font-mono font-semibold text-gray-700">{{ company.tax_number }}</span></p>
            <p v-if="company.cr_number">السجل التجاري: <span class="font-mono font-semibold text-gray-700">{{ company.cr_number }}</span></p>
            <p v-if="company.phone">{{ company.phone }}</p>
            <p v-if="company.email">{{ company.email }}</p>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- معلومات الفاتورة -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">الفاتورة</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">الرقم</dt><dd class="font-mono font-semibold">{{ invoice.invoice_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">التسلسل</dt><dd>#{{ invoice.invoice_counter }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">التاريخ</dt><dd>{{ formatDate(invoice.issued_at) }}</dd></div>
            <div v-if="invoice.due_at" class="flex justify-between"><dt class="text-gray-500">الاستحقاق</dt><dd>{{ formatDate(invoice.due_at) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">الفرع</dt><dd>{{ invoice.branch?.name }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">أنشأها</dt><dd>{{ invoice.created_by?.name }}</dd></div>
          </dl>
        </div>

        <!-- معلومات العميل -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">العميل</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">الاسم</dt><dd class="font-medium">{{ invoice.customer?.name ?? '—' }}</dd></div>
            <div v-if="invoice.vehicle" class="flex justify-between"><dt class="text-gray-500">المركبة</dt><dd class="font-mono">{{ invoice.vehicle.plate_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">النوع</dt><dd class="uppercase text-xs font-semibold text-primary-600">{{ invoice.customer_type }}</dd></div>
            <div v-if="invoice.source_type" class="flex justify-between"><dt class="text-gray-500">المصدر</dt><dd class="text-xs text-gray-500">{{ sourceLabel }}</dd></div>
          </dl>
        </div>

        <!-- المبالغ -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">المبالغ</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">المجموع قبل الضريبة</dt><dd>{{ Number(invoice.subtotal).toFixed(2) }} ر.س</dd></div>
            <div v-if="Number(invoice.discount_amount) > 0" class="flex justify-between text-green-600"><dt>الخصم</dt><dd>-{{ Number(invoice.discount_amount).toFixed(2) }} ر.س</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">الضريبة</dt><dd>{{ Number(invoice.tax_amount).toFixed(2) }} ر.س</dd></div>
            <div class="flex justify-between font-bold border-t border-gray-100 pt-2"><dt>الإجمالي</dt><dd class="text-primary-600">{{ Number(invoice.total).toFixed(2) }} ر.س</dd></div>
            <div class="flex justify-between text-green-600"><dt>المدفوع</dt><dd>{{ Number(invoice.paid_amount).toFixed(2) }} ر.س</dd></div>
            <div class="flex justify-between" :class="Number(invoice.due_amount) > 0 ? 'text-red-500 font-bold' : 'text-gray-400'">
              <dt>المتبقي</dt><dd>{{ Number(invoice.due_amount).toFixed(2) }} ر.س</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- البنود -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100"><h3 class="font-medium text-gray-800">بنود الفاتورة</h3></div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-right">البند</th>
              <th class="px-4 py-3 text-right">النوع</th>
              <th class="px-4 py-3 text-right">الكمية</th>
              <th class="px-4 py-3 text-right">سعر الوحدة</th>
              <th class="px-4 py-3 text-right">الضريبة</th>
              <th class="px-4 py-3 text-right">الإجمالي</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!invoice.items?.length">
              <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-slate-400 text-sm">
                لا بنود مسجلة على هذه الفاتورة
              </td>
            </tr>
            <tr v-for="item in invoice.items" :key="item.id">
              <td class="px-4 py-3 text-right">
                <p class="font-medium">{{ item.name }}</p>
                <p v-if="item.description" class="text-xs text-gray-400">{{ item.description }}</p>
              </td>
              <td class="px-4 py-3 text-right text-gray-500">{{ item.service_id ? 'خدمة' : item.product_id ? 'منتج' : '—' }}</td>
              <td class="px-4 py-3 text-right">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right">{{ Number(item.unit_price).toFixed(2) }}</td>
              <td class="px-4 py-3 text-right">{{ Number(item.tax_amount).toFixed(2) }}</td>
              <td class="px-4 py-3 text-right font-semibold">{{ Number(item.line_total).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- المدفوعات (مدمجة في GET /invoices/{id}) -->
      <div v-if="invoice" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between bg-gray-50/50 dark:bg-slate-800/50">
          <h3 class="font-semibold text-gray-800 dark:text-slate-100">المدفوعات</h3>
          <button type="button" class="text-xs font-medium text-primary-600 hover:underline disabled:opacity-50 inline-flex items-center gap-1" :disabled="refreshing" @click="load">
            <span v-if="refreshing" class="inline-block w-3 h-3 border-2 border-primary-200 border-t-primary-600 rounded-full animate-spin" />
            {{ refreshing ? 'جارٍ التحديث' : 'تحديث' }}
          </button>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-right">طريقة الدفع</th>
              <th class="px-4 py-3 text-right">المرجع</th>
              <th class="px-4 py-3 text-right">التاريخ</th>
              <th class="px-4 py-3 text-right">المبلغ</th>
              <th class="px-4 py-3 text-right">الحالة</th>
              <th class="px-4 py-3 text-right w-28">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!paymentsList.length">
              <td colspan="6" class="px-5 py-10 text-center">
                <p class="text-sm text-gray-500 dark:text-slate-400">لا توجد مدفوعات مسجلة بعد</p>
                <p v-if="canRecordPayment" class="text-xs text-gray-400 mt-1">استخدم «دفع الفاتورة» لتسجيل تحصيل نقدي أو من المحفظة</p>
              </td>
            </tr>
            <tr v-for="p in paymentsList" :key="p.id">
              <td class="px-4 py-3 text-right">{{ paymentMethodLabel(p.method) }}</td>
              <td class="px-4 py-3 text-right text-gray-500 font-mono text-xs">{{ p.reference ?? '—' }}</td>
              <td class="px-4 py-3 text-right text-gray-500 text-xs">{{ formatDate(p.created_at) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-green-600">{{ Number(p.amount).toFixed(2) }} ر.س</td>
              <td class="px-4 py-3 text-right">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="paymentStatusClass(p.status)">{{ paymentStatusLabel(p.status) }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="canRefundPayment(p)"
                  type="button"
                  class="text-xs font-medium text-amber-700 hover:underline disabled:opacity-50"
                  :disabled="refundSubmittingId === p.id"
                  @click="submitRefund(p)"
                >
                  {{ refundSubmittingId === p.id ? 'جارٍ الاسترداد…' : 'استرداد' }}
                </button>
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pay modal -->
      <Teleport to="body">
        <div
          v-if="payModal.open"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          dir="rtl"
          @click.self="payModal.open = false"
        >
          <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4 border border-gray-100 dark:border-slate-700">
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">تسجيل دفع</h3>
              <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                المتبقي:
                <strong class="text-gray-900 dark:text-slate-100 tabular-nums">{{ Number(invoice?.due_amount ?? 0).toFixed(2) }} ر.س</strong>
              </p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">طريقة الدفع</label>
              <select
                v-model="payForm.method"
                class="w-full border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500"
              >
                <option value="cash">نقدي</option>
                <option value="wallet">محفظة العميل</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">المبلغ (ر.س)</label>
              <input
                v-model.number="payForm.amount"
                type="number"
                min="0.01"
                step="0.01"
                class="w-full border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2.5 text-sm tabular-nums focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div v-if="payForm.method !== 'cash'">
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">مرجع (اختياري)</label>
              <input
                v-model="payForm.reference"
                type="text"
                class="w-full border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2.5 text-sm"
                placeholder="رقم المرجع أو ملاحظة داخلية"
              />
            </div>
            <p v-if="payForm.method === 'wallet'" class="text-xs text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-900/30 rounded-lg px-3 py-2">
              يُخصم من محفظة العميل المرتبطة بالفاتورة. يتطلب مفتاح تزامن (يُدار تلقائياً).
            </p>
            <p v-if="payForm.method === 'wallet' && !invoice?.customer_id" class="text-sm text-red-600 dark:text-red-400">لا يمكن الدفع من المحفظة بدون عميل مرتبط بالفاتورة.</p>
            <p v-if="payError" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg px-3 py-2">{{ payError }}</p>
            <div class="flex gap-2 justify-end pt-2 border-t border-gray-100 dark:border-slate-700">
              <button
                type="button"
                class="px-4 py-2.5 text-sm border border-gray-200 dark:border-slate-600 rounded-xl text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700"
                @click="payModal.open = false"
              >
                إلغاء
              </button>
              <button
                type="button"
                class="px-4 py-2.5 text-sm font-semibold bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 shadow-sm"
                :disabled="paySubmitting || (payForm.method === 'wallet' && !invoice?.customer_id)"
                @click="submitPay"
              >
                {{ paySubmitting ? 'جارٍ التسجيل…' : 'تأكيد الدفع' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ══════ الفاتورة الناطقة ══════ -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
              <VideoCameraIcon class="w-4 h-4 text-indigo-600" />
            </div>
            <div>
              <h3 class="text-sm font-semibold text-gray-900">الفاتورة الناطقة</h3>
              <p class="text-xs text-gray-400">صور قبل/بعد + فيديو شرح + توقيع الفني</p>
            </div>
          </div>
          <button class="text-xs text-primary-600 hover:underline"
                  @click="talkingInvoice.open = !talkingInvoice.open"
          >
            {{ talkingInvoice.open ? 'إخفاء' : 'إدارة الوسائط' }}
          </button>
        </div>

        <!-- ZATCA QR + Experience QR Strip -->
        <div class="px-5 py-4 bg-gradient-to-r from-emerald-50 to-indigo-50 dark:from-emerald-900/20 dark:to-indigo-900/20 border-b flex items-center justify-between gap-4">
          <!-- ZATCA QR -->
          <div class="flex items-center gap-3">
            <div class="bg-white p-1.5 rounded-lg shadow-sm">
              <img v-if="zatcaQRUrl" :src="zatcaQRUrl" class="w-16 h-16" alt="ZATCA QR" />
              <div v-else class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center text-[9px] text-gray-400">جاري...</div>
            </div>
            <div>
              <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">QR الفاتورة (هيئة الزكاة)</p>
              <p class="text-[10px] text-gray-500 mt-0.5">ملزم بموجب لوائح هيئة الزكاة</p>
              <p class="text-[9px] text-gray-400 font-mono mt-0.5 max-w-[140px] truncate">{{ invoice?.invoice_hash?.slice(0,20) }}...</p>
            </div>
          </div>
          <!-- Experience QR + Share -->
          <div class="flex items-center gap-3">
            <div>
              <p class="text-xs font-bold text-indigo-700 dark:text-indigo-300">QR تجربة العميل</p>
              <p class="text-[10px] text-gray-500 mt-0.5">صور + تقرير + تقييم</p>
            </div>
            <div class="bg-white p-1.5 rounded-lg shadow-sm">
              <img v-if="experienceQRUrl" :src="experienceQRUrl" class="w-16 h-16" alt="Experience QR" />
              <div v-else class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center text-[9px] text-gray-400">جاري...</div>
            </div>
            <button type="button"
                    class="flex items-center gap-1.5 text-xs border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 bg-white dark:bg-slate-800 px-3 py-2 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors font-medium"
                    title="نسخ رابط الفاتورة العام"
                    @click="copyInvoiceUrl"
            >
              نسخ الرابط
            </button>
            <ShareModal
              :url="invoicePublicUrl"
              :title="`فاتورة رقم ${invoice?.invoice_number || invoice?.id}`"
              label="الفاتورة"
              :phone="invoice?.customer?.phone"
              :email="invoice?.customer?.email"
              :message="`فاتورتك جاهزة من ${invoice?.company?.name_ar || 'مركز الخدمة'}:`"
              entity-type="invoice"
              :entity-id="invoice?.id"
            >
              <template #default="{ open }">
                <button class="flex items-center gap-1.5 text-xs bg-indigo-600 text-white px-3 py-2 rounded-xl hover:bg-indigo-700 transition-colors font-medium"
                        @click="open"
                >
                  <ShareIcon class="w-3.5 h-3.5" />
                  مشاركة
                </button>
              </template>
            </ShareModal>
          </div>
        </div>

        <!-- Media Management Panel -->
        <div v-if="talkingInvoice.open" class="p-5 space-y-5">
          <!-- Before / After Photos -->
          <div>
            <p class="text-sm font-medium text-gray-700 mb-3">صور قبل / بعد الخدمة</p>
            <div class="grid grid-cols-2 gap-3">
              <!-- Before -->
              <div>
                <p class="text-xs text-gray-400 mb-1.5 flex items-center gap-1">
                  <span class="w-4 h-4 bg-orange-100 text-orange-600 rounded text-[10px] flex items-center justify-center font-bold">ق</span> قبل
                </p>
                <div class="grid grid-cols-2 gap-1.5">
                  <div v-for="(img, i) in talkingInvoice.beforePhotos" :key="i"
                       class="aspect-square rounded-lg overflow-hidden bg-gray-100 relative group"
                  >
                    <img :src="img.url" class="w-full h-full object-cover" />
                    <button class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center" @click="removePhoto('before', i)">
                      <XMarkIcon class="w-5 h-5 text-white" />
                    </button>
                  </div>
                  <label class="aspect-square rounded-lg border-2 border-dashed border-gray-200 hover:border-primary-400 cursor-pointer flex items-center justify-center transition-colors bg-gray-50">
                    <PlusIcon class="w-6 h-6 text-gray-300" />
                    <input type="file" accept="image/*" multiple class="hidden" @change="e => addPhotos('before', e)" />
                  </label>
                </div>
              </div>
              <!-- After -->
              <div>
                <p class="text-xs text-gray-400 mb-1.5 flex items-center gap-1">
                  <span class="w-4 h-4 bg-green-100 text-green-600 rounded text-[10px] flex items-center justify-center font-bold">ب</span> بعد
                </p>
                <div class="grid grid-cols-2 gap-1.5">
                  <div v-for="(img, i) in talkingInvoice.afterPhotos" :key="i"
                       class="aspect-square rounded-lg overflow-hidden bg-gray-100 relative group"
                  >
                    <img :src="img.url" class="w-full h-full object-cover" />
                    <button class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center" @click="removePhoto('after', i)">
                      <XMarkIcon class="w-5 h-5 text-white" />
                    </button>
                  </div>
                  <label class="aspect-square rounded-lg border-2 border-dashed border-gray-200 hover:border-green-400 cursor-pointer flex items-center justify-center transition-colors bg-gray-50">
                    <PlusIcon class="w-6 h-6 text-gray-300" />
                    <input type="file" accept="image/*" multiple class="hidden" @change="e => addPhotos('after', e)" />
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Video Explanation -->
          <div>
            <p class="text-sm font-medium text-gray-700 mb-2">فيديو شرح الخدمة</p>
            <div v-if="talkingInvoice.videoUrl" class="rounded-xl overflow-hidden bg-black aspect-video mb-2">
              <video :src="talkingInvoice.videoUrl" controls class="w-full h-full"></video>
            </div>
            <div class="flex gap-2">
              <label class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 cursor-pointer transition-colors">
                <VideoCameraIcon class="w-4 h-4 text-gray-500" />
                رفع فيديو
                <input type="file" accept="video/*" class="hidden" @change="onVideoUpload" />
              </label>
              <div class="flex-1">
                <input v-model="talkingInvoice.videoLink" placeholder="أو أدخل رابط يوتيوب / Vimeo..." class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" />
              </div>
            </div>
          </div>

          <!-- Technician Voice Note -->
          <div>
            <p class="text-sm font-medium text-gray-700 mb-2">ملاحظة صوتية من الفني</p>
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
              <button class="w-10 h-10 rounded-full flex items-center justify-center transition-colors flex-shrink-0"
                      :class="talkingInvoice.recording ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'"
                      @click="toggleRecording"
              >
                <MicrophoneIcon class="w-5 h-5" />
              </button>
              <div class="flex-1">
                <p v-if="talkingInvoice.recording" class="text-xs text-red-600 animate-pulse font-medium">● جارٍ التسجيل...</p>
                <p v-else-if="talkingInvoice.audioUrl" class="text-xs text-green-600 font-medium">✓ تم التسجيل</p>
                <p v-else class="text-xs text-gray-400">اضغط للتسجيل الصوتي (حتى 2 دقيقة)</p>
              </div>
              <audio v-if="talkingInvoice.audioUrl" :src="talkingInvoice.audioUrl" controls class="h-8"></audio>
            </div>
          </div>

          <!-- Save Media -->
          <button :disabled="savingMedia" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                  @click="saveMedia"
          >
            {{ savingMedia ? 'جارٍ الحفظ...' : 'حفظ الوسائط وتفعيل الفاتورة الناطقة' }}
          </button>
        </div>

        <!-- Media Preview (if already saved) -->
        <div v-if="!talkingInvoice.open && (talkingInvoice.beforePhotos.length || talkingInvoice.afterPhotos.length)" class="px-5 py-4">
          <div class="flex gap-2 overflow-x-auto pb-1">
            <div v-for="(img, i) in [...talkingInvoice.beforePhotos, ...talkingInvoice.afterPhotos].slice(0,6)" :key="i"
                 class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0"
            >
              <img :src="img.url" class="w-full h-full object-cover" />
            </div>
            <div v-if="talkingInvoice.videoUrl" class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
              <VideoCameraIcon class="w-5 h-5 text-indigo-600" />
            </div>
            <div v-if="talkingInvoice.audioUrl" class="w-12 h-12 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
              <MicrophoneIcon class="w-5 h-5 text-primary-600" />
            </div>
          </div>
        </div>
      </div>

      <!-- ══════ التوقيع والختم ══════ -->
      <div v-if="company?.signature_url || company?.stamp_url || invoiceSettings.show_signature || invoiceSettings.show_stamp"
           class="bg-white rounded-xl border border-gray-200"
      >
        <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
          <PencilSquareIcon class="w-4 h-4 text-gray-400" />
          <h3 class="text-sm font-semibold text-gray-700">التوقيع والختم الرسمي</h3>
        </div>
        <div class="p-5">
          <div class="flex items-end justify-between">
            <!-- Left: Stamp + Signature stacked -->
            <div class="flex flex-col items-center gap-2">
              <!-- Stamp -->
              <div v-if="company?.stamp_url" class="h-20 flex items-center justify-center">
                <img :src="company.stamp_url" class="max-h-full max-w-[100px] object-contain opacity-90" alt="ختم الشركة" />
              </div>
              <!-- Signature -->
              <div class="border-b-2 border-gray-400 pb-1 min-w-[140px] flex items-end justify-center">
                <img v-if="company?.signature_url" :src="company.signature_url" class="h-12 max-w-[140px] object-contain" alt="التوقيع" />
                <div v-else class="h-12 w-40 flex items-end justify-center text-xs text-gray-300">_______________</div>
              </div>
              <p class="text-xs text-gray-500 text-center">{{ company?.name_ar || company?.name || 'المفوَّض بالتوقيع' }}</p>
            </div>

            <!-- Right: Bank info if enabled -->
            <div v-if="invoiceSettings.show_bank_details && (company?.iban || company?.bank_name)" class="text-left">
              <p class="text-xs font-semibold text-gray-600 mb-1">بيانات التحويل البنكي</p>
              <p v-if="company?.bank_name" class="text-xs text-gray-500">البنك: {{ company.bank_name }}</p>
              <p v-if="company?.iban" class="text-xs font-mono text-gray-700 mt-0.5">{{ company.iban }}</p>
            </div>
          </div>

          <!-- Footer Note -->
          <div v-if="invoiceSettings.footer_note" class="mt-4 pt-4 border-t border-gray-100 text-center text-xs text-gray-400">
            {{ invoiceSettings.footer_note }}
          </div>
        </div>
      </div>

      <!-- ZATCA + Barcode -->
      <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
        <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">سلامة الفاتورة (ZATCA)</h3>
        <dl class="space-y-1 text-xs font-mono text-gray-500">
          <div class="flex gap-2"><dt class="text-gray-400 shrink-0">Hash الفاتورة:</dt><dd class="truncate">{{ invoice.invoice_hash }}</dd></div>
          <div class="flex gap-2"><dt class="text-gray-400 shrink-0">Hash السابق:</dt><dd class="truncate">{{ invoice.previous_invoice_hash }}</dd></div>
        </dl>
        <!-- Barcode strip: renders invoice number as simple bars -->
        <div class="mt-3 pt-3 border-t border-gray-200 flex flex-col items-center gap-1">
          <canvas ref="barcodeCanvas" class="h-12 w-full max-w-xs"></canvas>
          <p class="text-[10px] font-mono text-gray-400">{{ invoice.invoice_number }}</p>
        </div>
      </div>
    </template>

    <!-- قالب طباعة / PDF: يُنقل إلى body لتفادي صفحة ثانية فارغة (ارتفاع #app + min-h-screen) -->
    <Teleport to="body">
      <section
        id="invoice-print-template"
        class="invoice-print-only print-container invoice-formal-print"
        dir="rtl"
      >
        <div
          class="formal-sheet"
          :style="{ '--inv-accent': invoiceSettings.print_primary_color || '#5b21b6' }"
        >
          <header class="formal-issuer-bar">
            <div class="formal-issuer-grid">
              <div class="formal-issuer-en" dir="ltr">
                <div class="formal-doc-type">Tax Invoice</div>
                <p class="formal-co-name">{{ printCompanyNameEn }}</p>
                <div class="formal-issuer-lines">
                  <p v-if="company?.address">{{ company.address }}</p>
                  <p v-if="company?.tax_number || company?.vat_number">
                    VAT: {{ company?.tax_number || company?.vat_number }}
                  </p>
                  <p v-if="company?.cr_number">CR: {{ company.cr_number }}</p>
                  <p v-if="company?.phone">{{ company.phone }}</p>
                </div>
              </div>
              <div class="formal-issuer-logo-mid">
                <div v-if="invoiceSettings.show_logo" class="formal-logo-on-brand">
                  <img v-if="company?.logo_url" :src="company.logo_url" alt="" />
                  <div v-else class="formal-logo-fallback" aria-hidden="true">
                    {{ printLogoMonogram }}
                  </div>
                </div>
              </div>
              <div class="formal-issuer-ar" dir="rtl">
                <div class="formal-doc-type">فاتورة ضريبية</div>
                <p class="formal-co-name">{{ printCompanyNameAr }}</p>
                <div class="formal-issuer-lines">
                  <p v-if="company?.address">{{ company.address }}</p>
                  <p v-if="company?.tax_number || company?.vat_number">
                    الرقم الضريبي: {{ company?.tax_number || company?.vat_number }}
                  </p>
                  <p v-if="company?.cr_number">السجل التجاري: {{ company.cr_number }}</p>
                  <p v-if="company?.phone">{{ company.phone }}</p>
                </div>
              </div>
            </div>
          </header>

          <p class="formal-invoice-no">{{ invoice?.invoice_number || '—' }}</p>
          <p v-if="invoiceSettings.print_header_note" class="formal-header-note">{{ invoiceSettings.print_header_note }}</p>

          <div class="formal-section">
            <div class="formal-section-head">
              <span dir="ltr">Invoice details</span>
              <span>تفاصيل الفاتورة</span>
            </div>
            <table class="formal-kv">
              <tbody>
                <tr>
                  <th>
                    رقم الفاتورة
                    <span class="k-en">Invoice #</span>
                  </th>
                  <td class="font-mono">{{ invoice?.invoice_number || '—' }}</td>
                </tr>
                <tr>
                  <th>
                    تاريخ الإصدار
                    <span class="k-en">Issue date</span>
                  </th>
                  <td>{{ formatInvoiceDateOnly(invoice?.issued_at || '') }}</td>
                </tr>
                <tr>
                  <th>
                    وقت الإصدار
                    <span class="k-en">Issue time</span>
                  </th>
                  <td dir="ltr" style="text-align: right">{{ formatInvoiceTimeOnly(invoice?.issued_at || '') }}</td>
                </tr>
                <tr>
                  <th>
                    تاريخ الاستحقاق
                    <span class="k-en">Due date</span>
                  </th>
                  <td>{{ formatInvoiceDateOnly(invoice?.due_at || invoice?.issued_at || '') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="formal-section">
            <div class="formal-section-head">
              <span dir="ltr">Customer</span>
              <span>معلومات العميل</span>
            </div>
            <table class="formal-kv">
              <tbody>
                <tr>
                  <th>
                    اسم العميل
                    <span class="k-en">Customer name</span>
                  </th>
                  <td>{{ invoice?.customer?.name || '—' }}</td>
                </tr>
                <tr>
                  <th>
                    العنوان
                    <span class="k-en">Address</span>
                  </th>
                  <td>{{ invoice?.customer?.address || 'المملكة العربية السعودية' }}</td>
                </tr>
                <tr>
                  <th>
                    الهاتف
                    <span class="k-en">Phone</span>
                  </th>
                  <td>{{ invoice?.customer?.phone || '—' }}</td>
                </tr>
                <tr v-if="invoice?.vehicle?.plate_number">
                  <th>
                    رقم اللوحة
                    <span class="k-en">Vehicle plate</span>
                  </th>
                  <td class="font-mono">{{ invoice.vehicle.plate_number }}</td>
                </tr>
                <tr>
                  <th>
                    السجل التجاري
                    <span class="k-en">CR</span>
                  </th>
                  <td>{{ invoice?.customer?.cr_number || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="formal-lines-wrap">
            <table class="formal-lines">
              <thead>
                <tr>
                  <th style="width: 3%">#</th>
                  <th style="width: 36%">وصف الخدمة / Description</th>
                  <th style="width: 7%">كمية<br />Qty</th>
                  <th style="width: 9%">سعر<br />Price</th>
                  <th style="width: 12%">خاضع للضريبة<br />Taxable</th>
                  <th style="width: 12%">ضريبة<br />VAT</th>
                  <th style="width: 12%">الإجمالي<br />Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in (invoice?.items || [])" :key="item.id || idx">
                  <td class="col-num">{{ idx + 1 }}</td>
                  <td class="col-desc">{{ item.name }}</td>
                  <td class="col-num">{{ Number(item.quantity || 0).toFixed(0) }}</td>
                  <td class="col-num">{{ Number(item.unit_price || 0).toFixed(2) }}</td>
                  <td class="col-num">{{ Number(item.subtotal ?? item.line_total ?? item.total ?? 0).toFixed(2) }}</td>
                  <td class="col-num">{{ Number(item.tax_amount || 0).toFixed(2) }}</td>
                  <td class="col-num">{{ Number(item.line_total ?? item.total ?? 0).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="formal-totals-wrap">
            <div class="formal-qr">
              <img v-if="zatcaQRUrl" :src="zatcaQRUrl" alt="QR" />
            </div>
            <table class="formal-totals">
              <tr>
                <th>المجموع الفرعي <span dir="ltr">Subtotal</span></th>
                <td>{{ Number(invoice?.subtotal || 0).toFixed(2) }} ر.س</td>
              </tr>
              <tr v-if="Number(invoice?.discount_amount) > 0">
                <th>الخصم <span dir="ltr">Discount</span></th>
                <td>-{{ Number(invoice?.discount_amount || 0).toFixed(2) }} ر.س</td>
              </tr>
              <tr>
                <th>ضريبة القيمة المضافة <span dir="ltr">VAT</span></th>
                <td>{{ Number(invoice?.tax_amount || 0).toFixed(2) }} ر.س</td>
              </tr>
              <tr class="grand">
                <th>الإجمالي شامل الضريبة <span dir="ltr">Total inc. VAT</span></th>
                <td>{{ Number(invoice?.total || 0).toFixed(2) }} ر.س</td>
              </tr>
            </table>
          </div>

          <p class="formal-thanks">شكراً لكم · Thank you</p>
          <p class="formal-brand-line">صُدرت عبر نظام <strong>أسس برو</strong></p>
          <div class="formal-footer-row">
            <span>{{ printCompanyNameAr }}</span>
            <span class="font-mono" dir="ltr">{{ invoice?.invoice_number || '' }}</span>
          </div>
          <p v-if="invoiceSettings.footer_note" class="formal-footer-note">{{ invoiceSettings.footer_note }}</p>
        </div>
      </section>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, watch, nextTick } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  BuildingOfficeIcon, VideoCameraIcon, MicrophoneIcon, XMarkIcon, PlusIcon,
  PencilSquareIcon, ShareIcon, PrinterIcon, ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'
import apiClient, { withIdempotency } from '@/lib/apiClient'
import { v4 as uuidv4 } from 'uuid'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import { printDocument } from '@/composables/useAppPrint'
import ShareModal from '@/components/ShareModal.vue'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import { getZatcaQRUrl, getQRImageUrl } from '@/utils/zatca'
import {
  invoiceStatusClass,
  invoiceStatusLabel,
  paymentMethodLabel,
  paymentStatusClass,
  paymentStatusLabel,
} from '@/utils/financialLabels'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import {
  invoicePrintCompanyDisplayName,
  invoicePrintLogoMonogram,
} from '@/utils/invoicePrintDisplay'

const route   = useRoute()
const id      = Number(route.params.id)
const invoice = ref<any>(null)
const company = ref<any>(null)
const loading = ref(false)
const refreshing = ref(false)
const auth    = useAuthStore()
const toast   = useToast()
const pdfExporting = ref(false)

const printCompanyNameEn = computed(() => invoicePrintCompanyDisplayName(company.value, 'en'))
const printCompanyNameAr = computed(() => invoicePrintCompanyDisplayName(company.value, 'ar'))
const printLogoMonogram = computed(() => invoicePrintLogoMonogram(company.value))

const paymentsList = computed(() => {
  const p = invoice.value?.payments
  return Array.isArray(p) ? p : []
})
const payModal = reactive({ open: false })
const payForm  = reactive({ method: 'cash' as 'cash' | 'wallet', amount: 0 as number, reference: '' })
const payError = ref('')
const paySubmitting = ref(false)
const refundSubmittingId = ref<number | null>(null)

const barcodeCanvas = ref<HTMLCanvasElement | null>(null)

const canRecordPayment = computed(() => {
  const inv = invoice.value
  if (!inv) return false
  if (['cancelled', 'draft', 'refunded'].includes(String(inv.status))) return false
  return Number(inv.due_amount) > 0.0001
})

// ZATCA QR — TLV encoded as per heza.gov.sa requirements
const zatcaQRUrl = computed(() => {
  if (!invoice.value) return ''
  const co = invoice.value.company ?? company.value
  return getZatcaQRUrl({
    sellerName:   invoicePrintCompanyDisplayName(co, 'ar') || 'مركز الخدمة',
    vatNumber:    co?.tax_number ?? co?.vat_number ?? '000000000000000',
    invoiceDate:  invoice.value.created_at ?? new Date().toISOString(),
    totalWithVat: parseFloat(invoice.value.total ?? '0'),
    vatAmount:    parseFloat(invoice.value.tax_amount ?? invoice.value.vat_amount ?? '0'),
  }, 150)
})

// Experience QR — links to public invoice review page
const invoicePublicUrl = computed(() =>
  `${window.location.origin}/public/invoice/${invoice.value?.uuid ?? id}`
)
const experienceQRUrl = computed(() =>
  invoice.value ? getQRImageUrl(invoicePublicUrl.value, 150) : ''
)

function drawBarcode(text: string) {
  const canvas = barcodeCanvas.value
  if (!canvas || !text) return
  const W = canvas.clientWidth || 300
  const H = 48
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')!
  ctx.clearRect(0, 0, W, H)
  // Simple Code39-style barcode using char code based bars
  const chars = text.replace(/[^A-Z0-9. -]/gi, '').toUpperCase()
  const totalBars = chars.length * 9 + 5
  const barW = Math.max(1, Math.floor(W / totalBars))
  let x = 2
  ctx.fillStyle = '#1a1a1a'
  for (const ch of chars) {
    const code = ch.charCodeAt(0)
    for (let b = 0; b < 9; b++) {
      if ((code >> b) & 1) {
        ctx.fillRect(x, 2, barW, H - 6)
      }
      x += barW + (b % 3 === 2 ? 1 : 0)
    }
    x += 2
  }
}

watch(invoice, async (val) => {
  if (val?.invoice_number) {
    await nextTick()
    drawBarcode(val.invoice_number)
  }
}, { immediate: true })



// ── Talking Invoice State ──
const talkingInvoice = reactive({
  open: false,
  beforePhotos: [] as { url: string; file?: File }[],
  afterPhotos:  [] as { url: string; file?: File }[],
  videoUrl:   '',
  videoLink:  '',
  audioUrl:   '',
  recording:  false,
})
const savingMedia   = ref(false)
let mediaRecorder: MediaRecorder | null = null
let audioChunks:   Blob[] = []

function addPhotos(type: 'before' | 'after', e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  const arr = type === 'before' ? talkingInvoice.beforePhotos : talkingInvoice.afterPhotos
  Array.from(files).forEach(f => arr.push({ url: URL.createObjectURL(f), file: f }))
}

function removePhoto(type: 'before' | 'after', i: number) {
  const arr = type === 'before' ? talkingInvoice.beforePhotos : talkingInvoice.afterPhotos
  arr.splice(i, 1)
}

function onVideoUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  talkingInvoice.videoUrl = URL.createObjectURL(file)
}

async function toggleRecording() {
  if (talkingInvoice.recording) {
    mediaRecorder?.stop()
    talkingInvoice.recording = false
    return
  }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    mediaRecorder = new MediaRecorder(stream)
    audioChunks = []
    mediaRecorder.ondataavailable = e => audioChunks.push(e.data)
    mediaRecorder.onstop = () => {
      const blob = new Blob(audioChunks, { type: 'audio/webm' })
      talkingInvoice.audioUrl = URL.createObjectURL(blob)
      stream.getTracks().forEach(t => t.stop())
    }
    mediaRecorder.start()
    talkingInvoice.recording = true
    setTimeout(() => { if (talkingInvoice.recording) mediaRecorder?.stop() }, 120000)
  } catch { toast.error('تعذر الوصول للميكروفون') }
}

async function saveMedia() {
  savingMedia.value = true
  try {
    const fd = new FormData()
    talkingInvoice.beforePhotos.forEach((p, i) => { if (p.file) fd.append(`before[${i}]`, p.file) })
    talkingInvoice.afterPhotos.forEach((p, i)  => { if (p.file) fd.append(`after[${i}]`, p.file) })
    if (talkingInvoice.videoLink) fd.append('video_link', talkingInvoice.videoLink)
    await apiClient.post(`/invoices/${id}/media`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      skipGlobalErrorToast: true,
    })
    toast.success('تم حفظ الوسائط', 'الفاتورة الناطقة جاهزة للمشاركة')
    talkingInvoice.open = false
  } catch (e: unknown) {
    toast.error('تعذّر الحفظ', summarizeAxiosError(e))
  }
  finally { savingMedia.value = false }
}

function copyInvoiceUrl() {
  navigator.clipboard.writeText(invoicePublicUrl.value)
  toast.success('تم نسخ الرابط')
}

const invoiceSettings = reactive({
  show_signature: true,
  show_stamp: true,
  show_logo: true,
  show_bank_details: false,
  print_primary_color: '#1e3a8a',
  print_header_note: '',
  footer_note: '',
})

async function load() {
  if (invoice.value) refreshing.value = true
  else loading.value = true
  try {
    const [invRes, compRes] = await Promise.all([
      apiClient.get(`/invoices/${id}`),
      auth.user?.company_id ? apiClient.get(`/companies/${auth.user.company_id}`) : Promise.resolve(null),
    ])
    invoice.value = invRes.data.data
    if (compRes) company.value = compRes.data.data

    // Load invoice settings
    if (auth.user?.company_id) {
      try {
        const sRes = await apiClient.get(`/companies/${auth.user.company_id}/settings`)
        const opts = sRes.data.data?.invoice_options || {}
        invoiceSettings.show_signature    = opts.show_signature    ?? true
        invoiceSettings.show_stamp        = opts.show_stamp        ?? true
        invoiceSettings.show_logo         = opts.show_logo         ?? true
        invoiceSettings.show_bank_details = opts.show_bank_details ?? false
        invoiceSettings.print_primary_color = typeof opts.print_primary_color === 'string' ? opts.print_primary_color : '#1e3a8a'
        invoiceSettings.print_header_note   = typeof opts.print_header_note === 'string' ? opts.print_header_note : ''
        invoiceSettings.footer_note       = sRes.data.data?.invoice_footer_note || ''
      } catch { /* */ }
    }
    if (invoice.value?.media) {
      talkingInvoice.beforePhotos = (invoice.value.media.before || []).map((url: string) => ({ url }))
      talkingInvoice.afterPhotos  = (invoice.value.media.after  || []).map((url: string) => ({ url }))
      talkingInvoice.videoUrl     = invoice.value.media.video_url || ''
      talkingInvoice.videoLink    = invoice.value.media.video_link || ''
    }
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

function canRefundPayment(p: Record<string, unknown>): boolean {
  return String(p.status ?? '') === 'completed' && !p.original_payment_id
}

async function submitRefund(p: Record<string, unknown>) {
  if (refundSubmittingId.value != null) return
  if (!canRefundPayment(p)) return
  const pid = Number(p.id)
  const ok = await appConfirm({
    title: 'تأكيد الاسترداد',
    message: `استرداد كامل للدفع بقيمة ${Number(p.amount).toFixed(2)} ر.س؟ سيتم تحديث الفاتورة والمحفظة عند الدفع من المحفظة.`,
    confirmLabel: 'تأكيد الاسترداد',
  })
  if (!ok) return
  refundSubmittingId.value = pid
  try {
    await apiClient.post(`/payments/${pid}/refund`, {}, { ...withIdempotency(), skipGlobalErrorToast: true })
    toast.success('تم تسجيل الاسترداد')
    await load()
  } catch (e: unknown) {
    toast.error('تعذّر الاسترداد', summarizeAxiosError(e))
  } finally {
    refundSubmittingId.value = null
  }
}

function openPayModal() {
  payError.value = ''
  payForm.method = 'cash'
  payForm.amount = Math.max(0.01, Number(invoice.value?.due_amount ?? 0))
  payForm.reference = ''
  payModal.open = true
}

async function submitPay() {
  if (paySubmitting.value) return
  payError.value = ''
  if (!payForm.amount || payForm.amount <= 0) {
    payError.value = 'أدخل مبلغاً صحيحاً'
    return
  }
  paySubmitting.value = true
  try {
    const body: Record<string, unknown> = {
      amount: payForm.amount,
      method: payForm.method,
      reference: payForm.reference || undefined,
    }
    if (payForm.method === 'wallet') {
      body.wallet_idempotency_key = uuidv4()
    }
    const { data } = await apiClient.post(`/invoices/${id}/pay`, body, { ...withIdempotency(), skipGlobalErrorToast: true })
    invoice.value = data?.data?.invoice ?? invoice.value
    payModal.open = false
    toast.success('تم تسجيل الدفع')
    await load()
  } catch (e: unknown) {
    payError.value = summarizeAxiosError(e)
  } finally {
    paySubmitting.value = false
  }
}
function formatDate(dt: string): string {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('ar-SA')
}

function formatInvoiceDateOnly(iso: string): string {
  if (!iso) return '—'
  return iso.slice(0, 10)
}

function formatInvoiceTimeOnly(iso: string): string {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    })
  } catch {
    return '—'
  }
}
const sourceLabel = computed(() => {
  if (!invoice.value?.source_type) return ''
  const type = invoice.value.source_type.split('\\').pop()
  return `${type} #${invoice.value.source_id}`
})

async function printInvoice() {
  const root = document.getElementById('invoice-print-template')
  if (!root) {
    toast.error('تعذّر الطباعة', 'لم يُعثر على قالب الفاتورة.')
    return
  }
  await printDocument({ root })
}

async function exportPDF() {
  if (pdfExporting.value) return
  pdfExporting.value = true
  try {
    const res = await apiClient.get<Blob>(`/invoices/${id}/pdf`, {
      responseType: 'blob',
      skipGlobalErrorToast: true,
      headers: {
        Accept: 'application/pdf',
      },
    } as Parameters<typeof apiClient.get>[1])

    const blob = res.data
    const ct = String(res.headers['content-type'] ?? '')
    if (!ct.includes('application/pdf')) {
      const text = await blob.text()
      let msg = 'تعذّر إنشاء ملف PDF.'
      try {
        const j = JSON.parse(text) as { message?: string }
        if (j.message) msg = j.message
      } catch {
        /* ignore */
      }
      throw new Error(msg)
    }

    const fileBase = invoice.value?.invoice_number || `invoice-${id}`
    const safeName = `${fileBase.replace(/[^\w.-]+/g, '_')}.pdf`
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = safeName
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    toast.success('تم التصدير', 'تم تنزيل ملف PDF.')
  } catch (e: unknown) {
    console.warn('[InvoiceShow PDF]', e)
    const fallback = 'تعذّر تنزيل PDF. جرّب الطباعة ثم حفظ كـ PDF.'
    const fromThrown = e instanceof Error && e.message.trim() ? e.message.trim() : ''
    toast.error('تصدير PDF', summarizeAxiosError(e) || fromThrown || fallback)
  } finally {
    pdfExporting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.invoice-print-only {
  display: none;
}

</style>
