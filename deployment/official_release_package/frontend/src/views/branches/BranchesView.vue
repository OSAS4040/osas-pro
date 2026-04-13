<template>
  <div class="max-w-6xl mx-auto space-y-6 pb-10" dir="rtl">
    <header class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl flex items-center gap-2">
          <BuildingLibraryIcon class="w-8 h-8 text-primary-600 dark:text-primary-400 shrink-0" />
          إدارة الفروع
        </h1>
        <p class="page-subtitle max-w-2xl">
          إنشاء وتعديل الفروع، الإحداثيات لعرضها على خريطة Google، والفرع الرئيسي والوصول بين الفروع.
        </p>
      </div>
      <div class="page-toolbar">
        <RouterLink
          to="/branches/map"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 dark:border-slate-600 text-sm font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800"
        >
          <MapPinIcon class="w-5 h-5 text-primary-600" />
          خريطة الفروع
        </RouterLink>
        <button
          v-if="canAddMore"
          type="button"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700 shadow-sm"
          @click="openCreate"
        >
          + فرع جديد
        </button>
        <p v-else class="text-xs text-amber-700 dark:text-amber-300 self-center max-w-[200px]">
          وصلت لحد الفروع في باقتك. رقِّ الباقة لإضافة المزيد.
        </p>
      </div>
    </header>

    <div v-if="loading" class="flex justify-center py-16">
      <div class="animate-spin rounded-full h-10 w-10 border-2 border-primary-500 border-t-transparent" />
    </div>

    <div v-else class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-900/80 text-xs text-gray-500 dark:text-slate-400 uppercase">
          <tr>
            <th class="px-4 py-3 text-right font-semibold">الاسم</th>
            <th class="px-4 py-3 text-right font-semibold">الكود</th>
            <th class="px-4 py-3 text-right font-semibold">المدينة</th>
            <th class="px-4 py-3 text-right font-semibold">الخريطة</th>
            <th class="px-4 py-3 text-right font-semibold">ساعات العمل</th>
            <th class="px-4 py-3 text-right font-semibold">رئيسي</th>
            <th class="px-4 py-3 text-right font-semibold">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold w-36"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
          <tr v-for="b in branches" :key="b.id" class="hover:bg-gray-50/80 dark:hover:bg-slate-700/30">
            <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">
              {{ b.name_ar || b.name }}
            </td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ b.code ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-slate-300">{{ b.city ?? '—' }}</td>
            <td class="px-4 py-3">
              <span
                v-if="hasCoords(b)"
                class="inline-flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-300"
              >
                <MapPinIcon class="w-4 h-4" />
                مفعّل
              </span>
              <span v-else class="text-xs text-gray-400">بدون إحداثيات</span>
            </td>
            <td class="px-4 py-3">
              <span
                v-if="branchHasHours(b)"
                class="inline-flex items-center gap-1 text-xs text-sky-700 dark:text-sky-300"
                :title="branchHoursSummary(b)"
              >
                <ClockIcon class="w-4 h-4 shrink-0" />
                مضبوط
              </span>
              <span v-else class="text-xs text-gray-400">—</span>
            </td>
            <td class="px-4 py-3">
              <span v-if="b.is_main" class="text-xs font-semibold text-primary-700 dark:text-primary-300">نعم</span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="b.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300'"
              >
                {{ b.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td class="px-4 py-3 text-left whitespace-nowrap">
              <button type="button" class="text-primary-600 dark:text-primary-400 text-xs font-medium hover:underline ml-3" @click="openEdit(b)">
                تعديل
              </button>
              <button
                v-if="auth.isOwner && !b.is_main"
                type="button"
                class="text-red-600 dark:text-red-400 text-xs hover:underline"
                @click="confirmDelete(b)"
              >
                حذف
              </button>
            </td>
          </tr>
          <tr v-if="!branches.length">
            <td colspan="8" class="px-4 py-14 text-center text-gray-500 dark:text-slate-400">
              لا توجد فروع بعد. أضف فرعاً أو راجع الصلاحيات.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <Transition name="branch-modal">
        <div
          v-if="showForm"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
          role="presentation"
        >
          <div
            class="absolute inset-0 bg-slate-900/55 backdrop-blur-[3px]"
            aria-hidden="true"
            @click.self="closeForm"
          />
          <div
            ref="branchDialogEl"
            class="branch-modal-panel relative w-full max-w-2xl max-h-[min(92vh,880px)] flex flex-col rounded-2xl shadow-2xl border border-gray-200/80 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="branchModalTitleId"
            @click.stop
          >
            <div class="shrink-0 px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-l from-primary-600/5 to-transparent dark:from-primary-500/10">
              <div class="flex items-start gap-3">
                <div class="hidden sm:flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300">
                  <BuildingLibraryIcon class="w-6 h-6" aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1">
                  <h2 :id="branchModalTitleId" class="text-lg font-bold text-gray-900 dark:text-slate-100">
                    {{ editTarget ? 'تعديل بيانات الفرع' : 'إضافة فرع جديد' }}
                  </h2>
                  <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 leading-relaxed">
                    {{ editTarget ? 'حدّث بيانات الاتصال والموقع؛ تنعكس التغييرات مباشرة على الخريطة والصلاحيات.' : 'أدخل بيانات الفرع والإحداثيات لعرضه على خريطة الفروع وربطه بالمستخدمين.' }}
                  </p>
                </div>
                <button
                  type="button"
                  class="shrink-0 p-2 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 dark:hover:bg-slate-700 dark:text-slate-400 dark:hover:text-slate-100 transition-colors"
                  @click="closeForm"
                >
                  <span class="sr-only">إغلاق</span>
                  <XMarkIcon class="w-5 h-5" aria-hidden="true" />
                </button>
              </div>
            </div>

            <form class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-5 sm:p-6 space-y-6" @submit.prevent="save">
              <section class="rounded-xl border border-gray-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/35 p-4 sm:p-5 space-y-4">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-slate-200">
                  <BuildingOffice2Icon class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0" />
                  البيانات الأساسية
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">الاسم (إنجليزي) <span class="text-red-500">*</span></span>
                    <input
                      v-model="form.name"
                      required
                      type="text"
                      autocomplete="organization"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">الاسم بالعربية</span>
                    <input
                      v-model="form.name_ar"
                      type="text"
                      dir="rtl"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">رمز الفرع</span>
                    <input
                      v-model="form.code"
                      type="text"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm font-mono shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">الهاتف</span>
                    <input
                      v-model="form.phone"
                      type="tel"
                      autocomplete="tel"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                </div>
              </section>

              <section class="rounded-xl border border-gray-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/35 p-4 sm:p-5 space-y-4">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-slate-200">
                  <MapPinIcon class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0" />
                  العنوان والتموضع
                </div>
                <label class="block text-sm">
                  <span class="text-gray-700 dark:text-slate-300">العنوان</span>
                  <input
                    v-model="form.address"
                    type="text"
                    autocomplete="street-address"
                    class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                  />
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">المدينة</span>
                    <input
                      v-model="form.city"
                      type="text"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">الحالة الإدارية</span>
                    <select
                      v-model="form.status"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    >
                      <option value="active">نشط</option>
                      <option value="inactive">غير نشط</option>
                    </select>
                  </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">خط العرض (WGS84)</span>
                    <input
                      v-model="form.latitude"
                      type="text"
                      inputmode="decimal"
                      placeholder="مثال: 24.713600"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm font-mono shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-slate-300">خط الطول (WGS84)</span>
                    <input
                      v-model="form.longitude"
                      type="text"
                      inputmode="decimal"
                      placeholder="مثال: 46.675300"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2.5 text-sm font-mono shadow-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500"
                    />
                  </label>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-50"
                    :disabled="geolocating"
                    @click="useMyLocation"
                  >
                    <span
                      v-if="geolocating"
                      class="inline-block w-3.5 h-3.5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"
                      aria-hidden="true"
                    />
                    <GlobeAltIcon v-else class="w-4 h-4 text-primary-600 shrink-0" />
                    {{ geolocating ? 'جاري تحديد الموقع…' : 'استخدام موقعي الحالي' }}
                  </button>
                  <a
                    v-if="mapPreviewUrl"
                    :href="mapPreviewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium bg-primary-600 text-white hover:bg-primary-700"
                  >
                    <ArrowTopRightOnSquareIcon class="w-4 h-4 shrink-0" />
                    معاينة على خرائط Google
                  </a>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
                  بعد الحفظ، يظهر الفرع على
                  <RouterLink to="/branches/map" class="text-primary-600 dark:text-primary-400 font-medium hover:underline">خريطة الفروع</RouterLink>
                  عند توفر الإحداثيات.
                </p>
              </section>

              <section class="rounded-xl border border-gray-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/35 p-4 sm:p-5 space-y-4">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-slate-200">
                  <ClockIcon class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0" />
                  ساعات العمل والحجوزات
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
                  عند التفعيل، يُسمح بالحجز فقط ضمن الفترات المحددة لكل يوم (نفس منطق الخادم). إذا عطّلت الخيار، يُحذف الجدول ويُسمح بأي وقت طالما توجد منطقة عمل متاحة.
                </p>
                <label class="flex items-start gap-3 cursor-pointer rounded-xl p-2 -mx-2 hover:bg-white/60 dark:hover:bg-slate-800/60">
                  <input v-model="useOpeningHours" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                  <span>
                    <span class="block text-sm font-medium text-gray-800 dark:text-slate-200">تقييد الحجوزات بساعات عمل الفرع</span>
                    <span class="block text-xs text-gray-500 dark:text-slate-400 mt-0.5">يظهر الجدول في صفحة الحجوزات بعد الحفظ.</span>
                  </span>
                </label>
                <div
                  v-if="useOpeningHours"
                  class="rounded-xl border border-gray-200 dark:border-slate-600 overflow-hidden bg-white dark:bg-slate-900/50"
                >
                  <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-slate-800/80 text-gray-500 dark:text-slate-400">
                      <tr>
                        <th class="px-3 py-2 text-right font-medium">اليوم</th>
                        <th class="px-3 py-2 text-center font-medium w-16">يعمل</th>
                        <th class="px-3 py-2 text-right font-medium">من</th>
                        <th class="px-3 py-2 text-right font-medium">إلى</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                      <tr v-for="day in branchDayKeys" :key="day">
                        <td class="px-3 py-2 font-medium text-gray-800 dark:text-slate-200">
                          {{ branchDayLabelAr[day] }}
                        </td>
                        <td class="px-3 py-2 text-center">
                          <input v-model="weekHours[day].enabled" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                        </td>
                        <td class="px-3 py-2">
                          <input
                            v-model="weekHours[day].open"
                            type="time"
                            :disabled="!weekHours[day].enabled"
                            class="w-full min-w-0 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 font-mono disabled:opacity-45"
                          />
                        </td>
                        <td class="px-3 py-2">
                          <input
                            v-model="weekHours[day].close"
                            type="time"
                            :disabled="!weekHours[day].enabled"
                            class="w-full min-w-0 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 font-mono disabled:opacity-45"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>

              <section class="rounded-xl border border-gray-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/35 p-4 sm:p-5 space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-slate-200">
                  <Cog6ToothIcon class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0" />
                  إعدادات الفرع
                </div>
                <div class="space-y-3">
                  <label class="flex items-start gap-3 cursor-pointer group rounded-xl p-2 -mx-2 hover:bg-white/60 dark:hover:bg-slate-800/60">
                    <input v-model="form.is_main" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <span>
                      <span class="block text-sm font-medium text-gray-800 dark:text-slate-200 group-hover:text-primary-700 dark:group-hover:text-primary-300">فرع رئيسي للمنشأة</span>
                      <span class="block text-xs text-gray-500 dark:text-slate-400 mt-0.5">يُعرَض كمرجع افتراضي عند الحاجة لفرع رئيسي واحد.</span>
                    </span>
                  </label>
                  <label class="flex items-start gap-3 cursor-pointer group rounded-xl p-2 -mx-2 hover:bg-white/60 dark:hover:bg-slate-800/60">
                    <input v-model="form.is_active" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <span>
                      <span class="block text-sm font-medium text-gray-800 dark:text-slate-200 group-hover:text-primary-700 dark:group-hover:text-primary-300">نشط في النظام</span>
                      <span class="block text-xs text-gray-500 dark:text-slate-400 mt-0.5">الفروع غير النشطة لا تُقترَح للعمليات اليومية.</span>
                    </span>
                  </label>
                  <label class="flex items-start gap-3 cursor-pointer group rounded-xl p-2 -mx-2 hover:bg-white/60 dark:hover:bg-slate-800/60">
                    <input v-model="form.cross_branch_access" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <span>
                      <span class="block text-sm font-medium text-gray-800 dark:text-slate-200 group-hover:text-primary-700 dark:group-hover:text-primary-300">سياسة وصول بين الفروع</span>
                      <span class="block text-xs text-gray-500 dark:text-slate-400 mt-0.5">السماح للمستخدمين المصرَّح لهم برؤية بيانات هذا الفرع حسب إعدادات المنصة.</span>
                    </span>
                  </label>
                </div>
              </section>

              <div
                v-if="formError"
                class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 px-4 py-3 text-sm text-red-800 dark:text-red-200"
                role="alert"
              >
                {{ formError }}
              </div>

              <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 pt-1 border-t border-gray-100 dark:border-slate-700">
                <button
                  type="button"
                  class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium border border-gray-300 dark:border-slate-600 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-200"
                  @click="closeForm"
                >
                  إلغاء
                </button>
                <button
                  type="submit"
                  class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700 shadow-sm disabled:opacity-50 disabled:pointer-events-none"
                  :disabled="saving"
                >
                  <span
                    v-if="saving"
                    class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
                    aria-hidden="true"
                  />
                  {{ saving ? 'جاري الحفظ…' : editTarget ? 'حفظ التغييرات' : 'إنشاء الفرع' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  ArrowTopRightOnSquareIcon,
  BuildingLibraryIcon,
  BuildingOffice2Icon,
  ClockIcon,
  Cog6ToothIcon,
  GlobeAltIcon,
  MapPinIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStore } from '@/stores/subscription'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import type { BranchRow } from './branchTypes'
import {
  BRANCH_DAY_KEYS,
  BRANCH_DAY_LABEL_AR,
  defaultWeekHoursForm,
  openingHoursPayloadFromWeekForm,
  scheduleHasIntervals,
  normalizeOpeningHoursForDisplay,
  weekFormFromOpeningHours,
  type WeekHoursForm,
} from '@/utils/branchOpeningHours'

const branchDayKeys = [...BRANCH_DAY_KEYS] as const
const branchDayLabelAr = BRANCH_DAY_LABEL_AR

const auth = useAuthStore()
const sub = useSubscriptionStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const branchModalTitleId = 'branch-form-dialog-title'
const branchDialogEl = ref<HTMLElement | null>(null)

const branches = ref<BranchRow[]>([])
const loading = ref(true)
const showForm = ref(false)
const editTarget = ref<BranchRow | null>(null)
const saving = ref(false)
const formError = ref('')
const geolocating = ref(false)

let branchModalEscapeHandler: ((e: KeyboardEvent) => void) | null = null

const form = ref({
  name: '',
  name_ar: '',
  code: '',
  phone: '',
  address: '',
  city: '',
  latitude: '' as string,
  longitude: '' as string,
  is_main: false,
  is_active: true,
  cross_branch_access: false,
  status: 'active',
})

const useOpeningHours = ref(false)
const weekHours = ref<WeekHoursForm>(defaultWeekHoursForm())

function branchHasHours(b: BranchRow): boolean {
  return scheduleHasIntervals(b.opening_hours as Record<string, unknown> | undefined)
}

function branchHoursSummary(b: BranchRow): string {
  const n = normalizeOpeningHoursForDisplay(b.opening_hours)
  if (!n) return ''
  const parts: string[] = []
  for (const day of BRANCH_DAY_KEYS) {
    const slots = n[day]
    if (!slots?.length) continue
    const ranges = slots.map(([a, z]) => `${a}–${z}`).join('، ')
    parts.push(`${BRANCH_DAY_LABEL_AR[day]}: ${ranges}`)
  }
  return parts.join(' · ')
}

const canAddMore = computed(() => {
  const max = sub.limits.max_branches ?? 1
  if (max <= 0) return true
  return branches.value.length < max
})

const mapPreviewUrl = computed(() => {
  const lat = form.value.latitude.trim()
  const lng = form.value.longitude.trim()
  if (lat === '' || lng === '') return ''
  const la = Number(lat)
  const lo = Number(lng)
  if (Number.isNaN(la) || Number.isNaN(lo)) return ''
  if (la < -90 || la > 90 || lo < -180 || lo > 180) return ''
  return `https://www.google.com/maps?q=${encodeURIComponent(`${la},${lo}`)}`
})

function validateCoords(): string | null {
  const lat = form.value.latitude.trim()
  const lng = form.value.longitude.trim()
  if (lat === '' && lng === '') return null
  if (lat === '' || lng === '') {
    return 'أدخل خطي العرض والطول معاً، أو اتركهما فارغين.'
  }
  const la = Number(lat)
  const lo = Number(lng)
  if (Number.isNaN(la) || Number.isNaN(lo)) {
    return 'الإحداثيات يجب أن تكون أرقاماً عشرية صحيحة.'
  }
  if (la < -90 || la > 90) return 'خط العرض يجب أن يكون بين -90 و 90.'
  if (lo < -180 || lo > 180) return 'خط الطول يجب أن يكون بين -180 و 180.'
  return null
}

function useMyLocation(): void {
  if (!navigator.geolocation) {
    toast.error('الموقع', 'المتصفّح لا يدعم تحديد الموقع الجغرافي.')
    return
  }
  geolocating.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.value.latitude = pos.coords.latitude.toFixed(6)
      form.value.longitude = pos.coords.longitude.toFixed(6)
      geolocating.value = false
      toast.success('تم التحديث', 'تم تعبئة الإحداثيات من موقعك الحالي.')
    },
    () => {
      geolocating.value = false
      toast.error('الموقع', 'تعذّر الحصول على الموقع. راجع أذونات المتصفّح وحاول مجدداً.')
    },
    { enableHighAccuracy: true, timeout: 15_000, maximumAge: 60_000 },
  )
}

function hasCoords(b: BranchRow): boolean {
  return b.latitude != null && b.longitude != null && !Number.isNaN(Number(b.latitude)) && !Number.isNaN(Number(b.longitude))
}

function normBody() {
  const o: Record<string, unknown> = {
    name: form.value.name.trim(),
    name_ar: form.value.name_ar.trim() || null,
    code: form.value.code.trim() || null,
    phone: form.value.phone.trim() || null,
    address: form.value.address.trim() || null,
    city: form.value.city.trim() || null,
    is_main: form.value.is_main,
    is_active: form.value.is_active,
    cross_branch_access: form.value.cross_branch_access,
    status: form.value.status,
  }
  const lat = form.value.latitude.trim()
  const lng = form.value.longitude.trim()
  o.latitude = lat === '' ? null : Number(lat)
  o.longitude = lng === '' ? null : Number(lng)
  o.opening_hours = openingHoursPayloadFromWeekForm(useOpeningHours.value, weekHours.value)
  return o
}

async function loadBranches() {
  loading.value = true
  try {
    await sub.loadSubscription().catch(() => {})
    const { data } = await apiClient.get('/branches', { params: { per_page: 100 } })
    const p = data.data
    branches.value = Array.isArray(p?.data) ? p.data : []
  } catch (e: unknown) {
    toast.error('تعذّر التحميل', (e as Error)?.message ?? '')
    branches.value = []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editTarget.value = null
  useOpeningHours.value = false
  weekHours.value = defaultWeekHoursForm()
  form.value = {
    name: '',
    name_ar: '',
    code: '',
    phone: '',
    address: '',
    city: '',
    latitude: '',
    longitude: '',
    is_main: branches.value.length === 0,
    is_active: true,
    cross_branch_access: false,
    status: 'active',
  }
  formError.value = ''
  showForm.value = true
}

function openEdit(b: BranchRow) {
  editTarget.value = b
  const wh = weekFormFromOpeningHours(b.opening_hours)
  useOpeningHours.value = wh.useHours
  weekHours.value = wh.week
  form.value = {
    name: b.name ?? '',
    name_ar: b.name_ar ?? '',
    code: b.code ?? '',
    phone: b.phone ?? '',
    address: b.address ?? '',
    city: b.city ?? '',
    latitude: b.latitude != null ? String(b.latitude) : '',
    longitude: b.longitude != null ? String(b.longitude) : '',
    is_main: !!b.is_main,
    is_active: !!b.is_active,
    cross_branch_access: !!b.cross_branch_access,
    status: b.status === 'inactive' ? 'inactive' : 'active',
  }
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editTarget.value = null
}

watch(showForm, (open) => {
  if (branchModalEscapeHandler) {
    document.removeEventListener('keydown', branchModalEscapeHandler)
    branchModalEscapeHandler = null
  }
  if (open) {
    document.body.style.overflow = 'hidden'
    branchModalEscapeHandler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault()
        closeForm()
      }
    }
    document.addEventListener('keydown', branchModalEscapeHandler)
    void nextTick(() => {
      const first = branchDialogEl.value?.querySelector<HTMLElement>(
        'form input:not([type="hidden"]), form select, form textarea',
      )
      first?.focus()
    })
  } else {
    document.body.style.overflow = ''
  }
})

