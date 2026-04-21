#include <Arduino.h>
#include <Adafruit_NeoPixel.h>
#include <DFRobotDFPlayerMini.h>
#include <HardwareSerial.h>
#include <esp_system.h>

/*
  ==============================================
  روح البلد - ESP32 MVP (نسخة مستقرة للعرض الأولي)
  ==============================================
  - تشغيل أساسي بدون voice bridge.
  - Test Mode لاختبار كل جزء منفصل.
  - Serial Logs واضحة لكل حالة وأي خطأ.
*/

// =========================================================
// الإعدادات الافتراضية (Default Config) - عدلي من هنا فقط
// =========================================================
namespace CFG {
  // اللوحة المعتمدة: ESP32 DevKit V1 (WROOM-32)
  static const uint32_t SERIAL_BAUD = 115200;

  // Pins
  static const uint8_t PIN_LED_DATA = 18;
  static const uint8_t PIN_BTN_START = 25;
  static const uint8_t PIN_BTN_A = 26;
  static const uint8_t PIN_BTN_B = 27;
  static const uint8_t PIN_PROX = 33; // حساس IR رقمي
  static const uint8_t PIN_DF_TX = 16; // ESP32 TX -> DF RX (مع مقاومة 1K)
  static const uint8_t PIN_DF_RX = 17; // ESP32 RX <- DF TX

  // LED strip المعتمد: WS2812B عدد 60
  static const uint16_t LED_COUNT = 60;
  static const uint8_t LED_BRIGHTNESS = 120; // 0..255

  // السلوك
  static const bool ENABLE_TEST_MODE = false; // true لاختبار الأجزاء منفصلة
  static const bool ENABLE_PROX_SENSOR = true; // إذا تعطل الحساس يعطل تلقائيا
  static const unsigned long INTRO_MS = 7000;
  static const unsigned long AWAIT_MS = 10000;
  static const unsigned long SCENE_MS = 30000;
  static const unsigned long OUTRO_MS = 6000;

  // الصوت
  static const uint8_t AUDIO_VOLUME = 22; // 0..30

  // مراقبة الحساس
  static const unsigned long PROX_STUCK_LOW_MS = 12000;
}

HardwareSerial dfSerial(2);
DFRobotDFPlayerMini dfPlayer;
Adafruit_NeoPixel strip(CFG::LED_COUNT, CFG::PIN_LED_DATA, NEO_GRB + NEO_KHZ800);

enum State { IDLE, INTRO, AWAIT_SCENARIO, SCENARIO_A, SCENARIO_B, OUTRO };
State currentState = IDLE;
unsigned long stateStartMs = 0;
unsigned long lastAnimMs = 0;

bool dfReady = false;
bool sdReady = false;
bool proxEnabled = CFG::ENABLE_PROX_SENSOR;
unsigned long proxLowSinceMs = 0;
int lastProx = HIGH;

String serialCommand;

// ---------- أدوات عامة ----------
void logLine(const String& msg) {
  Serial.println("[RUH] " + msg);
}

bool isPressed(uint8_t pin) {
  return digitalRead(pin) == LOW;
}

void setAll(uint8_t r, uint8_t g, uint8_t b) {
  for (uint16_t i = 0; i < CFG::LED_COUNT; i++) {
    strip.setPixelColor(i, strip.Color(r, g, b));
  }
  strip.show();
}

void breathingStep(uint8_t r, uint8_t g, uint8_t b, uint16_t periodMs) {
  // نبضة إضاءة غير حاجزة (non-blocking)
  unsigned long t = millis() % periodMs;
  float phase = (float)t / (float)periodMs;
  float wave = (phase < 0.5f) ? (phase * 2.0f) : (2.0f - phase * 2.0f);
  uint8_t k = (uint8_t)(wave * 120.0f) + 10;
  setAll((r * k) / 255, (g * k) / 255, (b * k) / 255);
}

void transition(State nextState) {
  currentState = nextState;
  stateStartMs = millis();
  switch (nextState) {
    case IDLE: logLine("STATE -> IDLE"); break;
    case INTRO: logLine("STATE -> INTRO"); break;
    case AWAIT_SCENARIO: logLine("STATE -> AWAIT_SCENARIO"); break;
    case SCENARIO_A: logLine("STATE -> SCENARIO_A"); break;
    case SCENARIO_B: logLine("STATE -> SCENARIO_B"); break;
    case OUTRO: logLine("STATE -> OUTRO"); break;
  }
}

