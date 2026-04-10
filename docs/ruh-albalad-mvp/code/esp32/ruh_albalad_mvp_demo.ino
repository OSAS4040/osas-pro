#include <Arduino.h>
#include <Adafruit_NeoPixel.h>
#include <DFRobotDFPlayerMini.h>
#include <HardwareSerial.h>

/*
  روح البلد - Demo أمام اللجنة
  - مدة إجمالية مستهدفة: 45-60 ثانية
  - تشغيل سريع ومستقر
  - بدون voice bridge

  ============================================================
  النسخة الرسمية يوم العرض: COMMISSION READY
  ============================================================
  هذه هي نسخة العرض الرسمية المعتمدة أمام اللجنة.
  الإعدادات النهائية مقفلة داخل CFG::COMMISSION_READY_PRESET.
  يمنع تعديل التوقيتات/السطوع/السلوك يوم العرض.

  Quick Recover (مرجع سريع):
  1) إذا لم يبدأ العرض:
     - اضغط START أو انتظر Auto-Start بعد 4 ثوان.
  2) إذا لم يعمل الصوت:
     - استمر في العرض (Safe Demo Mode) ثم افحص microSD/DFPlayer بعد الجولة.
  3) إذا توقف الحساس:
     - استخدم START فقط (الحساس ليس شرطا لنجاح العرض).
  4) إذا احتجت إعادة تشغيل سريعة:
     - افصل الطاقة 3 ثوان ثم أعد التوصيل.
*/

// =========================================================
// CFG - إعدادات العرض النهائية (موحدة)
// =========================================================
namespace CFG {
  static const bool COMMISSION_READY_PRESET = true; // لا يغير يوم العرض
  static const bool BACKUP_SAFE_PRESET = false;     // preset احتياطي شديد الأمان

  static const uint32_t SERIAL_BAUD = 115200;

  // Pin map (نهائي)
  static const uint8_t PIN_LED_DATA = 18;
  static const uint8_t PIN_BTN_START = 25;   // زر تشغيل سريع
  static const uint8_t PIN_BTN_A = 26;       // سيناريو A
  static const uint8_t PIN_BTN_B = 27;       // سيناريو B
  static const uint8_t PIN_PROX = 33;        // حساس قرب IR (اختياري)
  static const uint8_t PIN_DF_TX = 16;       // ESP TX2 -> DF RX (1k)
  static const uint8_t PIN_DF_RX = 17;       // ESP RX2 <- DF TX

  static const uint16_t LED_COUNT = 60;
  // السطوع النهائي:
  // - Commission Ready: 150
  // - Backup Safe: 120 (أكثر أمانا عند حساسية الطاقة)
  static const uint8_t LED_BRIGHTNESS = BACKUP_SAFE_PRESET ? 120 : 150;

  // توقيتات Demo النهائية:
  // Commission Ready إجمالي ~52 ثانية (موصى)
  // Backup Safe إجمالي ~49 ثانية
  static const unsigned long INTRO_MS = BACKUP_SAFE_PRESET ? 4000 : 4500;
  static const unsigned long PICK_MS = BACKUP_SAFE_PRESET ? 5000 : 6000;
  static const unsigned long SCENE_MS = BACKUP_SAFE_PRESET ? 33000 : 34000;
  static const unsigned long OUTRO_MS = BACKUP_SAFE_PRESET ? 7000 : 7000;

  // سلوك النسخة
  static const bool ENABLE_TEST_MODE = false;      // نهائي يوم العرض
  static const bool ENABLE_DEMO_AUTO_START = true; // الأفضل للعرض (مع START كخيار يدوي)
  static const unsigned long AUTO_START_AFTER_MS = BACKUP_SAFE_PRESET ? 3000 : 4000;
  static const bool ENABLE_PROX = BACKUP_SAFE_PRESET ? false : true;
  static const uint8_t DEFAULT_SCENARIO = 1;       // A هو الأفضل بصريا

  static const uint8_t AUDIO_VOLUME = BACKUP_SAFE_PRESET ? 22 : 24; // 0..30
}

