<template>
  <section
    class="rounded-2xl border border-slate-200/90 bg-white/95 p-4 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/90 md:p-5"
    aria-labelledby="platform-overview-hub-title"
  >
    <h2 id="platform-overview-hub-title" class="mb-3 text-sm font-bold text-slate-900 dark:text-white">
      الوصول السريع
    </h2>
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
        </div>
        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ card.title }}</p>
        <p class="mt-1 line-clamp-2 text-[11px] leading-snug text-slate-500 dark:text-slate-400">{{ card.description }}</p>
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
        <p class="mt-1 text-[11px] leading-snug text-rose-800/90 dark:text-rose-200/85">واجهة التشغيل</p>
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
import {
  PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS,
  canAccessWithAnyPermission,
} from '@/config/platformSectionGate'

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
    title: 'العقود والمستأجرين',
    description: 'اختيار شركة، تصفية الخدمات والعقود، وربط السياق بالتسعير.',
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
  allCards.filter((c) => {
    if (c.requires && !auth.hasPermission(c.requires)) {
      return false
    }
    const extraKeys = PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS[c.routeName as keyof typeof PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS]
    if (
      extraKeys
      && !canAccessWithAnyPermission((p: string) => auth.hasPermission(p), extraKeys)
    ) {
      return false
    }
    return true
  }),
)
</script>
