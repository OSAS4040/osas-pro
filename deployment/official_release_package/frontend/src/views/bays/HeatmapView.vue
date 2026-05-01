<template>
  <div class="min-h-screen heatmap-page" dir="rtl">
    <div class="max-w-screen-2xl mx-auto px-4 py-6 space-y-6 pb-12">
      <!-- ══ Hero ═══════════════════════════════════════════════ -->
      <header class="relative overflow-hidden rounded-3xl border border-orange-200/60 dark:border-orange-900/40 bg-gradient-to-br from-amber-50 via-white to-orange-50/80 dark:from-slate-900 dark:via-slate-900 dark:to-orange-950/30 shadow-lg shadow-orange-500/5">
        <div class="absolute inset-0 opacity-[0.35] dark:opacity-20 pointer-events-none heatmap-hero-grid"></div>
        <div class="absolute top-0 left-0 w-72 h-72 bg-orange-400/20 dark:bg-orange-500/10 rounded-full blur-3xl -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-64 h-64 bg-rose-400/15 dark:bg-rose-500/10 rounded-full blur-3xl translate-y-1/2"></div>
        <div class="relative px-5 py-6 sm:px-8 sm:py-7 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-orange-500/15 text-orange-800 dark:text-orange-300 border border-orange-400/20">
              <SparklesIcon class="w-3.5 h-3.5" />
              تحليل ذكي للشغل والفراغ
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tracking-tight">
              الخريطة الحرارية
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 max-w-xl leading-relaxed">
              تتبّع إشغال كل منطقة عمل بالساعة، اكتشف الذروة والفجوات، وقرّب القرار على بيانات اليوم.
            </p>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <router-link to="/bays"
                           class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline"
              >
                <ArrowLeftIcon class="w-3.5 h-3.5" />
                إدارة المناطق
              </router-link>
            </div>
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-shrink-0">
            <div class="flex items-center gap-1.5 flex-wrap justify-end sm:justify-start">
              <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl border border-slate-200/80 dark:border-slate-600 bg-white/90 dark:bg-slate-800/90 backdrop-blur text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800 shadow-sm transition-all"
                      @click="goToToday"
              >
                <CalendarIcon class="w-4 h-4 text-primary-600" />
                اليوم
              </button>
              <button type="button" class="p-2 rounded-xl border border-slate-200/80 dark:border-slate-600 bg-white/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800 shadow-sm transition-all"
                      @click="prevDay"
              >
                <ChevronRightIcon class="w-4 h-4" />
              </button>
              <SmartDatePicker :model-value="date" mode="single" @change="onHeatmapDateChange" />
              <select
                v-if="branches.length > 1"
                v-model="selectedBranchId"
                class="rounded-xl border border-slate-200/80 dark:border-slate-600 px-3 py-2 text-sm bg-white/90 dark:bg-slate-800/90 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-sm max-w-[200px]"
                @change="load"
              >
                <option v-for="br in branches" :key="br.id" :value="br.id">{{ br.name_ar || br.name }}</option>
              </select>
              <button type="button" class="p-2 rounded-xl border border-slate-200/80 dark:border-slate-600 bg-white/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800 shadow-sm transition-all"
                      @click="nextDay"
              >
                <ChevronLeftIcon class="w-4 h-4" />
              </button>
            </div>
            <router-link to="/bookings"
                         class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-gradient-to-l from-primary-600 to-primary-500 hover:from-primary-500 hover:to-primary-400 text-white shadow-md shadow-primary-600/25 transition-all active:scale-[0.98]"
            >
              <PlusIcon class="w-4 h-4" />
              حجز جديد
            </router-link>
          </div>
        </div>
      </header>

      <!-- ══ Smart insights ═════════════════════════════════════ -->
      <section v-if="!loading && insights.length" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div
          v-for="(ins, i) in insights"
          :key="i"
          class="group relative rounded-2xl border p-4 overflow-hidden transition-all duration-300 hover:shadow-md"
          :class="ins.cardClass"
        >
          <div class="absolute top-0 left-0 w-16 h-16 rounded-br-full opacity-30 group-hover:scale-110 transition-transform" :class="ins.glowClass"></div>
          <div class="relative flex gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center" :class="ins.iconWrap">
              <component :is="ins.icon" class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ ins.label }}</p>
              <p class="text-sm font-bold text-slate-900 dark:text-white mt-1 leading-snug">{{ ins.title }}</p>
              <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">{{ ins.detail }}</p>
            </div>
          </div>
        </div>
      </section>

      <HeatmapHourlyChart
        v-if="!loading && heatmap.length"
        :hours="hours"
        :totals="hourlyTotals"
      />

      <!-- ══ Analytics KPIs ═══════════════════════════════════════ -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="group rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 backdrop-blur px-4 py-3.5 shadow-sm hover:shadow-md hover:border-primary-200 dark:hover:border-primary-800/50 transition-all">
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
            <Squares2X2Icon class="w-4 h-4 text-slate-400" />
            مناطق العمل
          </div>
          <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ analytics.total }}</span>
        </div>
        <div class="group rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 backdrop-blur px-4 py-3.5 shadow-sm hover:shadow-md transition-all">
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
            <BoltIcon class="w-4 h-4 text-rose-500" />
            مشغولة الآن
          </div>
          <span class="text-2xl font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ analytics.occupied }}</span>
        </div>
        <div class="group rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 backdrop-blur px-4 py-3.5 shadow-sm hover:shadow-md transition-all">
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
            <CheckCircleIcon class="w-4 h-4 text-emerald-500" />
            متاحة الآن
          </div>
          <span class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ analytics.available }}</span>
        </div>
        <div class="group rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 backdrop-blur px-4 py-3.5 shadow-sm hover:shadow-md transition-all sm:col-span-1 col-span-2">
          <div class="flex items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
            <span class="flex items-center gap-2">
              <ChartBarIcon class="w-4 h-4 text-amber-500" />
              إشغال اليوم (ساعات)
            </span>
            <span class="font-bold text-amber-600 dark:text-amber-400 tabular-nums">{{ analytics.utilizationPct }}٪</span>
          </div>
          <div class="w-full bg-slate-200/80 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
            <div class="h-2 rounded-full transition-all duration-700 ease-out heatmap-kpi-bar"
                 :class="analytics.utilizationPct > 80 ? 'bg-gradient-to-l from-rose-500 to-orange-400' : analytics.utilizationPct > 50 ? 'bg-gradient-to-l from-amber-500 to-yellow-400' : 'bg-gradient-to-l from-emerald-500 to-teal-400'"
                 :style="{ width: Math.min(100, analytics.utilizationPct) + '%' }"
            ></div>
          </div>
        </div>
        <div class="group rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 backdrop-blur px-4 py-3.5 shadow-sm hover:shadow-md transition-all">
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
            <BookmarkIcon class="w-4 h-4 text-primary-500" />
            حجوزات اليوم
          </div>
          <span class="text-2xl font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ analytics.bookingsToday }}</span>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-24">
        <div class="flex flex-col items-center gap-4">
          <div class="relative w-14 h-14">
            <div class="absolute inset-0 rounded-full border-2 border-primary-200 dark:border-primary-900"></div>
            <div class="absolute inset-0 rounded-full border-2 border-transparent border-t-primary-600 animate-spin"></div>
            <FireIcon class="absolute inset-0 m-auto w-6 h-6 text-orange-500 animate-pulse" />
          </div>
          <span class="text-sm font-medium text-slate-500 dark:text-slate-400">جاري بناء الخريطة...</span>
        </div>
      </div>

      <template v-else>
        <!-- ══ Live Status Cards ════════════════════════════════════ -->
        <div>
          <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            الحالة المباشرة لمناطق العمل
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <div v-for="bay in bays" :key="bay.id"
                 class="relative bg-white/90 dark:bg-slate-800/90 backdrop-blur rounded-2xl border-2 shadow-sm p-4 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg cursor-default"
                 :class="bayCardBorderClass(bay.status)"
            >
              <!-- Bottleneck Badge -->
              <div v-if="bottleneckBays.has(bay.id)"
                   class="absolute -top-2 -left-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full flex items-center gap-1 shadow"
              >
                <ExclamationTriangleIcon class="w-3 h-3" />
                مرتفع
              </div>
              <div class="flex items-start justify-between mb-2">
                <div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ bay.name }}</p>
                  <p class="text-xs text-gray-400 dark:text-gray-500">{{ bay.code }}</p>
                </div>
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-0.5" :class="bayStatusDot(bay.status)"></span>
              </div>
              <div class="mt-auto">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
                      :class="bayStatusBadge(bay.status)"
                >
                  {{ statusLabel(bay.status) }}
                </span>
                <p v-if="bay.status === 'occupied' && bay.current_work_order_id"
                   class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-1"
                >
                  <TruckIcon class="w-3 h-3" />
                  أمر عمل #{{ bay.current_work_order_id }}
                </p>
                <p v-if="nextBookingFor(bay.id)" class="text-xs text-gray-400 dark:text-gray-500 mt-1 flex items-center gap-1">
                  <ClockIcon class="w-3 h-3" />
                  {{ nextBookingFor(bay.id) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ Smart Heatmap Grid ═══════════════════════════════════ -->
        <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 backdrop-blur shadow-lg shadow-slate-200/50 dark:shadow-none overflow-hidden">
          <div class="px-4 py-3.5 border-b border-slate-200/80 dark:border-slate-700 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between bg-gradient-to-l from-slate-50/80 to-transparent dark:from-slate-800/50">
            <div class="flex items-center gap-3 flex-wrap">
              <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400">
                  <FireIcon class="w-4 h-4" />
                </span>
                شبكة الإشغال — {{ formatDateAr(date) }}
              </h2>
              <span v-if="peakHour !== null" class="text-xs font-medium px-2.5 py-1 rounded-lg bg-amber-500/15 text-amber-800 dark:text-amber-300 border border-amber-400/20">
                ذروة محتملة حوالي {{ peakHour }}:00
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 justify-end">
              <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                      :class="dimQuietHours
                        ? 'bg-primary-50 border-primary-200 text-primary-800 dark:bg-primary-900/30 dark:border-primary-700 dark:text-primary-300'
                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300'"
                      @click="dimQuietHours = !dimQuietHours"
              >
                <EyeIcon class="w-3.5 h-3.5" />
                إبراز الذروة
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/80 transition-all"
                      @click="compactGrid = !compactGrid"
              >
                <ArrowsPointingOutIcon class="w-3.5 h-3.5" />
                {{ compactGrid ? 'عرض مريح' : 'عرض مضغوط' }}
              </button>
            </div>
            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-[11px] text-slate-500 dark:text-slate-400 w-full sm:w-auto sm:justify-end">
              <div class="flex items-center gap-1.5">
                <div class="w-3.5 h-3.5 rounded bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600"></div>
                فارغ
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-3.5 h-3.5 rounded shadow-sm" style="background: linear-gradient(135deg,#34d399,#059669)"></div>
                حجز
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-3.5 h-3.5 rounded shadow-sm" style="background: linear-gradient(135deg,#fbbf24,#ea580c)"></div>
                متعدد
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-3.5 h-3.5 rounded shadow-sm" style="background: linear-gradient(135deg,#f87171,#b91c1c)"></div>
                ضغط
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <div class="relative min-w-max">
              <!-- Current time indicator line -->
              <div v-if="isToday && currentHourOffset !== null"
                   class="absolute top-0 bottom-0 w-px z-20 pointer-events-none bg-gradient-to-b from-primary-400 via-primary-500 to-primary-600 shadow-[0_0_12px_rgba(59,130,246,0.6)]"
                   :style="{ right: currentHourOffset + 'px' }"
              >
                <div class="absolute -top-0.5 right-1/2 translate-x-1/2 bg-primary-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-md shadow-md whitespace-nowrap">
                  الآن
                </div>
              </div>

              <!-- Hours Header -->
              <div class="flex border-b border-slate-200/80 dark:border-slate-700 bg-slate-50/90 dark:bg-slate-900/80">
                <div
                  class="flex-shrink-0 px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 border-r border-slate-200/80 dark:border-slate-700 sticky right-0 z-10 backdrop-blur-md"
                  :class="[stickyBayClass, 'bg-slate-50/95 dark:bg-slate-900/95']"
                >
                  منطقة العمل
                </div>
                <div
                  class="flex-shrink-0 px-2 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 border-r border-slate-200/80 dark:border-slate-700 text-center bg-slate-50/90 dark:bg-slate-900/80"
                  :class="stickyUtilClass"
                >
                  إشغال
                </div>
                <div v-for="h in hours" :key="'h-'+h"
                     class="flex-shrink-0 px-0.5 py-3 text-[11px] text-center font-medium border-r border-slate-200/80 dark:border-slate-700 last:border-r-0 transition-opacity duration-300"
                     :class="[
                       hourColClass,
                       h === currentHour && isToday ? 'bg-primary-100/80 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-bold' : 'text-slate-500 dark:text-slate-400 bg-slate-50/90 dark:bg-slate-900/80',
                       dimQuietHours && peakHour !== null && h !== peakHour ? 'opacity-45' : '',
                     ]"
                >
                  <span class="tabular-nums">{{ h }}</span><span class="text-[9px] opacity-70">:00</span>
                </div>
              </div>

              <!-- Bay Rows -->
              <div
                v-for="(bay, rowIdx) in heatmap"
                :key="bay.bay_id"
                class="flex border-b border-slate-200/60 dark:border-slate-700/80 last:border-0 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 group transition-colors heatmap-row-enter"
                :style="{ animationDelay: `${Math.min(rowIdx, 12) * 35}ms` }"
              >
                <!-- Bay Name + sparkline -->
                <div
                  class="flex-shrink-0 px-3 py-3 border-r border-slate-200/80 dark:border-slate-700 sticky right-0 z-10 transition-colors backdrop-blur-md"
                  :class="[stickyBayClass, 'bg-white/95 dark:bg-slate-800/95 group-hover:bg-slate-50/95 dark:group-hover:bg-slate-800/95']"
                >
                  <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ring-2 ring-white dark:ring-slate-800" :class="bayStatusDot(getBayStatus(bay.bay_id))"></span>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-slate-900 dark:text-white leading-tight truncate">{{ bay.bay_name }}</p>
                      <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ bay.bay_code }}</p>
                      <div class="mt-2 flex gap-px h-2 rounded-md overflow-hidden bg-slate-100 dark:bg-slate-700/80 p-px">
                        <div
                          v-for="h in hours" :key="'sl-'+bay.bay_id+'-'+h"
                          class="flex-1 min-w-0 first:rounded-r-sm last:rounded-l-sm transition-all duration-300"
                          :class="sparkSegmentClass(bay, h)"
                          :title="`${h}:00 — ${(bay.slots?.[h] ?? 0) ? 'مشغول' : 'فارغ'}`"
                        />
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Utilization % -->
                <div
                  class="flex-shrink-0 px-2 py-3 border-r border-slate-200/80 dark:border-slate-700 flex flex-col items-center justify-center bg-white/50 dark:bg-slate-800/30"
                  :class="stickyUtilClass"
                >
                  <span class="text-xs font-bold tabular-nums"
                        :class="bayUtilization(bay) > 80 ? 'text-red-600 dark:text-red-400' : bayUtilization(bay) > 50 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                  >
                    {{ bayUtilization(bay) }}٪
                  </span>
                  <div class="w-full bg-slate-200 dark:bg-slate-600 rounded-full h-1 mt-1 overflow-hidden">
                    <div class="h-1 rounded-full transition-all duration-700"
                         :class="bayUtilization(bay) > 80 ? 'bg-gradient-to-l from-rose-500 to-red-400' : bayUtilization(bay) > 50 ? 'bg-gradient-to-l from-amber-500 to-orange-400' : 'bg-gradient-to-l from-emerald-500 to-teal-400'"
                         :style="{ width: bayUtilization(bay) + '%' }"
                    ></div>
                  </div>
                  <ExclamationTriangleIcon v-if="bottleneckBays.has(bay.bay_id)" class="w-3.5 h-3.5 text-rose-500 mt-1 animate-pulse" />
                </div>
                <!-- Hour Cells -->
                <div v-for="h in hours" :key="'c-'+bay.bay_id+'-'+h"
                     class="flex-shrink-0 p-0.5 border-r border-slate-200/60 dark:border-slate-700/80 last:border-r-0 relative transition-opacity duration-300"
                     :class="[
                       hourColClass,
                       h === currentHour && isToday ? 'bg-primary-50/40 dark:bg-primary-900/15' : '',
                       dimQuietHours && peakHour !== null && h !== peakHour ? 'opacity-40' : '',
                     ]"
                >
                  <div
                    class="rounded-lg flex items-center justify-center cursor-pointer transition-all duration-200 hover:scale-[1.08] hover:shadow-lg hover:z-10 relative group/cell ring-1 ring-transparent hover:ring-white/30"
                    :class="compactGrid ? 'h-8' : 'h-10'"
                    :style="cellStyle(bay.slots?.[h])"
                    @click="openCellDetail(bay, h)"
                  >
                    <span v-if="(bay.slots?.[h] ?? 0) > 0" class="text-[10px] font-bold text-white drop-shadow-sm tabular-nums">{{ bay.slots[h] }}</span>
                    <div class="absolute bottom-full mb-2 right-1/2 translate-x-1/2 bg-slate-900 dark:bg-slate-700 text-white text-xs rounded-xl px-3 py-2 whitespace-nowrap opacity-0 invisible group-hover/cell:opacity-100 group-hover/cell:visible z-30 transition-all duration-150 shadow-2xl border border-white/10">
                      <p class="font-semibold">{{ bay.bay_name }} · {{ h }}:00</p>
                      <p class="text-slate-300 mt-0.5">{{ cellTooltipLine(bay, h) }}</p>
                      <div class="absolute top-full right-1/2 translate-x-1/2 border-8 border-transparent border-t-slate-900 dark:border-t-slate-700"></div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!heatmap.length" class="text-center py-20 px-6">
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 mb-4">
                  <ChartBarIcon class="w-8 h-8 text-slate-300 dark:text-slate-600" />
                </div>
                <p class="text-slate-600 dark:text-slate-300 font-medium">لا توجد مناطق عمل أو لا بيانات إشغال لهذا اليوم</p>
                <p class="text-xs text-slate-400 mt-2 max-w-md mx-auto">أضف مناطق من «إدارة المناطق» ثم اربطها بالحجوزات لترى الخريطة تُملأ تلقائياً.</p>
                <router-link to="/bays" class="inline-flex mt-4 text-sm font-semibold text-primary-600 hover:underline">الانتقال إلى المناطق</router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ Bottleneck Alert Banner ══════════════════════════════ -->
        <div v-if="bottleneckBays.size > 0"
             class="relative overflow-hidden rounded-2xl border border-rose-200/80 dark:border-rose-900/50 bg-gradient-to-l from-rose-50 to-white dark:from-rose-950/40 dark:to-slate-900 p-4 flex items-start gap-3 shadow-md shadow-rose-500/10"
        >
          <div class="absolute top-0 left-0 w-24 h-24 bg-rose-400/10 rounded-full blur-2xl"></div>
          <ExclamationTriangleIcon class="relative w-6 h-6 text-rose-600 dark:text-rose-400 flex-shrink-0 mt-0.5" />
          <div class="relative">
            <p class="text-sm font-bold text-rose-900 dark:text-rose-200">اختناق: إشغال مرتفع في نافذة اليوم</p>
            <p class="text-xs text-rose-700 dark:text-rose-300/90 mt-1 leading-relaxed">
              المناطق التالية تجاوزت 80٪ من ساعات العمل ضمن الحجوزات:
              <span class="font-bold">{{ bottleneckNames }}</span>
            </p>
          </div>
        </div>

        <!-- ══ Bookings Table ═══════════════════════════════════════ -->
        <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 backdrop-blur shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2 bg-slate-50/50 dark:bg-slate-900/30">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
              <BookmarkIcon class="w-4 h-4 text-primary-500" />
              حجوزات {{ formatDateAr(date) }}
            </h3>
            <span class="inline-flex items-center gap-1 text-xs bg-primary-500/10 text-primary-800 dark:text-primary-300 px-2.5 py-1 rounded-full font-semibold border border-primary-500/20">
              {{ bookings.length }} حجز
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">منطقة العمل</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">البداية</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">المدة</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">الخدمة</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">الحالة</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="b in bookings" :key="b.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                >
                  <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ b.bay?.name ?? b.bay_id }}</td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs font-mono">{{ formatTime(b.starts_at) }}</td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">{{ b.duration_minutes }} دقيقة</td>
                  <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ b.service_type }}</td>
                  <td class="px-4 py-3">
                    <span :class="bookingStatusBadge(b.status)" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ bookingStatusLabel(b.status) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!bookings.length">
                  <td colspan="5" class="text-center py-12 text-gray-400 dark:text-gray-500">
                    <BookmarkIcon class="w-8 h-8 mx-auto mb-2 opacity-30" />
                    لا توجد حجوزات لهذا اليوم
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <!-- ══ Cell Detail Modal ════════════════════════════════════ -->
      <Transition name="fade">
        <div v-if="selectedCell" class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.self="selectedCell = null"
        >
          <div class="absolute inset-0 bg-black/40 dark:bg-black/60 backdrop-blur-sm"></div>
          <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-md border border-slate-200/80 dark:border-slate-600 overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-primary-500 to-orange-400"></div>
            <div class="flex items-start justify-between mb-4 pt-2">
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ selectedCell.bay.bay_name }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ selectedCell.hour }}:00 — {{ selectedCell.hour + 1 }}:00</p>
              </div>
              <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                      @click="selectedCell = null"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div v-if="selectedCell.count > 0">
              <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     :style="cellStyle(selectedCell.count)"
                >
                  <span class="text-sm font-bold text-white">{{ selectedCell.count }}</span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ selectedCell.count }} حجز في هذه الساعة</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCell.bay.bay_name }} — {{ selectedCell.hour }}:00</p>
                </div>
              </div>
              <div class="mt-3 space-y-2">
                <div v-for="b in cellBookings" :key="b.id"
                     class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-700/40 rounded-lg text-xs"
                >
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ b.service_type || 'خدمة' }}</span>
                  <span :class="bookingStatusBadge(b.status)" class="px-2 py-0.5 rounded-full font-medium">
                    {{ bookingStatusLabel(b.status) }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-6 text-gray-400 dark:text-gray-500">
              <CheckCircleIcon class="w-10 h-10 mx-auto mb-2 text-green-400" />
              <p class="text-sm font-medium text-gray-600 dark:text-gray-300">الفترة متاحة</p>
              <p class="text-xs mt-1">لا توجد حجوزات في هذه الساعة</p>
            </div>
            <button class="mt-4 w-full py-2 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    @click="selectedCell = null"
            >
              إغلاق
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, type Component } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import HeatmapHourlyChart from '@/components/bays/HeatmapHourlyChart.vue'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import {
  CalendarIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon,
  FireIcon,
  ExclamationTriangleIcon,
  BookmarkIcon,
  ChartBarIcon,
  TruckIcon,
  ClockIcon,
  XMarkIcon,
  CheckCircleIcon,
  SparklesIcon,
  BoltIcon,
  Squares2X2Icon,
  ArrowLeftIcon,
  EyeIcon,
  ArrowsPointingOutIcon,
  LightBulbIcon,
} from '@heroicons/vue/24/outline'

