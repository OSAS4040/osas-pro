/**
 * دليل المستخدم لكل صفحة — يُحدَّث رقم الإصدار عند تغيير المحتوى لمشاركة واضحة.
 * المفتاح: اسم المسار في vue-router (route.name) أو المسار الكامل كاحتياط.
 */
export type PageHelpSection = { title: string; body: string }
export type PageHelpEntry = {
  title: string
  summary: string
  sections: PageHelpSection[]
  /** يزيد مع كل مراجعة للمحتوى */
  version: number
  updatedAt: string
}

export const PAGE_HELP_VERSION = 1

const helpByRouteName: Record<string, PageHelpEntry> = {
  dashboard: {
    title: 'الرئيسية',
    summary: 'نظرة على المؤشرات، الرسوم البيانية، واختصارات العمل.',
    version: 1,
    updatedAt: '2026-03-29',
    sections: [
      { title: 'الرسوم', body: 'تعرض إيرادات وأوامر العمل لآخر 7 أيام؛ إن لم تظهر بيانات، تحقق من الفترة والصلاحيات.' },
      { title: 'اختصارات', body: 'استخدم بطاقات الوصول السريع للانتقال إلى العملاء، المركبات، أو إنشاء أمر عمل.' },
    ],
  },
  'workshop.commissions': {
    title: 'العمولات',
    summary: 'متابعة العمولات المستحقة والمدفوعة المرتبطة بالفواتير وأوامر العمل.',
    version: 1,
    updatedAt: '2026-03-29',
    sections: [
      { title: 'الصرف', body: 'يمكن صرف العمولة المعلقة بعد المراجعة المالية؛ العملية تُسجَّل في سجل التدقيق.' },
      { title: 'الربط مع السياسات', body: 'انتقل إلى «سياسات العمولات» لتعريف نسب، عملاء، موظفين، وسقف للمبلغ.' },
    ],
  },
  'workshop.commission-policies': {
    title: 'سياسات العمولات',
    summary: 'قواعد على الخادم تُحدد النسبة، الحد الأدنى للأساس، سقف العمولة، الأولوية، والربط بموظف أو عميل.',
    version: 1,
    updatedAt: '2026-03-29',
    sections: [
      { title: 'الأولوية', body: 'الرقم الأعلى يُنفَّذ أولاً؛ ثم القواعد المرتبطة بعميل محدد، ثم الموظف، ثم العامة.' },
      { title: 'الحضور', body: 'يُدمج مؤشر انتظام الحضور (آخر 30 يوماً) مع مضاعف الحضور لتعديل العمولة تلقائياً.' },
      { title: 'التكامل', body: 'حقل meta يحفظ معرفات خارجية (مثل مزود رواتب) لاستخدامها لاحقاً في التكاملات.' },
    ],
  },
  'workshop.attendance': {
    title: 'الحضور والانصراف',
    summary: 'تسجيل الحضور بالنظام أو GPS، ومشاهدة السجل الشهري لكل موظف أو للجميع.',
    version: 1,
    updatedAt: '2026-03-29',
    sections: [
      { title: 'GPS', body: 'يتطلب صلاحية الباقة؛ يمكن ضبط إحداثيات الفرع ونطاق السماح بالأمتار.' },
      { title: 'التكاملات', body: 'روابط إلى مودد/قوى/تأمينات اختيارية — التفعيل الفعلي من «التكاملات» وإعدادات المنشأة.' },
    ],
  },
  'workshop.salaries': {
    title: 'مسير الرواتب',
    summary: 'إنشاء مسير شهري، مراجعة صافي الراتب، الطباعة، التصدير، والمشاركة.',
    version: 1,
    updatedAt: '2026-03-29',
    sections: [
      { title: 'التصدير', body: 'CSV للتحليل؛ الطباعة من المتصفح؛ احفظ لقطة JSON محلياً أو شارك عبر زر المشاركة إن توفر.' },
      { title: 'العمولات', body: 'عمود العمولات يعكس ما دُمج في المسير عند توليده من الخادم.' },
    ],
  },
  branches: {
    title: 'إدارة الفروع',
    summary: 'تعريف فروع المنشأة، الفرع الرئيسي، الإحداثيات للخريطة، والوصول بين الفروع.',
    version: 1,
    updatedAt: '2026-03-30',
    sections: [
      { title: 'الإحداثيات', body: 'أدخل خط العرض والطول بدقة ليظهر الفرع على خريطة Google من صفحة «خريطة الفروع».' },
      { title: 'حد الباقة', body: 'عدد الفروع قد يقتصر حسب اشتراكك؛ رقِّ الباقة أو يُطلب من المالك إضافة فرع.' },
      { title: 'الحذف', body: 'حذف الفرع متاح لمالك المنشأة فقط، ولا يُسمح بحذف الفرع الرئيسي.' },
    ],
  },
  'branches.map': {
    title: 'خريطة الفروع',
    summary: 'عرض فروع المنشأة على خريطة Google عند توفر إحداثيات ومفتاح API.',
    version: 1,
    updatedAt: '2026-03-30',
    sections: [
      { title: 'مفتاح Google', body: 'أضف VITE_GOOGLE_MAPS_API_KEY في بيئة الواجهة وفعّل Maps JavaScript API في Google Cloud.' },
      { title: 'التعديل', body: 'من النافذة على الخريطة يمكنك فتح نموذج تعديل الفرع في النظام مباشرة.' },
    ],
  },
}

const helpByPath: Record<string, PageHelpEntry> = {
  '/': helpByRouteName.dashboard,
}

const genericHelp: PageHelpEntry = {
  title: 'دليل سريع',
  summary: 'استخدم القائمة الجانبية أو Ctrl+K للانتقال؛ راجع الإعدادات والتكاملات لربط الأنظمة الخارجية.',
  version: 1,
  updatedAt: '2026-03-29',
  sections: [
    { title: 'الأمان', body: 'لا تشارك بيانات الدخول؛ استخدم صلاحيات المستخدمين حسب الدور.' },
    { title: 'دعم', body: 'راجع سجل العمليات عند الشك في تغيير؛ يمكن تصدير التقارير من صفحات المالية والموارد البشرية.' },
  ],
}

export function getPageHelp(routeName: string | null | undefined, path: string): PageHelpEntry | null {
  if (routeName && helpByRouteName[routeName]) {
    return helpByRouteName[routeName]
  }
  if (helpByPath[path]) {
    return helpByPath[path]
  }
  return genericHelp
}

export function listPageHelpEntries(): Array<{ key: string; entry: PageHelpEntry }> {
  return Object.entries(helpByRouteName).map(([key, entry]) => ({ key, entry }))
}
