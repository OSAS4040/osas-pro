#!/bin/sh
# تشغيل وكيل ngrok: upstream = خدمة frontend:5173 على شبكة Docker.
# NGROK_DOMAIN (اختياري): دومين محجوز من لوحة ngrok — يبقى الرابط ثابتاً (يقلل ERR_NGROK_3200 عند إعادة التشغيل بنفس الإعداد).
set -e

if [ -z "$NGROK_AUTHTOKEN" ]; then
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "ngrok: ضع NGROK_AUTHTOKEN في ملف .env عند جذر المشروع (نسخة من .env.example)"
  echo "     https://dashboard.ngrok.com/get-started/your-authtoken"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  exit 1
fi
export NGROK_AUTHTOKEN

if [ -n "$NGROK_DOMAIN" ]; then
  # يجب أن يطابق دوميناً محجوزاً في حسابك على ngrok
  _d="$NGROK_DOMAIN"
  case "$_d" in
    https://*) _d="${_d#https://}" ;;
    http://*) _d="${_d#http://}" ;;
  esac
  _d="${_d%%/*}"
  exec ngrok http frontend:5173 --url="https://${_d}"
else
  exec ngrok http frontend:5173
fi