// ── Types ────────────────────────────────────────────────────────────
interface HeatmapRow {
  bay_id: number
  bay_name: string
  bay_code: string
  status: string
  slots: Record<number, number>
  /** نسبة من الخادم (ساعات بها إشغال / 15) */
  serverUtilization?: number
}

interface Bay {
  id: number
  name: string
  code: string
  status: string
  current_work_order_id?: number | null
}

interface Booking {
  id: number
  bay_id: number
  bay?: { name: string }
  starts_at: string
  duration_minutes: number
  service_type: string
  status: string
}

interface SelectedCell {
  bay: HeatmapRow
  hour: number
  count: number
}

interface InsightCard {
  label: string
  title: string
  detail: string
  icon: Component
  cardClass: string
  iconWrap: string
  glowClass: string
}

const auth = useAuthStore()

// ── State ────────────────────────────────────────────────────────────
const branches = ref<{ id: number; name: string; name_ar?: string }[]>([])
const selectedBranchId = ref<number | null>(null)
const date    = ref(new Date().toISOString().slice(0, 10))
const heatmap = ref<HeatmapRow[]>([])
const bays    = ref<Bay[]>([])
const bookings = ref<Booking[]>([])
const loading = ref(true)
const selectedCell = ref<SelectedCell | null>(null)
/** مطابق للخادم: 7–21 (15 ساعة) */
const hours = Array.from({ length: 15 }, (_, i) => i + 7)
const currentHour = ref(new Date().getHours())
const compactGrid = ref(false)
const dimQuietHours = ref(false)

