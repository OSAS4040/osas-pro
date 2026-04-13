<template>
  <div class="max-w-2xl mx-auto space-y-5 px-1 sm:px-0">
    <!-- شريط علوي: عودة + مشاركة (أيقونات صغيرة وواضحة) -->
    <div class="no-print space-y-1.5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <RouterLink :to="`/vehicles/${vehicleId}`" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 text-sm flex items-center gap-1 transition-colors shrink-0">
          <ArrowRightIcon class="w-4 h-4" /> عودة لملف المركبة
        </RouterLink>

        <div v-if="vehicle" class="flex flex-col items-end gap-1.5 min-w-0 max-w-full">
          <span class="text-[10px] font-semibold text-gray-500 dark:text-slate-400">مشاركة البطاقة</span>
          <div class="flex flex-wrap items-center justify-end gap-1" role="toolbar" aria-label="مشاركة البطاقة الرقمية">
            <button
              type="button"
              class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-gray-200 bg-white p-2.5 text-teal-700 shadow-sm transition hover:bg-teal-50 dark:border-slate-600 dark:bg-slate-800 dark:text-teal-300 dark:hover:bg-slate-700"
              title="مشاركة سريعة (التطبيقات / نسخ الرابط)"
              aria-label="مشاركة سريعة"
              @click="shareCardQuick"
            >
              <DevicePhoneMobileIcon class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-gray-200 bg-white p-2.5 text-violet-700 shadow-sm transition hover:bg-violet-50 disabled:opacity-45 dark:border-slate-600 dark:bg-slate-800 dark:text-violet-300 dark:hover:bg-slate-700"
              title="مشاركة البطاقة كصورة"
              aria-label="مشاركة كصورة"
              :disabled="shareImageBusy"
              @click="shareCardAsImage"
            >
              <PhotoIcon class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-gray-200 bg-white p-2.5 text-primary-700 shadow-sm transition hover:bg-primary-50 dark:border-slate-600 dark:bg-slate-800 dark:text-primary-300 dark:hover:bg-slate-700"
              title="تحميل صورة PNG"
              aria-label="تحميل صورة PNG"
              @click="downloadCard"
            >
              <ArrowDownTrayIcon class="h-4 w-4" />
            </button>
            <ShareModal
              ref="shareModalRef"
              :url="qrUrl"
              :title="`${vehicle?.make} ${vehicle?.model} — ${vehicle?.plate_number}`"
              label="البطاقة الرقمية"
              :phone="vehicle?.customer?.phone"
              :email="vehicle?.customer?.email"
              :message="`بطاقة مركبتك ${vehicle?.plate_number} — يمكنك متابعة أوامر العمل والرصيد:`"
              entity-type="vehicle_card"
              :entity-id="vehicleId"
            >
              <template #default="{ open }">
                <button
                  type="button"
                  class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-gray-200 bg-white p-2.5 text-indigo-700 shadow-sm transition hover:bg-indigo-50 dark:border-slate-600 dark:bg-slate-800 dark:text-indigo-300 dark:hover:bg-slate-700"
                  title="خيارات متقدمة (رابط، واتساب، بريد)"
                  aria-label="خيارات مشاركة متقدمة"
                  @click="open"
                >
                  <ShareIcon class="h-4 w-4" />
                </button>
              </template>
            </ShareModal>
          </div>
          <p class="text-[10px] leading-relaxed text-gray-400 dark:text-slate-500 text-right max-w-[16rem] sm:max-w-xs">
            سريعة: رابط من الجهاز. الصورة: حفظ أو إرسال البطاقة كملف. المزيد: واتساب وبريد.
          </p>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-16 text-sm text-gray-400 dark:text-slate-400">جارٍ التحميل...</div>

    <div
      v-else-if="loadError"
      class="rounded-2xl border border-red-200 bg-red-50/80 px-5 py-10 text-center dark:border-red-900/50 dark:bg-red-950/20"
      role="alert"
    >
      <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ loadError }}</p>
      <button
        type="button"
        class="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700"
        @click="fetchDigitalCard"
      >
        إعادة المحاولة
      </button>
    </div>

    <template v-else-if="vehicle">
      <!-- إدارة رابط الهوية العام (مسح QR) — صلاحية تحديث المركبات -->
      <div
        v-if="canManageIdentity"
        class="no-print rounded-2xl border border-gray-200 bg-white/90 px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800/80"
      >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-800 dark:text-slate-100">الرابط العام للمسح (OSAS Pro)</p>
            <p v-if="identityHasActiveLink" class="mt-1 font-mono text-[11px] text-teal-700 dark:text-teal-300">
              {{ vehicle.identity?.public_code }}
            </p>
            <p
              v-else-if="vehicle.identity?.status === 'unavailable'"
              class="mt-1 text-xs leading-relaxed text-amber-800/95 dark:text-amber-200/90"
            >
              رمز المسح غير مُهيّأ على الخادم (جدول الهوية غير موجود). نفّذ ترقية قاعدة البيانات ثم أعد المحاولة.
            </p>
            <p v-else class="mt-1 text-xs leading-relaxed text-amber-800/95 dark:text-amber-200/90">
              لا يوجد رابط نشط. أي QR أو بطاقة مطبوعة سابقة تتوقف عن العمل بعد الإبطال حتى تُصدر رابطاً جديداً.
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
            <button
              v-if="identityHasActiveLink"
              type="button"
              class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              :disabled="identityBusy"
              @click="copyIdentityUrl"
            >
              نسخ الرابط
            </button>
            <button
              v-if="!identityHasActiveLink && vehicle.identity?.status !== 'unavailable'"
              type="button"
              class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-500 disabled:opacity-50"
              :disabled="identityBusy"
              @click="issueVehicleIdentity"
            >
              إصدار رابط جديد
            </button>
            <button
              v-if="identityHasActiveLink"
              type="button"
              class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-medium text-violet-800 hover:bg-violet-100 disabled:opacity-50 dark:border-violet-500/40 dark:bg-violet-950/50 dark:text-violet-200 dark:hover:bg-violet-900/40"
              :disabled="identityBusy"
              title="إلغاء الرمز الحالي وإنشاء رمز جديد — يُبطل الطباعات السابقة"
              @click="rotateVehicleIdentity"
            >
              تدوير الرمز
            </button>
            <button
              v-if="identityHasActiveLink"
              type="button"
              class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-800 hover:bg-red-100 disabled:opacity-50 dark:border-red-500/35 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
              :disabled="identityBusy"
              @click="revokeVehicleIdentity"
            >
              إبطال الرابط
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ محفظة رقمية (مظهر مقارب لـ Apple Wallet) ═══ -->
      <div class="wallet-phone-surface mx-auto max-w-[400px] rounded-[2rem] bg-[#f2f2f7] dark:bg-slate-900/80 px-4 pt-6 pb-5 shadow-inner border border-black/[0.06] dark:border-white/10">
        <div
          ref="cardRef"
          class="vehicle-wallet-card group relative flex w-full min-h-[272px] flex-col rounded-[1.65rem] sm:min-h-[296px] sm:rounded-[1.85rem] shadow-[0_28px_56px_-16px_rgba(49,17,89,0.55),0_0_0_1px_rgba(255,255,255,0.1)_inset] overflow-hidden select-none"
        >
          <div class="absolute inset-0 rounded-[inherit]" :style="{ background: cardGradientCss }" aria-hidden="true" />
          <div class="wallet-card-shine absolute inset-0 rounded-[inherit] pointer-events-none opacity-60" aria-hidden="true" />
          <div class="absolute -top-8 -right-8 w-[60%] h-[55%] rounded-full bg-white/[0.09] blur-3xl pointer-events-none" aria-hidden="true" />

          <!-- اتجاه RTL مع ترتيب عناصر يحافظ على مظهر محفظة رقمية متوازن -->
          <div dir="rtl" class="relative z-10 flex min-h-0 flex-1 flex-col p-5 sm:p-6 text-right text-white">
            <!-- شريط حالة + كبسولة الحالة -->
            <div class="mb-4 flex items-center justify-between gap-3">
              <div
                dir="ltr"
                class="wallet-status-bar flex h-[5px] w-[7.25rem] overflow-hidden rounded-full bg-white/20 shadow-inner"
                role="presentation"
              >
                <div class="h-full rounded-l-full bg-white/85" :style="{ width: statusBarLightPct + '%' }" />
                <div class="h-full min-w-[18%] flex-1 bg-emerald-400" />
              </div>
              <div class="flex items-center gap-1.5 rounded-full border border-white/10 bg-black/25 px-2.5 py-1">
                <span class="max-w-[5.5rem] truncate text-[10px] font-semibold text-white/95 sm:max-w-[7rem] sm:text-[11px]">{{ statusLabel }}</span>
                <span class="relative flex h-2 w-2">
                  <span class="absolute inline-flex h-full w-full rounded-full opacity-35 animate-ping" :class="statusDot" />
                  <span class="relative inline-flex h-2 w-2 rounded-full" :class="statusDot" />
                </span>
              </div>
            </div>

            <!-- شارة اللوحة + العلامة -->
            <div class="mb-5 flex items-start justify-between gap-2">
              <div
                class="wallet-chip shrink-0 rounded-xl border border-white/30 bg-gradient-to-br from-white/22 to-white/[0.06] px-2.5 py-1.5 text-center shadow-inner backdrop-blur-md"
              >
                <span class="mb-0.5 block text-[8px] font-semibold text-white/55">لوحة</span>
                <span class="block max-w-[6.5rem] truncate font-mono text-base font-black tracking-[0.08em] text-white sm:text-lg" dir="ltr">{{ vehicle.plate_number }}</span>
              </div>
              <div class="min-w-0 text-right">
                <p class="text-[11px] font-bold leading-tight text-white drop-shadow-sm sm:text-xs">
                  {{ walletBrandLine1 }}
                </p>
                <p class="text-[9px] font-semibold tracking-wide text-white/55">
                  {{ walletBrandLine2 }}
                </p>
              </div>
            </div>

            <!-- هوية المركبة -->
            <div class="mb-4 py-0.5">
              <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1 text-right">
                  <p class="text-[10px] font-medium text-white/50">مركبة مسجّلة</p>
                  <h1 class="text-xl font-black leading-tight tracking-tight text-white sm:text-2xl">
                    {{ vehicle.make }} {{ vehicle.model }}
                  </h1>
                  <p class="mt-0.5 text-xs text-white/60">
                    <span v-if="vehicle.year">{{ vehicle.year }}</span>
                    <span v-if="vehicle.year && vehicle.color"> · </span>
                    <span v-if="vehicle.color">{{ vehicle.color }}</span>
                  </p>
                  <p class="mt-1 font-mono text-[10px] text-white/40 dir-ltr text-left" dir="ltr">VIN · {{ vehicle.vin || '—' }}</p>
                </div>
                <div
                  class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/25 bg-black/20 shadow-lg backdrop-blur-md sm:h-[3.75rem] sm:w-[3.75rem]"
                >
                  <TruckIcon class="h-7 w-7 text-white/95 sm:h-8 sm:w-8" />
                </div>
              </div>
            </div>

            <!-- إحصائيات -->
            <div class="mb-3 grid grid-cols-3 gap-1.5 sm:gap-2">
              <div class="wallet-stat-cell rounded-xl border border-white/10 bg-black/18 px-1 py-2 text-center backdrop-blur-sm sm:py-2.5">
                <p class="text-base font-black tabular-nums sm:text-lg">{{ vehicle.work_orders_count || 0 }}</p>
                <p class="mt-0.5 text-[8px] font-medium text-white/50 sm:text-[9px]">زيارات</p>
              </div>
              <div
                class="wallet-stat-cell rounded-xl border border-white/10 bg-black/18 px-1 py-2 text-center backdrop-blur-sm sm:py-2.5"
                :class="walletBalance < 0 ? 'ring-1 ring-red-400/55' : ''"
              >
                <p class="text-sm font-black tabular-nums sm:text-base" :class="walletBalance < 0 ? 'text-red-200' : ''">
                  {{ fmtMoney(walletBalance) }}
                </p>
                <p class="mt-0.5 text-[8px] font-medium text-white/50 sm:text-[9px]">ر.س</p>
              </div>
              <div class="wallet-stat-cell rounded-xl border border-white/10 bg-black/18 px-1 py-2 text-center backdrop-blur-sm sm:py-2.5">
                <p class="text-base font-black tabular-nums sm:text-lg">{{ loyaltyPoints }}</p>
                <p class="mt-0.5 text-[8px] font-medium text-white/50 sm:text-[9px]">ولاء</p>
              </div>
            </div>

            <!-- مالك + آخر زيارة -->
            <div class="mb-3 flex items-center justify-between gap-2 rounded-xl border border-white/10 bg-black/22 px-3 py-2 backdrop-blur-md">
              <div class="flex min-w-0 flex-1 items-center gap-2">
                <UserCircleIcon class="h-8 w-8 shrink-0 text-white/45" />
                <div class="min-w-0 text-right">
                  <p class="text-[9px] text-white/45">مالك المركبة</p>
                  <p class="truncate text-xs font-bold text-white">{{ vehicle.customer?.name || '—' }}</p>
                </div>
              </div>
              <div class="shrink-0 text-left">
                <p class="text-[9px] text-white/45">آخر زيارة</p>
                <p class="whitespace-nowrap text-[11px] font-semibold text-white/90">{{ lastVisit }}</p>
              </div>
            </div>

            <!-- QR + شارة -->
            <div class="mt-auto flex items-end justify-between gap-2 border-t border-white/10 pt-3">
              <div class="flex shrink-0 items-end gap-2">
                <div class="flex flex-col items-start pb-0.5 pl-0.5">
                  <span class="text-[11px] font-black tracking-wide text-white/95">معتمد</span>
                  <span class="text-[8px] font-semibold text-white/45">رقمياً</span>
                </div>
                <div class="rounded-2xl bg-white p-1.5 shadow-lg shadow-black/30 ring-2 ring-white/25 sm:p-2">
                  <img
                    v-if="qrImageUrl"
                    :src="qrImageUrl"
                    width="88"
                    height="88"
                    class="block h-[4.5rem] w-[4.5rem] rounded-md sm:h-24 sm:w-24"
                    alt="رمز الاستجابة السريعة"
                  />
                  <div
                    v-else
                    class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-md bg-gray-100 text-[9px] text-gray-400 sm:h-24 sm:w-24"
                  >
                    QR
                  </div>
                </div>
              </div>
              <div class="min-w-0 space-y-0.5 pb-0.5 text-right">
                <p class="text-[10px] font-semibold text-white/88">امسح للفتح السريع</p>
                <p class="max-w-[11rem] truncate font-mono text-[9px] text-white/35 dir-ltr text-left" dir="ltr" :title="qrUrl">{{ qrUrlDisplay }}</p>
              </div>
            </div>

            <p class="mt-2 text-center text-[8px] font-medium tracking-wide text-white/35">
              بطاقة خاصة — لا تشاركها مع غير المخوّلين · OSAS Pro
            </p>
          </div>
        </div>

        <!-- زخرفة بصرية فقط — لا يوجد تحقق بيومتري -->
        <div class="no-print mt-7 flex flex-col items-center justify-center text-gray-800 dark:text-slate-200">
          <div
            class="wallet-face-id-icon flex h-14 w-14 items-center justify-center rounded-2xl border-2 border-dashed border-gray-300/90 bg-white/80 shadow-sm dark:border-slate-600 dark:bg-slate-800/90"
            aria-hidden="true"
          >
            <svg class="h-8 w-8 text-gray-400 dark:text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25">
              <path stroke-linecap="round" d="M9 10V9a3 3 0 0 1 6 0v1" />
              <rect x="6" y="7" width="12" height="11" rx="4" />
              <path stroke-linecap="round" d="M9 14h.01M12 14h.01M15 14h.01" />
            </svg>
          </div>
          <p class="mt-2.5 text-[15px] font-medium tracking-tight text-gray-900 dark:text-white">مظهر المحفظة الرقمية</p>
          <p class="mt-1 max-w-[17rem] text-center text-xs leading-relaxed text-gray-500 dark:text-slate-400">
            عنصر زخرفي فقط — لا يطلب بصمة وجه ولا Touch ID ولا أي تحقق حقيقي.
          </p>
        </div>

        <!-- طبقات بطاقات أسفل (مكدس) -->
        <div class="no-print relative mx-auto mt-5 h-12 w-[92%] max-w-[22rem]">
          <div class="wallet-stack-layer wallet-stack-layer--3 absolute bottom-0 left-[6%] right-[6%] h-3 rounded-t-xl bg-amber-200/95 shadow-md dark:bg-amber-900/50" />
          <div class="wallet-stack-layer wallet-stack-layer--2 absolute bottom-1 left-[10%] right-[10%] h-3 rounded-t-xl bg-sky-300/95 shadow-md dark:bg-sky-800/60" />
          <div class="wallet-stack-layer wallet-stack-layer--1 absolute bottom-2 left-[14%] right-[14%] h-3.5 rounded-t-xl bg-violet-300/90 shadow-md dark:bg-violet-900/55" />
        </div>
      </div>

      <!-- ═══ Active Work Orders (RTL: من اليمين لليسار) ═══ -->
      <div
        class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden"
        dir="rtl"
      >
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex flex-row-reverse items-center justify-between gap-2">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <ClipboardDocumentIcon class="w-4 h-4 text-primary-600 shrink-0" /> أوامر العمل
          </h3>
          <RouterLink :to="`/work-orders?vehicle=${vehicleId}`" class="text-xs text-primary-600 hover:underline shrink-0">عرض الكل</RouterLink>
        </div>
        <div v-if="workOrders.length" class="divide-y divide-gray-50 dark:divide-slate-700">
          <div
            v-for="wo in workOrders"
            :key="wo.id"
            class="flex flex-row-reverse items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors text-right"
          >
            <div class="min-w-0 flex flex-row-reverse items-center gap-3 flex-1">
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                :class="woIconBg(wo.status)"
              >
                <component :is="woIcon(wo.status)" class="w-4 h-4" :class="woIconColor(wo.status)" />
              </div>
              <div class="min-w-0 flex-1">
                <RouterLink
                  :to="`/work-orders/${wo.id}`"
                  class="block text-sm font-medium text-gray-900 dark:text-slate-100 hover:text-primary-600"
                >
                  {{ wo.order_number }}
                </RouterLink>
                <p class="text-xs text-gray-400 break-words">{{ wo.description?.substring(0, 40) || 'بدون وصف' }}</p>
              </div>
            </div>
            <div class="shrink-0 text-right">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full inline-block" :class="workOrderStatusBadgeClass(wo.status)">{{ workOrderStatusLabel(wo.status) }}</span>
              <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(wo.created_at) }}</p>
            </div>
          </div>
        </div>
        <p v-else class="text-center py-6 text-sm text-gray-400">لا توجد أوامر عمل</p>
      </div>

      <!-- ═══ Wallet Transactions ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <CreditCardIcon class="w-4 h-4 text-green-600" /> المحفظة والمعاملات
          </h3>
          <div class="flex items-center gap-2">
            <span class="text-sm font-bold" :class="walletBalance >= 0 ? 'text-green-600' : 'text-red-500'">
              {{ fmtMoney(walletBalance) }} ر.س
            </span>
          </div>
        </div>
        <div v-if="transactions.length" class="divide-y divide-gray-50 dark:divide-slate-700">
          <div v-for="tx in transactions.slice(0, 5)" :key="tx.id"
               class="flex items-center justify-between px-5 py-3"
          >
            <div class="flex items-center gap-3">
              <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                   :class="tx.type === 'credit' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'"
              >
                <ArrowUpIcon v-if="tx.type === 'credit'" class="w-3.5 h-3.5" />
                <ArrowDownIcon v-else class="w-3.5 h-3.5" />
              </div>
              <div>
                <p class="text-xs font-medium text-gray-800 dark:text-slate-200">{{ tx.description || 'معاملة' }}</p>
                <p class="text-[10px] text-gray-400">{{ formatDate(tx.created_at) }}</p>
              </div>
            </div>
            <span class="text-sm font-bold" :class="tx.type === 'credit' ? 'text-green-600' : 'text-red-500'">
              {{ tx.type === 'credit' ? '+' : '-' }}{{ fmtMoney(tx.amount) }}
            </span>
          </div>
        </div>
        <p v-else class="text-center py-6 text-sm text-gray-400">لا توجد معاملات</p>
      </div>

      <!-- ═══ Loyalty ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <StarIcon class="w-4 h-4 text-yellow-500" /> برنامج الولاء
          </h3>
        </div>
        <div class="p-5">
          <div class="flex items-center justify-between mb-3">
            <div>
              <p class="text-3xl font-black text-yellow-500">{{ loyaltyPoints }}</p>
              <p class="text-xs text-gray-400">نقطة متاحة</p>
            </div>
            <div class="text-left">
              <p class="text-xs text-gray-500">المستوى</p>
              <span class="text-sm font-bold px-3 py-1 rounded-full" :class="loyaltyTierClass">{{ loyaltyTier }}</span>
            </div>
          </div>
          <!-- Progress bar to next tier -->
          <div class="space-y-1.5">
            <div class="flex justify-between text-xs text-gray-400">
              <span>{{ loyaltyPoints }} / {{ loyaltyNextTier }} نقطة</span>
              <span>{{ loyaltyTierNext }}</span>
            </div>
            <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   :style="{ width: `${Math.min((loyaltyPoints / loyaltyNextTier) * 100, 100)}%` }"
                   :class="loyaltyBarClass"
              ></div>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ vehicle.work_orders_count || 0 }}</p>
              <p class="text-[10px] text-gray-400">زيارة كلية</p>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ fmtMoney(totalSpent) }}</p>
              <p class="text-[10px] text-gray-400">إجمالي الإنفاق</p>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ pointsRedeemed }}</p>
              <p class="text-[10px] text-gray-400">نقاط محصلة</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ Tracking & Dashcam ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <MapPinIcon class="w-4 h-4 text-blue-600" /> التتبع والكاميرات
          </h3>
          <span v-if="vehicle.tracking_id" class="flex items-center gap-1.5 text-xs text-green-600 font-medium">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> متصل
          </span>
          <span v-else class="text-xs text-gray-400">غير مرتبط</span>
        </div>
        <div class="p-5">
          <div v-if="vehicle.tracking_id" class="space-y-3">
            <!-- Live Map placeholder -->
            <div class="h-40 bg-gradient-to-br from-blue-50 to-teal-50 dark:from-blue-900/20 dark:to-teal-900/20 rounded-xl border border-blue-100 dark:border-blue-800 flex items-center justify-center relative overflow-hidden">
              <div class="absolute inset-0 opacity-20 dark:opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%236366f1\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M20 20h1v1h-1z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
              <div class="text-center z-10">
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-2 shadow-lg shadow-blue-500/40">
                  <MapPinIcon class="w-5 h-5 text-white" />
                </div>
                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">عرض الموقع الحي</p>
                <a :href="trackingUrl" target="_blank" class="text-xs text-blue-500 hover:underline mt-1 block">فتح في نظام التتبع ←</a>
              </div>
            </div>
            <!-- Dashcam -->
            <div v-if="vehicle.dashcam_id" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-xl">
              <VideoCameraIcon class="w-5 h-5 text-gray-500" />
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-800 dark:text-slate-200">داش كام متصل</p>
                <p class="text-xs text-gray-400">آخر مقطع: {{ lastDashcamEvent }}</p>
              </div>
              <a :href="dashcamUrl" target="_blank" class="text-xs bg-gray-200 dark:bg-slate-600 hover:bg-gray-300 px-2.5 py-1 rounded-lg transition-colors">مشاهدة</a>
            </div>
          </div>
          <div v-else class="text-center py-6 space-y-3">
            <MapPinIcon class="w-10 h-10 text-gray-200 mx-auto" />
            <p class="text-sm text-gray-400">لا يوجد نظام تتبع مرتبط</p>
            <RouterLink to="/settings/integrations" class="text-xs text-primary-600 hover:underline">ربط نظام تتبع من الإعدادات ←</RouterLink>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="rounded-2xl border border-gray-200 bg-gray-50/80 px-5 py-12 text-center text-sm text-gray-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300" role="status">
      لا تتوفر بيانات للعرض. ارجع لملف المركبة أو أعد المحاولة.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  TruckIcon, UserCircleIcon, ClipboardDocumentIcon, CreditCardIcon, StarIcon,
  MapPinIcon, VideoCameraIcon, ArrowUpIcon, ArrowDownIcon, ArrowRightIcon,
  ArrowDownTrayIcon, ShareIcon, DevicePhoneMobileIcon, PhotoIcon,
  ClockIcon, CheckCircleIcon, WrenchScrewdriverIcon, ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { ensurePrintFontsReady } from '@/composables/useAppPrint'
