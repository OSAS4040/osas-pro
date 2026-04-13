/**
 * تسميات عربية للمفاتيح التقنية المعروضة في مركز العمليات (قراءة فقط).
 * إن وُجد مفتاح غير معروف يُعرض كما هو مع تنسيق مقروء.
 */
export function signalLabel(key: string): string {
  const map: Record<string, string> = {
    'domain_events.count_24h_rolling_last': 'عدد أحداث المجال (آخر 24 ساعة)',
    'domain_events.count_24h_rolling_prior': 'عدد أحداث المجال (الـ 24 ساعة التي قبلها)',
    'domain_events.count_in_requested_window': 'عدد الأحداث في نافذة الطلب',
    'domain_events.total_in_window': 'إجمالي الأحداث في النافذة',
    'domain_events.scoped_query': 'استعلام أحداث المجال (ضمن النطاق)',
    'domain_events.totals': 'إجماليات أحداث المجال',
    'domain_events.by_event_name': 'توزيع الأحداث حسب الاسم',
    'domain_events.aggregates': 'تجميعات أحداث المجال',
    'domain_events.top_event_name_share': 'نصيب أكثر نوع حدث تكراراً',
    'domain_events.by_event_name.CustomerCreated': 'حدث «إنشاء عميل»',
    'domain_events.by_event_name.WalletDebited': 'حدث «خصم محفظة»',
    'domain_events.by_event_name.WalletCredited': 'حدث «إيداع محفظة»',
    'event_record_failures.count_recent': 'عدد فشول تسجيل الأحداث الأخيرة',
    'config.intelligent.events.persist.enabled': 'إعداد تفعيل حفظ الأحداث',
    'recommendations.rule_engine_v1': 'محرك قواعد التوصيات (إصدار 1)',
  }
  return map[key] ?? key.replace(/\./g, ' › ')
}

export function thresholdKeyLabel(key: string): string {
  const map: Record<string, string> = {
    rule_id: 'معرّف القاعدة',
    prior_24h_minimum: 'حد أدنى لعدد الأحداث في الـ 24 ساعة السابقة',
    last_must_exceed_prior_by_factor: 'يجب أن يتجاوز الأخير السابق بهذا المضاعف',
    minimum_rows_to_alert: 'أدنى عدد صفوف لإظهار التنبيه',
    lookback_days: 'عدد أيام المراجعة للخلف',
    events_in_window_must_be: 'عدد الأحداث المطلوب في النافذة',
    persist_must_be: 'يجب أن يكون الحفظ',
    observed_events_in_window: 'عدد الأحداث الملاحظ في النافذة',
    persist_enabled: 'الحفظ مفعّل',
    minimum_events_for_pattern_rules: 'أدنى أحداث لقواعد الأنماط',
    observed: 'القيمة الملاحظة',
    customer_created_share_minimum: 'حد أدنى لنسبة أحداث إنشاء العميل',
    observed_share: 'النسبة الملاحظة',
    observed_customer_created: 'عدد أحداث إنشاء العملاء',
    observed_total_events: 'إجمالي الأحداث الملاحظ',
    debits_must_exceed_credits_by_factor: 'يجب أن يتجاوز الخصم الإيداع بهذا المضاعف',
    credits_must_be: 'شرط الإيداع',
    observed_debits: 'عدد أحداث الخصم',
    observed_credits: 'عدد أحداث الإيداع',
    top_event_share_minimum: 'حد أدنى لنصيب أكثر حدث',
    total_events_minimum: 'أدنى إجمالي للأحداث',
    observed_top_event: 'أكثر نوع حدث تكراراً',
    any_pattern_rule_triggered: 'هل فُعّلت قاعدة نمط',
  }
  return map[key] ?? key
}

export function domainEventNameAr(name: string): string {
  const map: Record<string, string> = {
    CustomerCreated: 'إنشاء عميل',
    WalletDebited: 'خصم محفظة',
    WalletCredited: 'إيداع محفظة',
    InvoiceIssued: 'إصدار فاتورة',
    PaymentRecorded: 'تسجيل دفعة',
  }
  return map[name] ?? name
}