// Update current hour every minute
let clockTimer: ReturnType<typeof setInterval> | null = null

// ── Computed ─────────────────────────────────────────────────────────
const isToday = computed(() => date.value === new Date().toISOString().slice(0, 10))

const cellW = computed(() => (compactGrid.value ? 40 : 56))
const stickyBayW = computed(() => (compactGrid.value ? 128 : 160))
const stickyUtilW = computed(() => (compactGrid.value ? 64 : 80))

const stickyBayClass = computed(() => (compactGrid.value ? 'w-32' : 'w-40'))
const stickyUtilClass = computed(() => (compactGrid.value ? 'w-16' : 'w-20'))
const hourColClass = computed(() => (compactGrid.value ? 'w-10' : 'w-14'))

const currentHourOffset = computed(() => {
  if (!isToday.value) return null
  const h = currentHour.value
  if (h < 7 || h > 21) return null
  const colsBefore = hours.indexOf(h)
  if (colsBefore < 0) return null
  return stickyBayW.value + stickyUtilW.value + colsBefore * cellW.value + cellW.value / 2
})

const peakHour = computed(() => {
  let bestH: number | null = null
  let bestC = -1
  for (const h of hours) {
    let c = 0
    for (const bay of heatmap.value) {
      if ((bay.slots?.[h] ?? 0) > 0) c++
    }
    if (c > bestC) {
      bestC = c
      bestH = h
    }
  }
  return bestC <= 0 ? null : bestH
})

