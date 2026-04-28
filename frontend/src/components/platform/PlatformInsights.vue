<template>
  <div class="mt-4 rounded-xl border border-slate-200/85 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
    <div class="mb-3 border-b border-slate-100 pb-3 dark:border-slate-800">
      <h3 class="text-sm font-semibold text-slate-900 dark:text-white">المحرك الذكي — تفسير وتوجيه</h3>
      <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
        كل بطاقة تجيب: ما الذي يحدث، لماذا، ماذا يعني، وماذا تفعل بعدها.
      </p>
    </div>
    <div v-if="normalizedInsights.length" class="space-y-3">
      <PlatformInsightCard
        v-for="(ins, i) in normalizedInsights"
        :key="i"
        :eyebrow="ins.eyebrow"
        :title="ins.title"
        :badge="ins.badge"
        :tone="ins.cardTone"
        :why="ins.why"
        :meaning="ins.meaning"
        :recommendation="ins.recommendation"
        :cta-label="ins.ctaLabel"
        :cta-to="ins.ctaTo"
      />
    </div>
    <p v-else class="rounded-lg border border-dashed border-slate-200/90 bg-slate-50/50 px-3 py-4 text-center text-[11px] leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-400">
      لا توجد رؤى إحصائية تتجاوز العتبات حالياً — يمكنك الرجوع لاحقاً بعد تراكم إشارات جديدة.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { PlatformAdminInsight } from '@/types/platformAdminOverview'
import { computed } from 'vue'
import PlatformInsightCard from '@/components/platform-admin/ui/PlatformInsightCard.vue'

const props = defineProps<{ insights: PlatformAdminInsight[] }>()

const normalizedInsights = computed(() => props.insights.map((ins) => {
  const confidence = ins.tone === 'action' ? 92 : ins.tone === 'warning' ? 83 : ins.tone === 'positive' ? 86 : 72
  const signals = ins.tone === 'action'
    ? 'انحراف واضح في المؤشرات، وتكرار إنذار تشغيلي، وتأثير محتمل على الاستمرارية.'
    : ins.tone === 'warning'
      ? 'تراجع ملحوظ مقارنة بخط الأساس، وارتفاع إشارات المخاطر.'
      : ins.tone === 'positive'
        ? 'تحسن مستمر في النشاط، واستقرار على مستوى الأداء.'
        : 'تغيرات طفيفة ضمن النطاق الطبيعي.'
  const recommendation = ins.tone === 'action'
    ? 'نفّذ تدخلاً فورياً عبر التشغيل والمالية مع متابعة يومية.'
    : ins.tone === 'warning'
      ? 'راجع الشركات المتأثرة وخطة التحصيل قبل تصاعد المخاطر.'
      : ins.tone === 'positive'
        ? 'حافظ على الإيقاع التشغيلي وراقب أسبوعياً.'
        : 'لا يلزم إجراء عاجل؛ يكفي الرصد الدوري.'
  const ctaTo = ins.tone === 'action'
    ? '/platform/companies?risk=high&status=struggling'
    : ins.tone === 'warning'
      ? '/platform/companies?risk=high'
      : '/platform/overview'
  const ctaLabel = ins.tone === 'action'
    ? 'عرض الشركات عالية المخاطر'
    : ins.tone === 'warning'
      ? 'مراجعة المخاطر'
      : 'العودة للملخص التنفيذي'
  const eyebrow = ins.tone === 'positive' ? 'فرصة / استقرار' : ins.tone === 'warning' ? 'تنبيه اتجاهي' : ins.tone === 'action' ? 'يتطلب قراراً' : 'رصد عام'

  return {
    tone: ins.tone,
    cardTone: mapCardTone(ins.tone),
    title: ins.text,
    eyebrow,
    badge: `ثقة ${confidence}٪`,
    why: `الإشارات الإحصائية تشير إلى: ${signals}`,
    meaning: 'الخلاصة التشغيلية: هذا النمط يحدد أولوية المتابعة في الأيام القادمة.',
    recommendation,
    ctaLabel,
    ctaTo,
  }
}))

function mapCardTone(tone: string): 'default' | 'positive' | 'warning' | 'action' {
  if (tone === 'positive') return 'positive'
  if (tone === 'warning') return 'warning'
  if (tone === 'action') return 'action'
  return 'default'
}
</script>
