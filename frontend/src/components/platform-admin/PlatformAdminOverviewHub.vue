<template>
  <section
    class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-slate-50/80 to-primary-50/30 p-4 shadow-sm ring-1 ring-slate-100/80 dark:border-slate-700/80 dark:from-slate-900 dark:via-slate-900 dark:to-primary-950/20 dark:ring-slate-800/60 md:p-5"
    aria-labelledby="platform-overview-hub-title"
  >
    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 id="platform-overview-hub-title" class="text-sm font-bold text-slate-900 dark:text-white">
          الوصول السريع
        </h2>
        <p class="mt-0.5 max-w-2xl text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
          اختر وجهتك مباشرة — التفاصيل الكاملة في كل قسم. التسعير والمزودون يظهران حسب صلاحياتك.
        </p>
      </div>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <RouterLink
        v-for="card in visibleCards"
        :key="card.routeName"
        :to="{ name: card.routeName }"
        class="group flex min-h-[5.5rem] flex-col rounded-xl border border-slate-200/90 bg-white/95 p-4 text-right shadow-sm transition-all hover:border-primary-400/80 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900/90 dark:hover:border-primary-600/50 dark:focus-visible:ring-offset-slate-900"
      >
        <div class="mb-2 flex items-start justify-between gap-2">
          <span
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 ring-1 ring-primary-200/60 dark:bg-primary-950/60 dark:text-primary-200 dark:ring-primary-800/40"
          >
            <component :is="card.icon" class="h-5 w-5" aria-hidden="true" />
          </span>
          <span
            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-500 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-slate-800 dark:text-slate-400"
            dir="ltr"
          >
            →
          </span>
        </div>
        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ card.title }}</p>
        <p class="mt-1 line-clamp-2 text-[11px] leading-snug text-slate-600 dark:text-slate-400">{{ card.description }}</p>
      </RouterLink>
      <RouterLink
        v-if="auth.hasPermission('platform.subscription.manage')"
        to="/admin/subscriptions"
        class="group flex min-h-[5.5rem] flex-col rounded-xl border border-rose-200/90 bg-rose-50/50 p-4 text-right shadow-sm transition-all hover:border-rose-400/80 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 dark:border-rose-900/40 dark:bg-rose-950/25 dark:hover:border-rose-600/50 dark:focus-visible:ring-offset-slate-900"
      >
        <div class="mb-2 flex items-start justify-between gap-2">
          <span
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 ring-1 ring-rose-200/60 dark:bg-rose-950/50 dark:text-rose-200 dark:ring-rose-900/40"
          >
            <ClipboardDocumentCheckIcon class="h-5 w-5" aria-hidden="true" />
          </span>
        </div>
        <p class="text-sm font-bold text-rose-950 dark:text-rose-100">طلبات الاشتراكات</p>
        <p class="mt-1 text-[11px] leading-snug text-rose-900/80 dark:text-rose-200/90">
          مراجعة الطابور والفواتير — يفتح في واجهة التشغيل.
        </p>
      </RouterLink>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  BanknotesIcon,
  BellAlertIcon,
  BoltIcon,
  BuildingOffice2Icon,
  ChartBarIcon,
  ClipboardDocumentCheckIcon,
  CurrencyDollarIcon,
  DocumentTextIcon,
  LifebuoyIcon,
  Squares2X2Icon,
  TruckIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

type HubCard = {
  routeName: string
  title: string
  description: string
  icon: typeof BuildingOffice2Icon
  /** إن وُجدت يجب امتلاك الصلاحية */
  requires?: string
}

const allCards: HubCard[] = [
  {
    routeName: 'platform-companies',
    title: 'المشتركون (شركات)',
    description: 'حالة الشركات، المخاطر، الأولوية، والدخول لملف شركة.',
    icon: BuildingOffice2Icon,
  },
  {
    routeName: 'platform-customers',
    title: 'عملاء المنصة',
    description: 'عرض عملاء المستأجرين عبر كل الشركات.',
    icon: UsersIcon,
  },
  {
    routeName: 'platform-finance',
    title: 'النموذج المالي',
    description: 'الباقات والاعتمادات المالية على مستوى المنصة.',
    icon: BanknotesIcon,
  },
  {
    routeName: 'platform-incidents',
    title: 'مركز الحوادث',
    description: 'متابعة الحوادث والمرشحين والأولويات.',
    icon: BoltIcon,
  },
  {
    routeName: 'platform-notifications',
    title: 'مركز التنبيهات',
    description: 'ما يحتاج متابعة الآن مع روابط للتنفيذ.',
    icon: BellAlertIcon,
  },
  {
    routeName: 'platform-intelligence-command',
    title: 'سطح القيادة (ذكاء)',
    description: 'الإجراءات الموجّهة والمتابعة التشغيلية.',
    icon: Squares2X2Icon,
  },
  {
    routeName: 'platform-support',
    title: 'الدعم الفني',
    description: 'تذاكر الدعم عبر كل المشتركين.',
    icon: LifebuoyIcon,
  },
  {
    routeName: 'platform-pricing-requests',
    title: 'التسعير والتجارة',
    description: 'طلبات التسعير وسير الإنشاء والمراجعة والاعتماد.',
    icon: CurrencyDollarIcon,
    requires: 'platform.pricing.view',
  },
  {
    routeName: 'platform-providers-list',
    title: 'مزودو الخدمة',
    description: 'قائمة المزودين والتكاليف الداخلية للتسعير.',
    icon: TruckIcon,
    requires: 'platform.providers.manage',
  },
  {
    routeName: 'platform-contracts',
    title: 'العقود',
    description: 'ربط العقود بالتسعير والعملاء.',
    icon: DocumentTextIcon,
  },
  {
    routeName: 'platform-reports',
    title: 'التقارير',
    description: 'تقارير تجارية على مستوى المنصة.',
    icon: ChartBarIcon,
  },
]

const visibleCards = computed(() =>
  allCards.filter((c) => (c.requires ? auth.hasPermission(c.requires) : true)),
)
</script>