import ShareModal from '@/components/ShareModal.vue'
import { getQRImageUrl } from '@/utils/zatca'
import { workOrderStatusLabel, workOrderStatusBadgeClass } from '@/utils/workOrderStatusLabels'

const route = useRoute()
const toast = useToast()
const auth = useAuthStore()
const vehicleId = Number(route.params.id)
const loading = ref(true)
const loadError = ref<string | null>(null)
const vehicle = ref<any>(null)
const workOrders = ref<any[]>([])
const transactions = ref<any[]>([])
const cardRef = ref<HTMLElement | null>(null)
const shareImageBusy = ref(false)
const identityBusy = ref(false)

const canManageIdentity = computed(() => auth.hasPermission('vehicles.update'))
const identityHasActiveLink = computed(() => {
  const i = vehicle.value?.identity as { public_url?: string; status?: string } | undefined
  return !!(i?.public_url && i?.status === 'active')
})

const qrUrl = computed(() => {
  const u = vehicle.value?.identity?.public_url
  if (typeof u === 'string' && u.trim() !== '') return u.trim()
  return ''
})
const qrUrlDisplay = computed(() => {
  if (!qrUrl.value) return '—'
  try {
    const u = new URL(qrUrl.value)
    return u.host + u.pathname
  } catch {
    return qrUrl.value
  }
})
const qrImageUrl = computed(() => (qrUrl.value ? getQRImageUrl(qrUrl.value, 200) : ''))
const trackingUrl = computed(() => vehicle.value?.tracking_url || '#')
const dashcamUrl = computed(() => vehicle.value?.dashcam_url || '#')
const lastDashcamEvent = computed(() => vehicle.value?.last_dashcam_event || 'غير معروف')
const walletBalance = computed(() => vehicle.value?.wallet_balance ?? 0)
const loyaltyPoints = computed(() => vehicle.value?.loyalty_points ?? 0)
const totalSpent = computed(() => vehicle.value?.total_spent ?? 0)
const pointsRedeemed = computed(() => vehicle.value?.points_redeemed ?? 0)