HardwareSerial dfSerial(2);
DFRobotDFPlayerMini dfPlayer;
Adafruit_NeoPixel strip(CFG::LED_COUNT, CFG::PIN_LED_DATA, NEO_GRB + NEO_KHZ800);

enum State { IDLE, INTRO, PICK_SCENE, SCENE_A, SCENE_B, OUTRO };
State state = IDLE;
unsigned long stateStart = 0;
unsigned long bootMs = 0;

bool dfReady = false;
bool sdReady = false;
bool proxEnabled = CFG::ENABLE_PROX;
String serialCmd;

void logDemo(const String& msg) { Serial.println("[DEMO] " + msg); }
bool pressed(uint8_t pin) { return digitalRead(pin) == LOW; }

void setAll(uint8_t r, uint8_t g, uint8_t b) {
  for (uint16_t i = 0; i < CFG::LED_COUNT; i++) strip.setPixelColor(i, strip.Color(r, g, b));
  strip.show();
}

void waveWarm(uint16_t speedMs) {
  static uint8_t p = 0;
  p++;
  uint8_t k = 70 + (uint8_t)(55.0f * sin((float)p / speedMs * 6.28f));
  setAll((uint8_t)(k), (uint8_t)(k * 0.65), (uint8_t)(k * 0.25));
}

void waveCool(uint16_t speedMs) {
  static uint8_t p = 0;
  p++;
  uint8_t k = 50 + (uint8_t)(45.0f * sin((float)p / speedMs * 6.28f));
  setAll((uint8_t)(k * 0.25), (uint8_t)(k * 0.5), (uint8_t)(k));
}

void transition(State next) {
  state = next;
  stateStart = millis();
  switch (state) {
    case IDLE: logDemo("STATE: IDLE"); break;
    case INTRO: logDemo("STATE: INTRO"); break;
    case PICK_SCENE: logDemo("STATE: PICK_SCENE"); break;
    case SCENE_A: logDemo("STATE: SCENE_A"); break;
    case SCENE_B: logDemo("STATE: SCENE_B"); break;
    case OUTRO: logDemo("STATE: OUTRO"); break;
  }
}

void playSafe(uint8_t track) {
  if (!dfReady || !sdReady) return; // Safe Demo Mode: يكمل بدون صوت
  dfPlayer.play(track);
}

void setupAudio() {
  dfSerial.begin(9600, SERIAL_8N1, CFG::PIN_DF_RX, CFG::PIN_DF_TX);
  dfReady = dfPlayer.begin(dfSerial, true, true);
  if (dfReady) {
    dfPlayer.volume(CFG::AUDIO_VOLUME);
    sdReady = dfPlayer.readFileCounts() > 0;
  }
  logDemo(dfReady ? "DF: OK" : "DF: FAIL (silent mode)");
  logDemo(sdReady ? "SD: OK" : "SD: FAIL/EMPTY (silent mode)");
}

void parseSerial() {
  while (Serial.available() > 0) {
    char c = (char)Serial.read();
    if (c == '\n' || c == '\r') {
      serialCmd.trim();
      if (serialCmd == "START" && state == IDLE) {
        playSafe(1);
        transition(INTRO);
      }
      serialCmd = "";
    } else {
      serialCmd += c;
    }
  }
}

// ---------- TEST MODE ----------
void testMode() {
  logDemo("TEST: START");
  // Buttons
  logDemo("TEST: Buttons");
  unsigned long t0 = millis();
  while (millis() - t0 < 6000) {
    if (pressed(CFG::PIN_BTN_START)) logDemo("BTN START OK");
    if (pressed(CFG::PIN_BTN_A)) logDemo("BTN A OK");
    if (pressed(CFG::PIN_BTN_B)) logDemo("BTN B OK");
    delay(100);
  }
  // LED
  logDemo("TEST: LED");
  setAll(255, 0, 0); delay(500);
  setAll(0, 255, 0); delay(500);
  setAll(0, 0, 255); delay(500);
  setAll(255, 180, 60); delay(500);
  setAll(0, 0, 0);
  // DF
  logDemo("TEST: DF");
  if (dfReady && sdReady) { playSafe(1); delay(2000); }
  else { logDemo("TEST: DF skipped (safe mode)"); }
  // Proximity
  logDemo("TEST: PROX (5s window)");
  bool hit = false;
  t0 = millis();
  while (millis() - t0 < 5000) {
    if (digitalRead(CFG::PIN_PROX) == LOW) { hit = true; break; }
    delay(30);
  }
  logDemo(hit ? "PROX OK" : "PROX FAIL -> fallback START button");
  logDemo("TEST: END");
}