void playTrackSafe(uint8_t idx) {
  // Fail-safe: الصوت اختياري، العرض لا يتوقف إذا تعطل
  if (!dfReady || !sdReady) {
    logLine("AUDIO SKIP: DF/SD not ready, requested track " + String(idx));
    return;
  }
  dfPlayer.play(idx);
  logLine("AUDIO PLAY: " + String(idx));
}

void parseSerialCommands() {
  while (Serial.available() > 0) {
    char c = (char)Serial.read();
    if (c == '\n' || c == '\r') {
      serialCommand.trim();
      if (serialCommand.length() > 0) {
        logLine("CMD: " + serialCommand);
        if (serialCommand == "START" && currentState == IDLE) {
          playTrackSafe(1);
          transition(INTRO);
        } else if (serialCommand == "A" && currentState == AWAIT_SCENARIO) {
          playTrackSafe(2);
          transition(SCENARIO_A);
        } else if (serialCommand == "B" && currentState == AWAIT_SCENARIO) {
          playTrackSafe(3);
          transition(SCENARIO_B);
        } else if (serialCommand == "TEST") {
          logLine("TIP: set CFG::ENABLE_TEST_MODE = true then upload");
        }
      }
      serialCommand = "";
    } else {
      serialCommand += c;
    }
  }
}

void setupAudio() {
  dfSerial.begin(9600, SERIAL_8N1, CFG::PIN_DF_RX, CFG::PIN_DF_TX);
  dfReady = dfPlayer.begin(dfSerial, true, true);
  if (!dfReady) {
    logLine("FAILSAFE: DFPlayer init failed (audio disabled)");
    sdReady = false;
    return;
  }
  dfPlayer.volume(CFG::AUDIO_VOLUME);
  uint16_t files = dfPlayer.readFileCounts();
  sdReady = files > 0;
  if (!sdReady) {
    logLine("FAILSAFE: microSD missing/empty (audio disabled)");
  } else {
    logLine("DFPlayer ready, files: " + String(files));
  }
}

void setupHardware() {
  pinMode(CFG::PIN_BTN_START, INPUT_PULLUP);
  pinMode(CFG::PIN_BTN_A, INPUT_PULLUP);
  pinMode(CFG::PIN_BTN_B, INPUT_PULLUP);
  pinMode(CFG::PIN_PROX, INPUT_PULLUP);

  strip.begin();
  strip.setBrightness(CFG::LED_BRIGHTNESS);
  strip.clear();
  strip.show();
}

void monitorProximityHealth() {
  if (!proxEnabled) return;
  int v = digitalRead(CFG::PIN_PROX);
  if (v == LOW) {
    if (lastProx != LOW) {
      proxLowSinceMs = millis();
    } else if (millis() - proxLowSinceMs > CFG::PROX_STUCK_LOW_MS) {
      proxEnabled = false;
      logLine("FAILSAFE: proximity sensor stuck LOW, disabled");
    }
  }
  lastProx = v;
}

void runFailSafePowerSignal() {
  // لا يوجد قياس جهد مباشر في الدائرة الحالية.
  // بديل عملي: تحذير مرئي ورسالة تسلسلية عند إعادة التشغيل المتكررة.
  esp_reset_reason_t reason = esp_reset_reason();
  if (reason == ESP_RST_BROWNOUT) {
    logLine("FAILSAFE: brownout detected -> check 5V PSU and GND");
    for (int i = 0; i < 3; i++) {
      setAll(255, 0, 0);
      delay(150);
      setAll(0, 0, 0);
      delay(100);
    }
  }
}

// ---------- Test Mode ----------
void testButtons() {
  logLine("TEST: Buttons (press each button)");
  unsigned long start = millis();
  while (millis() - start < 10000) {
    if (isPressed(CFG::PIN_BTN_START)) logLine("TEST OK: START button");
    if (isPressed(CFG::PIN_BTN_A)) logLine("TEST OK: A button");
    if (isPressed(CFG::PIN_BTN_B)) logLine("TEST OK: B button");
    delay(120);
  }
}

void testLeds() {
  logLine("TEST: LED strip");
  setAll(255, 0, 0); delay(700);
  setAll(0, 255, 0); delay(700);
  setAll(0, 0, 255); delay(700);
  setAll(255, 180, 60); delay(700);
  setAll(0, 0, 0);
  logLine("TEST OK: LED sequence done");
}