const lastVisit = computed(() => {
  const wo = workOrders.value[0]
  if (!wo) return 'لا يوجد'
  return new Date(wo.created_at).toLocaleDateString('ar-SA-u-ca-gregory', { day: 'numeric', month: 'short', year: 'numeric' })
})

/** بنفسجي عميق مقارب لبطاقات المحفظة (مع اختلاف خفيف حسب الحالة) */
const cardGradientCss = computed(() => {
  const b = walletBalance.value
  const wo = workOrders.value.find(w => ['in_progress', 'assigned'].includes(w.status))
  if (wo) {
    return 'linear-gradient(168deg, #312e81 0%, #4c1d95 45%, #5b21b6 100%)'
  }
  if (b < 0) {
    return 'linear-gradient(168deg, #4a1942 0%, #7f1d5c 42%, #4c0519 100%)'
  }
  if (b === 0) {
    return 'linear-gradient(168deg, #3b2f52 0%, #4a1d6e 50%, #3d1f5c 100%)'
  }
  return 'linear-gradient(168deg, #3d1f6b 0%, #5b21b6 38%, #4c1d95 88%)'
})

/** شريط الحالة العلوي: طول الجزء الفاتح (الباقي أخضر) */
const statusBarLightPct = computed(() => {
  if (walletBalance.value < 0) return 42
  const wo = workOrders.value.find(w => ['in_progress', 'assigned'].includes(w.status))
  if (wo) return 78
  return 68
})