void setup() {
  Serial.begin(CFG::SERIAL_BAUD);
  delay(200);
  logDemo("BOOT: Ruh Albalad Demo");

  pinMode(CFG::PIN_BTN_START, INPUT_PULLUP);
  pinMode(CFG::PIN_BTN_A, INPUT_PULLUP);
  pinMode(CFG::PIN_BTN_B, INPUT_PULLUP);
  pinMode(CFG::PIN_PROX, INPUT_PULLUP);

  strip.begin();
  strip.setBrightness(CFG::LED_BRIGHTNESS);
  setAll(0, 0, 0);

  setupAudio();
  bootMs = millis();
  transition(IDLE);

  if (CFG::ENABLE_TEST_MODE) testMode();
}

void handleIdle() {
  waveWarm(40); // هادئ لكن واضح

  bool proxTrig = proxEnabled && (digitalRead(CFG::PIN_PROX) == LOW);
  bool autoTrig = CFG::ENABLE_DEMO_AUTO_START && (millis() - bootMs >= CFG::AUTO_START_AFTER_MS);
  if (pressed(CFG::PIN_BTN_START) || proxTrig || autoTrig) {
    playSafe(1); // 0001 intro
    transition(INTRO);
  }
}

void handleIntro() {
  // بداية قوية سريعة
  waveWarm(18);
  if (millis() - stateStart >= CFG::INTRO_MS) {
    transition(PICK_SCENE);
  }
}

void handlePick() {
  setAll(90, 90, 90);
  if (pressed(CFG::PIN_BTN_A)) {
    playSafe(2);
    transition(SCENE_A);
  } else if (pressed(CFG::PIN_BTN_B)) {
    playSafe(3);
    transition(SCENE_B);
  } else if (millis() - stateStart >= CFG::PICK_MS) {
    if (CFG::DEFAULT_SCENARIO == 2) {
      playSafe(3);
      transition(SCENE_B);
    } else {
      playSafe(2);
      transition(SCENE_A);
    }
  }
}

void handleSceneA() {
  // سيناريو العرض الأفضل: ازدهار (دافئ ومشرق)
  waveWarm(14);
  if (millis() - stateStart >= CFG::SCENE_MS) {
    playSafe(4);
    transition(OUTRO);
  }
}

void handleSceneB() {
  // سيناريو بديل فقط عند الحاجة
  waveCool(16);
  if (millis() - stateStart >= CFG::SCENE_MS) {
    playSafe(4);
    transition(OUTRO);
  }
}

void handleOutro() {
  unsigned long e = millis() - stateStart;
  if (e < CFG::OUTRO_MS) {
    int k = map((int)e, 0, (int)CFG::OUTRO_MS, 85, 0);
    setAll(k, (int)(k * 0.6), (int)(k * 0.25));
  } else {
    bootMs = millis(); // لإعادة auto-start في الدورة التالية عند الحاجة
    transition(IDLE);
  }
}

void loop() {
  parseSerial();


  

  switch (state) {
    case IDLE: handleIdle(); break;
    case INTRO: handleIntro(); break;
    case PICK_SCENE: handlePick(); break;
    case SCENE_A: handleSceneA(); break;
    case SCENE_B: handleSceneB(); break;
    case OUTRO: handleOutro(); break;
  }

  delay(20); // استقرار زمني خفيف
}