onUnmounted(() => {
  if (branchModalEscapeHandler) {
    document.removeEventListener('keydown', branchModalEscapeHandler)
    branchModalEscapeHandler = null
  }
  document.body.style.overflow = ''
})

async function save() {
  formError.value = ''
  const coordErr = validateCoords()
  if (coordErr) {
    formError.value = coordErr
    return
  }
  if (useOpeningHours.value) {
    const oh = openingHoursPayloadFromWeekForm(true, weekHours.value)
    if (oh === null) {
      formError.value =
        'فعّل يوماً واحداً على الأقل مع أوقات صحيحة (وقت البداية قبل وقت النهاية).'
      return
    }
  }
  saving.value = true
  try {
    const body = normBody()
    if (editTarget.value) {
      await apiClient.put(`/branches/${editTarget.value.id}`, body)
      toast.success('تم التحديث', 'تم حفظ بيانات الفرع')
    } else {
      if (!canAddMore.value) {
        formError.value = 'لا يمكن إضافة فرع جديد ضمن حد الباقة.'
        return
      }
      await apiClient.post('/branches', body)
      toast.success('تم الإنشاء', 'تمت إضافة الفرع')
    }
    closeForm()
    await loadBranches()
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string } } }
    formError.value = ax.response?.data?.message ?? 'فشل الحفظ'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(b: BranchRow) {
  const ok = await appConfirm({
    title: 'حذف الفرع',
    message: `حذف الفرع «${b.name_ar || b.name}»؟ لا يمكن التراجع.`,
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/branches/${b.id}`)
    toast.success('تم الحذف')
    await loadBranches()
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string } } }
    toast.error('تعذّر الحذف', ax.response?.data?.message ?? '')
  }
}

watch(
  () => route.query.edit,
  async (id) => {
    if (!id || loading.value) return
    const sid = String(id)
    const b = branches.value.find((x) => String(x.id) === sid)
    if (b) {
      openEdit(b)
      await router.replace({ query: {} })
    }
  },
)

onMounted(async () => {
  await loadBranches()
  const id = route.query.edit
  if (id) {
    const sid = String(id)
    const b = branches.value.find((x) => String(x.id) === sid)
    if (b) {
      openEdit(b)
      router.replace({ query: {} })
    }
  }
})
</script>

<style scoped>
.branch-modal-enter-active,
.branch-modal-leave-active {
  transition: opacity 0.2s ease;
}

.branch-modal-enter-active .branch-modal-panel,
.branch-modal-leave-active .branch-modal-panel {
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.branch-modal-enter-from,
.branch-modal-leave-to {
  opacity: 0;
}

.branch-modal-enter-from .branch-modal-panel,
.branch-modal-leave-to .branch-modal-panel {
  transform: scale(0.97) translateY(10px);
  opacity: 0;
}
</style>