void testDfPlayer() {
  logLine("TEST: DFPlayer");
  if (!dfReady) {
    logLine("TEST FAIL: DFPlayer not initialized");
    return;
  }
  if (!sdReady) {
    logLine("TEST FAIL: microSD missing/empty");
    return;
  }
  playTrackSafe(1);
  delay(2500);
  playTrackSafe(4);
  delay(2500);
  logLine("TEST OK: DFPlayer tracks played");
}

void testProximity() {
  logLine("TEST: Proximity sensor (trigger within 8s)");
  unsigned long start = millis();
  bool hit = false;
  while (millis() - start < 8000) {
    if (digitalRead(CFG::PIN_PROX) == LOW) {
      hit = true;
      break;
    }
    delay(30);
  }
  if (hit) logLine("TEST OK: proximity triggered");
  else logLine("TEST WARN: proximity not triggered, fallback to START button");
}

void runTestMode() {
  logLine("========== TEST MODE ==========");
  testButtons();
  testLeds();
  testDfPlayer();
  testProximity();
  logLine("========== TEST MODE DONE ==========");
}

// ---------- حالات النظام ----------
void handleIdle() {
  if (millis() - lastAnimMs > 25) {
    breathingStep(40, 30, 20, 2200);
    lastAnimMs = millis();
  }

  bool proxTriggered = proxEnabled && (digitalRead(CFG::PIN_PROX) == LOW);
  if (isPressed(CFG::PIN_BTN_START) || proxTriggered) {
    playTrackSafe(1); // 0001 - intro
    transition(INTRO);
  }
}

void handleIntro() {
  if (millis() - lastAnimMs > 20) {
    breathingStep(255, 165, 60, 1400);
    lastAnimMs = millis();
  }
  if (millis() - stateStartMs >= CFG::INTRO_MS) {
    transition(AWAIT_SCENARIO);
  }
}

void handleAwaitScenario() {
  if (millis() - lastAnimMs > 35) {
    breathingStep(80, 80, 80, 1800);
    lastAnimMs = millis();
  }

  if (isPressed(CFG::PIN_BTN_A)) {
    playTrackSafe(2); // 0002 - prosperity
    transition(SCENARIO_A);
  } else if (isPressed(CFG::PIN_BTN_B)) {
    playTrackSafe(3); // 0003 - neglect
    transition(SCENARIO_B);
  } else if (millis() - stateStartMs >= CFG::AWAIT_MS) {
    logLine("No selection -> default SCENARIO_A");
    playTrackSafe(2);
    transition(SCENARIO_A);
  }
}

void handleScenarioA() {
  if (millis() - lastAnimMs > 18) {
    breathingStep(255, 180, 70, 1000);
    lastAnimMs = millis();
  }
  if (millis() - stateStartMs >= CFG::SCENE_MS) {
    playTrackSafe(4); // 0004 - outro
    transition(OUTRO);
  }
}

void handleScenarioB() {
  if (millis() - lastAnimMs > 25) {
    breathingStep(40, 90, 180, 1300);
    lastAnimMs = millis();
  }
  if (millis() - stateStartMs >= CFG::SCENE_MS) {
    playTrackSafe(4);
    transition(OUTRO);
  }
}

void handleOutro() {
  unsigned long elapsed = millis() - stateStartMs;
  if (elapsed < CFG::OUTRO_MS) {
    int k = map((int)elapsed, 0, (int)CFG::OUTRO_MS, 50, 0);
    setAll(k / 2, k / 3, k / 4);
  } else {
    transition(IDLE);
  }
}

void setup() {
  Serial.begin(CFG::SERIAL_BAUD);
  delay(200);
  logLine("BOOT: Ruh Albalad MVP");

  setupHardware();
  setupAudio();
  runFailSafePowerSignal();
  transition(IDLE);

  if (CFG::ENABLE_TEST_MODE) {
    runTestMode();
  }
}

void loop() {
  parseSerialCommands();
  monitorProximityHealth();

  switch (currentState) {
    case IDLE: handleIdle(); break;
    case INTRO: handleIntro(); break;
    case AWAIT_SCENARIO: handleAwaitScenario(); break;
    case SCENARIO_A: handleScenarioA(); break;
    case SCENARIO_B: handleScenarioB(); break;
    case OUTRO: handleOutro(); break;
  }
}
