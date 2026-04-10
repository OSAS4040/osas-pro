"""
Voice bridge (optional):
- Listens for simple Arabic keywords from microphone.
- Sends serial commands to ESP32:
  START, A, B

This script is intentionally minimal for MVP demos.
"""

import time
import serial
import speech_recognition as sr

SERIAL_PORT = "COM6"  # adjust to your ESP32 port
BAUD_RATE = 115200

KEYWORDS = {
    "ابدأ": "START\n",
    "ابدا": "START\n",
    "ازدهار": "A\n",
    "الازدهار": "A\n",
    "اهمال": "B\n",
    "إهمال": "B\n",
}


def send_cmd(ser: serial.Serial, text: str) -> None:
    ser.write(text.encode("utf-8"))
    ser.flush()


def map_keyword(transcript: str) -> str | None:
    for key, cmd in KEYWORDS.items():
        if key in transcript:
            return cmd
    return None


def main() -> None:
    recognizer = sr.Recognizer()
    mic = sr.Microphone()

    with serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1) as ser:
        print("Voice bridge started. Speak Arabic keywords...")
        while True:
            with mic as source:
                recognizer.adjust_for_ambient_noise(source, duration=0.4)
                audio = recognizer.listen(source, phrase_time_limit=3)
            try:
                text = recognizer.recognize_google(audio, language="ar-SA")
                print("Heard:", text)
                cmd = map_keyword(text)
                if cmd:
                    send_cmd(ser, cmd)
                    print("Sent:", cmd.strip())
            except sr.UnknownValueError:
                pass
            except Exception as exc:
                print("Error:", exc)
                time.sleep(0.5)


if __name__ == "__main__":
    main()