const walletBrandLine1 = computed(() => vehicle.value?.make || 'مركبة')

const walletBrandLine2 = computed(() => 'بطاقة رقمية')

const statusDot = computed(() => {
  const wo = workOrders.value.find(w => ['in_progress', 'assigned'].includes(w.status))
  if (wo) return 'bg-blue-400'
  if (walletBalance.value < 0) return 'bg-red-400'
  return 'bg-green-400'
})

const statusLabel = computed(() => {
  const wo = workOrders.value.find(w => w.status === 'in_progress')
  if (wo) return 'في مركز الخدمة'
  const wo2 = workOrders.value.find(w => w.status === 'assigned')
  if (wo2) return 'موعد مجدول'
  if (walletBalance.value < 0) return 'رصيد سالب'
  return 'طبيعي'
})

const loyaltyTier = computed(() => {
  const p = loyaltyPoints.value
  if (p >= 5000) return 'بلاتيني'
  if (p >= 2000) return 'ذهبي'
  if (p >= 500) return 'فضي'
  return 'برونزي'
})
const loyaltyTierNext = computed(() => {
  const p = loyaltyPoints.value
  if (p >= 5000) return '✓ أعلى مستوى'
  if (p >= 2000) return 'بلاتيني'
  if (p >= 500) return 'ذهبي'
  return 'فضي'
})
const loyaltyNextTier = computed(() => {
  const p = loyaltyPoints.value
  if (p >= 5000) return 5000
  if (p >= 2000) return 5000
  if (p >= 500) return 2000
  return 500
})
const loyaltyTierClass = computed(() => {
  const t = loyaltyTier.value
  if (t === 'بلاتيني') return 'bg-purple-100 text-purple-700'
  if (t === 'ذهبي') return 'bg-yellow-100 text-yellow-700'
  if (t === 'فضي') return 'bg-gray-100 text-gray-600'
  return 'bg-orange-100 text-orange-700'
})
const loyaltyBarClass = computed(() => {
  const t = loyaltyTier.value
  if (t === 'بلاتيني') return 'bg-purple-500'
  if (t === 'ذهبي') return 'bg-yellow-400'
  return 'bg-gray-400'
})