const insights = computed((): InsightCard[] => {
  const out: InsightCard[] = []
  if (!heatmap.value.length) return out

  if (peakHour.value !== null) {
    const n = heatmap.value.filter(b => (b.slots?.[peakHour.value!] ?? 0) > 0).length
    out.push({
      label: 'ذروة الإشغال',
      title: `حوالي الساعة ${peakHour.value}:00`,
      detail: `${n} من ${heatmap.value.length} منطقة بها حجز يتقاطع مع هذه الساعة. خطط للطاقم والاستقبال.`,
      icon: BoltIcon,
      cardClass: 'border-amber-200/80 dark:border-amber-900/40 bg-gradient-to-br from-amber-50/90 to-white dark:from-amber-950/25 dark:to-slate-900/50',
      iconWrap: 'bg-amber-500/20 text-amber-700 dark:text-amber-300',
      glowClass: 'bg-amber-400',
    })
  }

  let minU = 101
  let minBay: HeatmapRow | null = null
  for (const b of heatmap.value) {
    const u = bayUtilization(b)
    if (u < minU) {
      minU = u
      minBay = b
    }
  }
  if (minBay && minU < 100) {
    out.push({
      label: 'منطقة أهدأ',
      title: minBay.bay_name,
      detail:
        minU === 0
          ? 'لا حجوزات مسجلة في نطاق ساعات العمل لهذا اليوم — مناسبة لصيانات طويلة أو دفعات جديدة.'
          : `نسبة إشغال الساعات حوالي ${minU}٪ مقارنة ببقية المناطق.`,
      icon: LightBulbIcon,
      cardClass: 'border-emerald-200/80 dark:border-emerald-900/40 bg-gradient-to-br from-emerald-50/80 to-white dark:from-emerald-950/20 dark:to-slate-900/50',
      iconWrap: 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300',
      glowClass: 'bg-emerald-400',
    })
  }

  let calmH: number | null = null
  let calmScore = 999
  for (const h of hours) {
    const busy = heatmap.value.reduce((s, b) => s + ((b.slots?.[h] ?? 0) > 0 ? 1 : 0), 0)
    if (busy < calmScore) {
      calmScore = busy
      calmH = h
    }
  }
  if (calmH !== null && calmScore < heatmap.value.length && calmH !== peakHour.value) {
    out.push({
      label: 'نافذة أنعم',
      title: `حوالي ${calmH}:00`,
      detail: `في هذه الساعة عدد المناطق المشغولة أقل (${calmScore} من ${heatmap.value.length}) — فرصة لحجوزات مرنة أو متابعة.`,
      icon: SparklesIcon,
      cardClass: 'border-primary-200/80 dark:border-primary-900/40 bg-gradient-to-br from-primary-50/80 to-white dark:from-primary-950/25 dark:to-slate-900/50',
      iconWrap: 'bg-primary-500/20 text-primary-700 dark:text-primary-300',
      glowClass: 'bg-primary-400',
    })
  }

  return out.slice(0, 3)
})

