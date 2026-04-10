# Pin Map نهائي + Wiring عملي (نسخة تنفيذ مباشر)

## اللوحة المعتمدة

- `ESP32 DevKit V1 (ESP-WROOM-32)`

## جدول Pins النهائي (بدون تعارض)

| المكون | الطرف | ESP32 Pin | ملاحظات |
|---|---|---|---|
| WS2812B | DIN | `GPIO18` | عبر مقاومة `330Ω` |
| زر START | Signal | `GPIO25` | الزر بين GPIO و GND (INPUT_PULLUP) |
| زر SCENARIO_A | Signal | `GPIO26` | الزر بين GPIO و GND |
| زر SCENARIO_B | Signal | `GPIO27` | الزر بين GPIO و GND |
| حساس قرب IR | OUT | `GPIO33` | خرج رقمي، LOW عند الكشف |
| DFPlayer | RX | `GPIO16 (TX2)` | عبر مقاومة `1kΩ` |
| DFPlayer | TX | `GPIO17 (RX2)` | مباشر |

## توصيل DFPlayer Mini كامل

- `DFPlayer VCC` -> `5V PSU`
- `DFPlayer GND` -> `GND مشترك`
- `DFPlayer SPK_1/SPK_2` -> سماعة `3W/4Ω`
- `DFPlayer RX` <- `ESP32 GPIO16` عبر مقاومة `1kΩ`
- `DFPlayer TX` -> `ESP32 GPIO17`

## Wiring Diagram مبسط

```text
                +------------------- 5V PSU -------------------+
                |                                               |
             +--+--+                                        +---+---+
             |ESP32|                                        |WS2812B|
             |     | GPIO18 --[330R]----------------------->| DIN   |
             |     | GND ----------------------------------->| GND   |
             |     | (USB/5V IN for board power)            | 5V <--+---- 5V PSU
             +--+--+                                        +-------+
                |
                | GPIO16 (TX2) --[1k]--> DFPlayer RX
                | GPIO17 (RX2) <-------- DFPlayer TX
                | GND ------------------> DFPlayer GND
                |                       DFPlayer VCC <-------- 5V PSU
                |                       DFPlayer SPK1/SPK2 ---> Speaker 3W/4Ω
                |
                | GPIO25 <---- START button ---- GND
                | GPIO26 <---- A button -------- GND
                | GPIO27 <---- B button -------- GND
                | GPIO33 <---- IR Sensor OUT
                | GND    <---- IR Sensor GND
                + 5V PSU ----> IR Sensor VCC

  Capacitor 1000uF between WS2812B 5V and GND near strip head.
```

## ترتيب ملفات الصوت النهائي على microSD

داخل جذر البطاقة (Root)، أسماء رقمية فقط:

1. `0001.mp3` = الافتتاح
2. `0002.mp3` = سيناريو ازدهار
3. `0003.mp3` = سيناريو إهمال
4. `0004.mp3` = الخاتمة

## تنبيهات حرجة تمنع التلف

- ممنوع تغذية شريط `WS2812B` من 3.3V أو من طرف ESP32 مباشرة.
- ممنوع توصيل `5V` من PSU إلى GPIOs.
- ممنوع تشغيل الشريط بدون `GND مشترك`.
- توصيل TX/RX بالعكس يسبب عدم عمل الصوت (ليس تلف غالبا)، لكن 5V على GPIO قد يتلف ESP32.