function fmtMoney(v: number) {
  return new Intl.NumberFormat('ar-SA', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v)
}
function formatDate(d: string) {
  return new Date(d).toLocaleDateString('ar-SA-u-ca-gregory', { day: 'numeric', month: 'short' })
}

function woIcon(s: string) {
  if (s === 'completed') return CheckCircleIcon
  if (s === 'in_progress') return WrenchScrewdriverIcon
  if (s === 'pending' || s === 'new') return ClockIcon
  return ExclamationCircleIcon
}
function woIconBg(s: string) {
  if (s === 'completed') return 'bg-green-100'
  if (s === 'in_progress') return 'bg-blue-100'
  return 'bg-gray-100'
}
function woIconColor(s: string) {
  if (s === 'completed') return 'text-green-600'
  if (s === 'in_progress') return 'text-blue-600'
  return 'text-gray-500'
}
async function downloadCard() {
  if (!cardRef.value) return
  try {
    await ensurePrintFontsReady()
    const { default: html2canvas } = await import('html2canvas')
    const canvas = await html2canvas(cardRef.value, {
      scale: 3,
      useCORS: true,
      logging: false,
      backgroundColor: null,
    })
    const a = document.createElement('a')
    a.download = `vehicle-card-${vehicle.value?.plate_number}.png`
    a.href = canvas.toDataURL('image/png')
    a.click()
    toast.success('تم التنزيل', 'تم حفظ صورة البطاقة.')
  } catch {
    if (qrUrl.value) {
      try {
        await navigator.clipboard.writeText(qrUrl.value)
        toast.info('نسخ الرابط', 'تعذّر إنشاء الصورة — تم نسخ رابط البطاقة للحافظة.')
      } catch {
        toast.warning('تعذّر التصدير', 'جرّب لقطة شاشة يدوية أو افتح الرابط من المتصفح.')
      }
    } else {
      toast.warning('تعذّر التصدير', 'لا يوجد رابط عام للنسخ — تحقق من إعداد الواجهة العامة.')
    }
  }
}