const hourlyTotals = computed(() =>
  hours.map((h) => heatmap.value.reduce((sum, b) => sum + (b.slots?.[h] ?? 0), 0)),
)

const analytics = computed(() => {
  const total     = bays.value.length
  const occupied  = bays.value.filter(b => b.status === 'occupied').length
  const available = bays.value.filter(b => b.status === 'available').length
  const allSlots  = heatmap.value.reduce((sum, bay) => sum + Object.values(bay.slots || {}).reduce((a: number, v: any) => a + (v || 0), 0), 0)
  const maxSlots  = heatmap.value.length * hours.length
  const rawPct      = maxSlots > 0 ? (allSlots / maxSlots) * 100 : 0
  const utilizationPct = Math.min(100, Math.round(rawPct))
  return { total, occupied, available, utilizationPct, bookingsToday: bookings.value.length }
})

const bottleneckBays = computed(() => {
  const set = new Set<number>()
  for (const bay of heatmap.value) {
    if (bayUtilization(bay) > 80) set.add(bay.bay_id)
  }
  return set
})

const bottleneckNames = computed(() =>
  heatmap.value
    .filter(b => bottleneckBays.value.has(b.bay_id))
    .map(b => b.bay_name)
    .join('، ')
)

const cellBookings = computed(() => {
  if (!selectedCell.value) return []
  const { bay, hour } = selectedCell.value
  return bookings.value.filter(b => {
    if ((b.bay_id ?? (b.bay as any)?.id) !== bay.bay_id) return false
    const start = new Date(b.starts_at)
    return start.getHours() === hour
  })
})

