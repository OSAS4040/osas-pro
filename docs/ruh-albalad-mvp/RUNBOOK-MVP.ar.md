# Runbook نهائي - تشغيل MVP أمام المشرف

## 1) ما هو المعتمد في هذه النسخة

- تشغيل أساسي فقط: `أزرار + حساس + LED + DFPlayer`.
- `voice bridge` **خارج النطاق الحالي** ويضاف لاحقا بعد ثبات MVP.

## 2) خطوات الرفع والتشغيل الأولي

1. افتحي ملف: `code/esp32/ruh_albalad_mvp.ino`.
2. في Arduino IDE:
   - Board: `ESP32 Dev Module`
   - Port: منفذ اللوحة الصحيح (COM).
3. ثبتي المكتبات:
   - `Adafruit NeoPixel`
   - `DFRobotDFPlayerMini`
4. تأكدي أن `CFG::ENABLE_TEST_MODE = true` لأول اختبار.
5. ارفعي الكود.
6. افتحي Serial Monitor على `115200`.
7. شاهدي نتائج الاختبارات:
   - Buttons
   - LED
   - DFPlayer
   - Proximity
8. بعد نجاح الاختبارات، غيّري `CFG::ENABLE_TEST_MODE = false` ثم ارفعي الكود مرة ثانية.

## 3) Fail-safe المعتمد

### فشل قراءة microSD

- الأثر: لا صوت.
- السلوك: النظام يكمل بالإضاءة فقط.
- السجل: `FAILSAFE: microSD missing/empty`.

### عدم استجابة DFPlayer

- الأثر: لا صوت.
- السلوك: النظام يكمل المشاهد بصريا.
- السجل: `FAILSAFE: DFPlayer init failed`.

### تعطل حساس القرب

- الأثر: قد يبقى LOW دائما.
- السلوك: تعطيل الحساس تلقائيا بعد فترة stuck.
- البديل: التشغيل بزر `START`.
- السجل: `FAILSAFE: proximity sensor stuck LOW, disabled`.

### مشكلة في الطاقة

- الأثر: إعادة تشغيل مفاجئة (Brownout).
- السلوك: إظهار تحذير أحمر عند الإقلاع + رسالة Serial.
- السجل: `FAILSAFE: brownout detected`.

## 4) سيناريو اختبار كامل قبل العرض

1. تشغيل الطاقة.
2. التأكد في Serial:
   - `BOOT: Ruh Albalad MVP`
   - `DFPlayer ready` (أو Fail-safe واضح)
   - `STATE -> IDLE`
3. الضغط على `START`:
   - انتقال `INTRO`.
   - تشغيل `0001.mp3` (إذا الصوت جاهز).
4. بعد الافتتاح:
   - انتقال `AWAIT_SCENARIO`.
5. الضغط على `A`:
   - انتقال `SCENARIO_A`.
   - تشغيل `0002.mp3`.
6. إعادة الدورة ثم اختبار `B`:
   - انتقال `SCENARIO_B`.
   - تشغيل `0003.mp3`.
7. نهاية كل سيناريو:
   - `OUTRO` + `0004.mp3`.
8. عودة تلقائية إلى `IDLE`.
9. إعادة الدورة 5 مرات متتالية بدون تعليق.

## 5) معيار القبول (Ready for Demo)

- لا يوجد Reset مفاجئ.
- كل زر يستجيب خلال أقل من ثانية.
- الإضاءة تعمل طوال الدورة.
- الصوت يعمل أو Fail-safe واضح دون إيقاف العرض.
- دورة كاملة ناجحة 5 مرات متتالية.