async function shareCardQuick() {
  const v = vehicle.value
  const url = qrUrl.value
  if (!url) {
    toast.warning('لا يوجد رابط عام', 'تأكد من تحميل البطاقة أو من إعداد عنوان الواجهة العامة (APP_PUBLIC_URL).')
    return
  }
  const title = v ? `${v.make} ${v.model} — ${v.plate_number}` : 'بطاقة مركبة'
  const text = v ? `بطاقتي الرقمية: ${v.plate_number}\n${url}` : url
  if (typeof navigator !== 'undefined' && navigator.share) {
    try {
      await navigator.share({ title, text, url })
      return
    } catch (e: unknown) {
      if (e && typeof e === 'object' && (e as Error).name === 'AbortError') return
    }
  }
  try {
    await navigator.clipboard.writeText(`${text}\n${url}`)
    toast.success('تم النسخ', 'رابط البطاقة في الحافظة — الصقه في واتساب أو البريد.')
  } catch {
    toast.error('تعذّرت المشاركة', 'جرّب «خيارات متقدمة» أو التحميل كصورة.')
  }
}

async function shareCardAsImage() {
  if (!cardRef.value) return
  if (!qrUrl.value) {
    toast.warning('لا يوجد رابط عام', 'تأكد من تحميل البطاقة أو من إعداد عنوان الواجهة العامة (APP_PUBLIC_URL).')
    return
  }
  shareImageBusy.value = true
  try {
    await ensurePrintFontsReady()
    const { default: html2canvas } = await import('html2canvas')
    const canvas = await html2canvas(cardRef.value, {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: null,
    })
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png', 0.92))
    if (!blob) {
      throw new Error('no blob')
    }
    const name = `vehicle-card-${vehicle.value?.plate_number ?? vehicleId}.png`
    const file = new File([blob], name, { type: 'image/png' })
    const title = vehicle.value ? `${vehicle.value.make} ${vehicle.value.model}` : 'بطاقة مركبة'
    const text = vehicle.value ? `بطاقة رقمية — ${vehicle.value.plate_number}` : 'بطاقة رقمية'
    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ title, text, url: qrUrl.value, files: [file] })
      toast.success('تمت المشاركة', 'يمكن حفظ الصورة في الألبوم أو إرسالها.')
      return
    }
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = name
    a.click()
    URL.revokeObjectURL(a.href)
    toast.info('تنزيل تلقائي', 'المتصفّح لا يدعم مشاركة الصورة — تم تنزيل الملف.')
  } catch (e: unknown) {
    if (e && typeof e === 'object' && (e as Error).name === 'AbortError') return
    await downloadCard()
  } finally {
    shareImageBusy.value = false
  }
}