// ── Helpers ──────────────────────────────────────────────────────────
function bayUtilization(bay: HeatmapRow): number {
  if (bay.serverUtilization != null && !Number.isNaN(bay.serverUtilization)) {
    return Math.min(100, Math.round(bay.serverUtilization))
  }
  const filled = Object.values(bay.slots || {}).filter(v => v > 0).length
  return Math.round((filled / hours.length) * 100)
}

function getBayStatus(bayId: number): string {
  return bays.value.find(b => b.id === bayId)?.status ?? 'available'
}

function nextBookingFor(bayId: number): string | null {
  const now = new Date()
  const upcoming = bookings.value
    .filter(b => (b.bay_id ?? (b.bay as any)?.id) === bayId)
    .filter(b => new Date(b.starts_at) > now)
    .sort((a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime())
  if (!upcoming.length) return null
  return `التالي: ${formatTime(upcoming[0].starts_at)}`
}

function cellStyle(count: number | undefined) {
  const c = Number(count ?? 0)
  if (!c) {
    return {
      background: 'linear-gradient(145deg, rgba(241,245,249,0.9), rgba(226,232,240,0.65))',
      boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.6)',
    }
  }
  if (c === 1) {
    return {
      background: 'linear-gradient(145deg, #34d399, #059669)',
      boxShadow: '0 4px 14px rgba(5,150,105,0.35), inset 0 1px 0 rgba(255,255,255,0.25)',
    }
  }
  if (c === 2) {
    return {
      background: 'linear-gradient(145deg, #fcd34d, #ea580c)',
      boxShadow: '0 4px 14px rgba(234,88,12,0.3)',
    }
  }
  if (c === 3) {
    return {
      background: 'linear-gradient(145deg, #fb923c, #dc2626)',
      boxShadow: '0 4px 14px rgba(220,38,38,0.35)',
    }
  }
  return {
    background: 'linear-gradient(145deg, #f87171, #991b1b)',
    boxShadow: '0 4px 18px rgba(153,27,27,0.45)',
  }
}

