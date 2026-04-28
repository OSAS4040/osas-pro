# Hot Tenant Bulk Work Orders — 200 Vehicles

## حالة الاختبار

**مغلق رسمياً** كاختبار سعة/سلوك لمسار الـ API: دفعة واحدة (hot tenant) حتى 200 مركبة، مع تكرار تشغيل k6 (VUs 1 / 5 / 10) وفق معايير القبول المتفق عليها (POST، poll `completed`، `counts`، بدون 5xx).

## معيار «عدم التكرار» (مُوثَّق)

| المعيار | معنى |
|--------|--------|
| **معتمد** | عدم تكرار **نفس المركبة (`vehicle_id`) داخل نفس دفعة العمل الجماعي** — أي داخل صف واحد من `work_order_batches` (`work_order_batch_id`). يُفرض ذلك في قاعدة البيانات عبر قيد فريد `work_order_batch_items_batch_vehicle_unique` على `(work_order_batch_id, vehicle_id)`. دفعة الـ bulk الحالية تربط **خدمة واحدة** (`bulk_service_code`) لكل الدفعة. |
| **مسموح (وليس تكراراً مخالفاً)** | أن تظهر **نفس المركبة** في **دفعة أخرى** (`work_order_batch_id` مختلف)، بما في ذلك لـ **خدمة أخرى** (`bulk_service_code` مختلف)؛ القيد لا يشمل `company_id` ولا الخدمة عبر الدفعات، بل **نطاق الدفعة فقط**. |
| **غير معتمد لهذا الاختبار** | أن تبقى مركبة واحدة دون أكثر من أمر عمل (`work_orders`) خلال فترة زمنية (مثلاً ساعة) عبر **كل** التشغيلات والطلبات؛ التشغيلات المتعددة على نفس أسطول الـ seed تُنشئ عمداً أوامر عمل جديدة لنفس المركبات. |

التحقق المعتمد بعد التشغيل:

```sql
SELECT work_order_batch_id, vehicle_id, COUNT(*) AS cnt
FROM work_order_batch_items
WHERE created_at >= NOW() - INTERVAL '1 hour'  -- اضبط النافذة حسب الحاجة
GROUP BY work_order_batch_id, vehicle_id
HAVING COUNT(*) > 1;
```

**المطلوب:** `0 rows`.

## مراجع سريعة

- سكربت الحمل: `work_orders_bulk.js`
- بيانات الاختبار: `php artisan db:seed --class=WorkOrderBulkLoadTestSeeder` (يطبع `K6_BULK_VEHICLE_IDS`)