async function copyIdentityUrl() {
  if (!qrUrl.value) {
    toast.warning('لا يوجد رابط', 'أصدر رابطاً جديداً أو تحقق من الإعدادات.')
    return
  }
  try {
    await navigator.clipboard.writeText(qrUrl.value)
    toast.success('تم النسخ', 'رابط المسح في الحافظة.')
  } catch {
    toast.error('تعذّر النسخ', 'انسخ الرابط يدوياً من شريط العنوان إن وُجد.')
  }
}

async function rotateVehicleIdentity() {
  if (!canManageIdentity.value) return
  identityBusy.value = true
  try {
    const { data } = await apiClient.post(`/vehicles/${vehicleId}/identity/rotate`)
    const block = data?.data
    if (vehicle.value && block) {
      vehicle.value.identity = block
    }
    toast.success('تم التدوير', 'تم إنشاء رمز جديد — أي QR أو طباعة قديمة تتوقف عن العمل.')
  } catch {
    toast.error('تعذّر التدوير', 'تحقق من الصلاحيات ثم أعد المحاولة.')
  } finally {
    identityBusy.value = false
  }
}

async function revokeVehicleIdentity() {
  if (!canManageIdentity.value) return
  if (!window.confirm('سيتم إبطال الرابط الحالي. أي QR مطبوع أو محفوظ سيتوقف عن العمل حتى تُصدر رابطاً جديداً. متابعة؟')) {
    return
  }
  identityBusy.value = true
  try {
    const { data } = await apiClient.post(`/vehicles/${vehicleId}/identity/revoke`)
    const block = data?.data
    if (vehicle.value && block) {
      vehicle.value.identity = block
    }
    toast.success('تم الإبطال', 'يمكنك لاحقاً إصدار رابط جديد من هنا.')
  } catch {
    toast.error('تعذّر الإبطال', 'تحقق من الصلاحيات ثم أعد المحاولة.')
  } finally {
    identityBusy.value = false
  }
}

