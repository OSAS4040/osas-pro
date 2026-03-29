<template>
  <div class="space-y-5" dir="rtl">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <DocumentTextIcon class="w-6 h-6 text-primary-600" />
        عروض الأسعار
      </h2>
      <button
        @click="openCreateModal"
        class="flex items-center gap-1.5 px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition-colors"
      >
        <PlusIcon class="w-4 h-4" />
        عرض جديد
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="flex flex-wrap gap-3">
      <button
        v-for="stat in stats"
        :key="stat.key"
        @click="filterStatus = stat.key === 'all' ? '' : stat.key"
        class="flex items-center gap-2 px-3 py-2 rounded-xl border text-sm font-medium transition-all"
        :class="[
          (stat.key === 'all' ? filterStatus === '' : filterStatus === stat.key)
            ? `${stat.activeBg} ${stat.activeText} border-transparent shadow-sm`
            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'
        ]"
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
      <input
        v-model="dateFrom"
        type="date"
        class="text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-400"
      />
      <input
        v-model="dateTo"
        type="date"
        class="text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-400"
      />
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div v-if="loading" class="py-12 text-center text-gray-400 dark:text-gray-500 text-sm">جارٍ التحميل...</div>
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
                    @click="viewQuote(q)"
                    class="px-2.5 py-1 text-xs rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                  >
                    عرض
                  </button>
                  <button
                    v-if="q.status === 'draft'"
                    @click="changeStatus(q, 'sent')"
                    class="px-2.5 py-1 text-xs rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                  >
                    إرسال
                  </button>
                  <button
                    v-if="q.status === 'sent'"
                    @click="changeStatus(q, 'accepted')"
                    class="px-2.5 py-1 text-xs rounded-lg bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors"
                  >
                    قبول
                  </button>
                  <button
                    v-if="q.status === 'sent'"
                    @click="changeStatus(q, 'rejected')"
                    class="px-2.5 py-1 text-xs rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors"
                  >
                    رفض
                  </button>
                  <button
                    @click="deleteQuote(q)"
                    class="px-2.5 py-1 text-xs rounded-lg bg-red-50 dark:bg-red-900/20 text-red-500 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
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
      <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-2xl" dir="rtl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">تفاصيل عرض السعر</h3>
          <button @click="viewModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>
        <div class="p-6 space-y-4">
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
        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-end">
          <button @click="viewModal = false" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">إغلاق</button>
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

        <form @submit.prevent="saveQuote" class="overflow-y-auto flex-1">
          <div class="p-6 space-y-5">

            <!-- Customer -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">العميل <span class="text-red-500">*</span></label>
              <select
                v-model="form.customer_id"
                required
                class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-400"
              >
                <option value="">-- اختر عميلاً --</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تاريخ الإصدار <span class="text-red-500">*</span></label>
                <input
                  v-model="form.issue_date"
                  type="date"
                  required
                  class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-400"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تاريخ الانتهاء</label>
                <input
                  v-model="form.expiry_date"
                  type="date"
                  class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-400"
                />
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
                  @click="addItem"
                  class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 font-medium"
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
                          @click="removeItem(i)"
                          class="text-red-400 hover:text-red-600 transition-colors"
                          :disabled="form.items.length <= 1"
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
        </form>

        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex gap-3 justify-end flex-shrink-0">
          <button
            type="button"
            @click="createModal = false"
            class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            إلغاء
          </button>
          <button
            @click="saveQuote"
            :disabled="saving"
            class="px-5 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors"
          >
            {{ saving ? 'جارٍ الحفظ...' : 'حفظ العرض' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  DocumentTextIcon,
  PlusIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'

const { get, post, put, del } = useApi()

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

function viewQuote(q: any) {
  selectedQuote.value = q
  viewModal.value = true
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
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      customer_id: form.value.customer_id,
      issue_date:  form.value.issue_date,
      expiry_date: form.value.expiry_date,
      notes:       form.value.notes,
      discount:    form.value.discount,
      total:       grandTotal.value,
      items:       form.value.items.filter(i => i.name?.trim()),
    }
    await post('/quotes', payload)
    await loadQuotes()
    createModal.value = false
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? 'فشل الحفظ، يرجى المحاولة مجدداً'
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
  if (!confirm(`هل أنت متأكد من حذف عرض السعر ${q.quote_number ?? '#' + q.id}؟`)) return
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