function cellTooltipLine(bay: HeatmapRow, h: number) {
  const c = bay.slots?.[h] ?? 0
  if (!c) return 'لا حجز في هذه الساعة — خلية متاحة للجدولة'
  if (c === 1) return 'حجز واحد يغطي هذه الساعة'
  return `${c} حجوزات متداخلة — راقب الازدواجية أو الازدحام`
}

function sparkSegmentClass(bay: HeatmapRow, h: number) {
  const c = bay.slots?.[h] ?? 0
  if (!c) return 'bg-slate-200/90 dark:bg-slate-600/80'
  if (c === 1) return 'bg-gradient-to-t from-emerald-600 to-emerald-400'
  if (c === 2) return 'bg-gradient-to-t from-amber-600 to-amber-400'
  if (c === 3) return 'bg-gradient-to-t from-orange-600 to-red-500'
  return 'bg-gradient-to-t from-red-700 to-red-500'
}

function normalizeHeatmapRow(raw: Record<string, unknown>): HeatmapRow {
  const slotsRaw = (raw.slots ?? raw.hourly) as Record<number, number> | undefined
  const slots: Record<number, number> = {}
  if (slotsRaw && typeof slotsRaw === 'object') {
    for (const [k, v] of Object.entries(slotsRaw)) {
      const h = Number(k)
      if (!Number.isNaN(h)) slots[h] = Number(v) || 0
    }
  }
  const utilRaw = raw.utilization
  const serverUtilization =
    typeof utilRaw === 'number' && !Number.isNaN(utilRaw) ? utilRaw : undefined
  return {
    bay_id: Number(raw.bay_id),
    bay_name: String(raw.bay_name ?? raw.name ?? '—'),
    bay_code: String(raw.bay_code ?? raw.code ?? ''),
    status: String(raw.status ?? ''),
    slots,
    serverUtilization,
  }
}

function bayStatusDot(status: string) {
  return {
    'bg-green-500 animate-pulse': status === 'available',
    'bg-yellow-400':  status === 'reserved',
    'bg-red-500':     status === 'occupied',
    'bg-gray-400':    status === 'maintenance',
  }
}

