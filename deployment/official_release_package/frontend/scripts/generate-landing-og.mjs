/**
 * يولّد صورة Open Graph ‎1200×630‎ لصفحة /landing (PNG في public/).
 * تشغيل: npm run generate:og
 */
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import sharp from 'sharp'

const __dirname = dirname(fileURLToPath(import.meta.url))
const outPath = join(__dirname, '..', 'public', 'og-asaspro.png')

const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg width="1200" height="630" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0d9488"/>
      <stop offset="55%" stop-color="#0f766e"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#g)"/>
  <text x="600" y="260" text-anchor="middle" fill="#ffffff" font-family="Segoe UI, Tahoma, Arial, sans-serif" font-size="62" font-weight="700">أسس برو</text>
  <text x="600" y="330" text-anchor="middle" fill="#99f6e4" font-family="Segoe UI, Arial, sans-serif" font-size="30" font-weight="600">Osas Pro</text>
  <text x="600" y="390" text-anchor="middle" fill="#cbd5e1" font-family="Segoe UI, Arial, sans-serif" font-size="18">متعددة الأنشطة — ورش · تجزئة · جملة · توزيع · أساطيل</text>
</svg>`

await sharp(Buffer.from(svg, 'utf8')).png().toFile(outPath)
console.log('Wrote', outPath)
