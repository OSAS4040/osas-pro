/// V1 policy: online-first. No offline writes for financial / wallet / final stock.
/// Sensitive submits must go through Laravel with idempotency — enforced server-side.
abstract final class OfflinePolicy {
  static const String summary =
      'Online-first: لا يُنفَّذ دفع/محفظة/ترحيل مالي/خصم مخزون نهائي بدون اتصال بالخادم.';
}