function bayCardBorderClass(status: string) {
  return {
    'border-green-400 dark:border-green-600':  status === 'available',
    'border-yellow-400 dark:border-yellow-600': status === 'reserved',
    'border-red-400 dark:border-red-600':      status === 'occupied',
    'border-gray-300 dark:border-gray-600':    status === 'maintenance',
  }
}

function bayStatusBadge(status: string) {
  return {
    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400':   status === 'available',
    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': status === 'reserved',
    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400':            status === 'occupied',
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400':           status === 'maintenance',
  }
}

/** تسمية حالة الركن/الحظيرة فقط (ليست حالة أمر عمل — لا تُربَط بـ workOrderStatusLabels). */
function statusLabel(status: string) {
  const map: Record<string, string> = {
    available: 'متاح',
    reserved: 'محجوز',
    occupied: 'مشغول',
    maintenance: 'صيانة',
  }
  return map[status] ?? status
}

function bookingStatusBadge(status: string) {
  return {
    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400': status === 'confirmed',
    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400':     status === 'pending',
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400':        status === 'cancelled',
  }
}

function bookingStatusLabel(status: string) {
  const map: Record<string, string> = { confirmed: 'مؤكد', pending: 'معلق', cancelled: 'ملغى' }
  return map[status] ?? status
}

function formatTime(d: string) {
  return new Date(d).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
}

function formatDateAr(d: string) {
  return new Date(d).toLocaleDateString('ar-SA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

// ── Actions ──────────────────────────────────────────────────────────
function goToToday() {
  date.value = new Date().toISOString().slice(0, 10)
  load()
}

function prevDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() - 1)
  date.value = d.toISOString().slice(0, 10)
  load()
}

function nextDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() + 1)
  date.value = d.toISOString().slice(0, 10)
  load()
}

function openCellDetail(bay: HeatmapRow, hour: number) {
  selectedCell.value = { bay, hour, count: bay.slots?.[hour] ?? 0 }
}

function onHeatmapDateChange(val: { from: string; to: string }) {
  date.value = val.from || val.to
  load()
}

// ── Data Loading ─────────────────────────────────────────────────────
async function loadBranches() {
  try {
    const res = await apiClient.get('/branches', { params: { per_page: 100 } })
    const p = res.data?.data
    const rows = Array.isArray(p?.data) ? p.data : []
    branches.value = rows
    if (selectedBranchId.value == null && auth.user?.branch_id) {
      selectedBranchId.value = auth.user.branch_id
    }
    if (selectedBranchId.value == null && rows[0]?.id) {
      selectedBranchId.value = rows[0].id
    }
  } catch {
    branches.value = []
  }
}

function branchQuery(): string {
  const bid = selectedBranchId.value
  return bid != null && bid !== 0 ? `&branch_id=${bid}` : ''
}

/** Laravel غالباً يلفّ { data: T[] } أو تجميعة صفحات { data: { data: T[] } } */
function extractPaginatedRows(res: { data?: { data?: unknown } }): unknown[] {
  const d = res?.data?.data
  if (Array.isArray(d)) return d
  if (d && typeof d === 'object' && Array.isArray((d as { data?: unknown[] }).data))
    return (d as { data: unknown[] }).data
  return []
}

async function load() {
  loading.value = true
  try {
    await loadBranches()
    const bq = branchQuery()
    const [h, b, baysList] = await Promise.all([
      apiClient.get(`/bays/heatmap?date=${date.value}${bq}`),
      apiClient.get(`/bookings?date=${date.value}&per_page=200${bq}`),
      apiClient.get(`/bays${bq ? `?branch_id=${selectedBranchId.value}` : ''}`),
    ])
    const raw = h.data?.data ?? []
    heatmap.value = Array.isArray(raw) ? raw.map((row: Record<string, unknown>) => normalizeHeatmapRow(row)) : []
    bookings.value = extractPaginatedRows(b) as Booking[]
    bays.value = extractPaginatedRows(baysList) as Bay[]
  } finally {
    loading.value = false
  }
}

// ── Lifecycle ────────────────────────────────────────────────────────
onMounted(() => {
  load()
  clockTimer = setInterval(() => {
    currentHour.value = new Date().getHours()
  }, 60_000)
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.heatmap-page {
  background:
    radial-gradient(ellipse 120% 80% at 100% -20%, rgba(251, 146, 60, 0.08), transparent 50%),
    radial-gradient(ellipse 80% 60% at 0% 100%, rgba(99, 102, 241, 0.06), transparent 45%),
    rgb(248 250 252);
}
.dark .heatmap-page {
  background:
    radial-gradient(ellipse 100% 60% at 100% 0%, rgba(234, 88, 12, 0.12), transparent 50%),
    rgb(2 6 23);
}

.heatmap-hero-grid {
  background-image:
    linear-gradient(rgba(148, 163, 184, 0.12) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
  background-size: 24px 24px;
}

.heatmap-row-enter {
  animation: heatmap-row-in 0.45s ease backwards;
}
@keyframes heatmap-row-in {
  from {
    opacity: 0;
    transform: translateX(12px);
  }
}

.heatmap-kpi-bar {
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.2);
}
</style>