async function issueVehicleIdentity() {
  if (!canManageIdentity.value) return
  identityBusy.value = true
  try {
    const { data } = await apiClient.post(`/vehicles/${vehicleId}/identity/issue`)
    const block = data?.data
    if (vehicle.value && block) {
      vehicle.value.identity = block
    }
    toast.success('تم الإصدار', 'رابط المسح جاهز — يمكنك الطباعة أو المشاركة.')
  } catch {
    toast.error('تعذّر الإصدار', 'تحقق من الصلاحيات ثم أعد المحاولة.')
  } finally {
    identityBusy.value = false
  }
}

async function fetchDigitalCard() {
  loading.value = true
  loadError.value = null
  vehicle.value = null
  workOrders.value = []
  transactions.value = []
  try {
    const res = await apiClient.get(`/vehicles/${vehicleId}/digital-card`)
    const data = res.data?.data
    if (!data) {
      loadError.value = 'لا توجد بيانات لهذه البطاقة الرقمية.'
      return
    }
    vehicle.value = data
    workOrders.value = res.data.work_orders || []
    transactions.value = res.data.transactions || []
  } catch (e: unknown) {
    const msg =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    loadError.value =
      typeof msg === 'string' && msg.trim() !== ''
        ? msg
        : 'تعذّر تحميل البطاقة الرقمية. تحقق من الاتصال أو الصلاحيات ثم أعد المحاولة.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void fetchDigitalCard()
})
</script>

<style scoped>
.wallet-card-shine {
  background: linear-gradient(
    125deg,
    transparent 0%,
    rgba(255, 255, 255, 0.08) 40%,
    transparent 55%,
    rgba(255, 255, 255, 0.04) 100%
  );
  mix-blend-mode: overlay;
}

.wallet-stat-cell {
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.06) inset;
}

.vehicle-wallet-card {
  transform: translateZ(0);
}

/* أخدود علوي خفيف على طبقات المكدس (مشابه لبطاقات المحفظة) */
.wallet-stack-layer {
  position: relative;
}
.wallet-stack-layer::after {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  width: 22px;
  height: 4px;
  transform: translate(-50%, -25%);
  border-radius: 0 0 10px 10px;
  background: rgba(0, 0, 0, 0.12);
  pointer-events: none;
}
.dark .wallet-stack-layer::after {
  background: rgba(255, 255, 255, 0.12);
}
</style>
