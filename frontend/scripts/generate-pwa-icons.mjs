/**
 * يولّد أيقونات PNG لـ PWA (بدون خطوط عربية معقّدة — تعمل مع محرّك SVG في sharp).
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import sharp from 'sharp'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const outDir = path.join(__dirname, '..', 'public')

const svg512 = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="112" fill="#4f46e5"/>
  <g fill="none" stroke="#ffffff" stroke-width="28" stroke-linecap="round" stroke-linejoin="round">
    <path d="M148 196 L148 316 M148 256 L228 196 L228 316 Z"/>
    <path d="M284 196 L364 256 L284 316 M364 196 L364 316"/>
  </g>
</svg>`

async function main() {
  if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true })
  const buf = Buffer.from(svg512)
  await sharp(buf).resize(192, 192).png().toFile(path.join(outDir, 'pwa-192.png'))
  await sharp(buf).resize(512, 512).png().toFile(path.join(outDir, 'pwa-512.png'))
  // eslint-disable-next-line no-console -- سكربت بناء
  console.log('Wrote public/pwa-192.png and public/pwa-512.png')
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
