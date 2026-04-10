#!/usr/bin/env node
/**
 * يقرأ واجهة وكيل ngrok المحلية (4040) ويطبع الرابط العام وقيمة VITE_DEV_PUBLIC_HOST.
 * يشغّل بعد: docker compose --profile ngrok up -d ngrok
 */
const base = process.env.NGROK_INSPECT_URL ?? 'http://127.0.0.1:4040'

async function main() {
  try {
    const res = await fetch(`${base}/api/tunnels`)
    if (!res.ok) {
      console.error(`فشل HTTP ${res.status} من ${base}/api/tunnels — هل حاوية ngrok تعمل؟`)
      process.exitCode = 1
      return
    }
    const data = await res.json()
    const tunnels = Array.isArray(data.tunnels) ? data.tunnels : []
    const https = tunnels.find((t) => t.proto === 'https') || tunnels.find((t) => t.public_url?.startsWith('https'))
    if (!https?.public_url) {
      console.error('لا يوجد نفق https نشط. شغّل: docker compose --profile ngrok up -d ngrok')
      console.error('وتأكد من ضبط NGROK_AUTHTOKEN في ملف .env عند جذر المشروع.')
      process.exitCode = 1
      return
    }
    const url = new URL(https.public_url)
    console.log('الرابط العام:', https.public_url)
    console.log('')
    console.log('أضف إلى frontend/.env ثم أعد تشغيل Vite (أو أعد تشغيل حاوية frontend):')
    console.log(`VITE_DEV_PUBLIC_HOST=${url.hostname}`)
    console.log('')
    console.log('ملاحظة: ERR_NGROK_3200 = الوكيل غير متصل. شغّل: make up-ngrok')
    console.log('للرابط الثابت: عيّن NGROK_DOMAIN في .env عند جذر المشروع (دومين محجوز من لوحة ngrok).')
  } catch (e) {
    console.error('تعذّر الاتصال بـ', base, '— شغّل ngrok أولاً (make ngrok-up أو docker compose --profile ngrok up -d ngrok)')
    console.error(String(e?.message ?? e))
    process.exitCode = 1
  }
}

main()
